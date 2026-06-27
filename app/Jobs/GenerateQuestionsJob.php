<?php

namespace App\Jobs;

use App\Enums\AIProvider;
use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Setting;
use App\Services\AI\AIService;
use App\Services\AI\QuestionNormalizer;
use App\Services\AI\QuestionValidator;
use App\Traits\TracksJobStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Queue job for generating questions using AI.
 * 
 * This job handles bulk question generation in the background to prevent
 * HTTP timeouts and improve user experience.
 */
class GenerateQuestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TracksJobStatus;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The number of seconds to wait before the job times out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param int $userId The user who initiated the generation
     * @param int|string $categoryId The exam category ID or 'all'
     * @param string $level The exam level (professional/sub_professional)
     * @param int $count The number of questions to generate
     * @param array $metadata Additional metadata for tracking
     */
    public function __construct(
        public int $userId,
        public int|string $categoryId,
        public string $level,
        public int $count,
        public array $metadata = []
    ) {
        // Set queue based on priority
        $this->queue = 'ai-generation';
    }

    /**
     * Execute the job.
     */
    public function handle(AIService $aiService): void
    {
        Log::info('Starting GenerateQuestionsJob', [
            'user_id' => $this->userId,
            'category_id' => $this->categoryId,
            'level' => $this->level,
            'count' => $this->count,
        ]);

        // Create job status tracking
        $this->createJobStatus($this->metadata);

        try {
            // Get categories
            if ($this->categoryId === 'all') {
                $categories = ExamCategory::where('level', 'both')
                    ->orWhere('level', $this->level)
                    ->get();
            } else {
                $category = ExamCategory::find($this->categoryId);
                $categories = $category ? collect([$category]) : collect();
            }

            if ($categories->isEmpty()) {
                Log::warning('No categories found for generation', [
                    'category_id' => $this->categoryId,
                    'level' => $this->level,
                ]);
                $this->markJobCompleted(['message' => 'No categories found']);
                return;
            }

            // Distribute questions across categories
            $categoryCount = $categories->count();
            $baseCount = (int) ($this->count / $categoryCount);
            $remainder = $this->count % $categoryCount;

            $totalSaved = 0;
            $totalSkipped = 0;
            $totalCategories = $categories->count();
            $processedCategories = 0;

            foreach ($categories as $index => $category) {
                $catCount = $baseCount + ($index < $remainder ? 1 : 0);
                if ($catCount <= 0) {
                    continue;
                }

                $result = $this->generateForCategory($category, $catCount, $aiService);
                $totalSaved += $result['saved'];
                $totalSkipped += $result['skipped'];
                $processedCategories++;

                // Update progress after each category
                $progress = (int) (($processedCategories / $totalCategories) * 100);
                $this->updateJobProgress($progress, $totalSaved, $totalSkipped);
            }

            Log::info('GenerateQuestionsJob completed', [
                'user_id' => $this->userId,
                'saved' => $totalSaved,
                'skipped' => $totalSkipped,
            ]);

            // Mark job as completed with results
            $this->markJobCompleted([
                'saved_count' => $totalSaved,
                'skipped_count' => $totalSkipped,
                'message' => "Generated {$totalSaved} questions, skipped {$totalSkipped}",
            ]);

        } catch (\Exception $e) {
            Log::error('GenerateQuestionsJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->markJobFailed($e, ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Generate questions for a specific category.
     */
    private function generateForCategory(ExamCategory $category, int $count, AIService $aiService): array
    {
        $savedCount = 0;
        $skippedCount = 0;
        $maxAttempts = 3;
        $discardedTexts = [];

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $remaining = $count - $savedCount;
            if ($remaining <= 0) {
                break;
            }

            // Generate seed offset for uniqueness
            $seedOffset = (int)(microtime(true) * 1000) % 1000000 + rand(1000, 9999) + ($attempt * 100);

            // Generate questions
            $questionsList = $aiService->generateQuestions(
                $category->name,
                $this->level,
                $remaining,
                $seedOffset,
                $discardedTexts
            );

            // Convert to arrays for processing
            $questionsArray = array_map(function ($q) {
                return $q->toArray();
            }, $questionsList);

            // Validate and improve questions
            $validationResult = QuestionValidator::validateImproveAndCheckDuplicates(
                $questionsArray,
                $this->getExistingQuestionsForCategory($category)
            );

            // Process valid questions
            $seenInBatch = [];
            DB::transaction(function () use ($validationResult, $category, &$savedCount, &$skippedCount, &$discardedTexts, &$seenInBatch) {
                foreach ($validationResult['valid'] as $question) {
                    $hash = Question::computeHash($question['question_text']);

                    // Avoid duplicate within batch
                    if (in_array($hash, $seenInBatch)) {
                        $skippedCount++;
                        $discardedTexts[] = $question['question_text'];
                        continue;
                    }

                    // Check database duplicate
                    if (Question::where('question_hash', $hash)->exists()) {
                        $skippedCount++;
                        $discardedTexts[] = $question['question_text'];
                        continue;
                    }

                    $seenInBatch[] = $hash;

                    // Create the question
                    $questionModel = Question::create([
                        'exam_category_id' => $category->id,
                        'question_text' => $question['question_text'],
                        'explanation' => $question['explanation'] ?? null,
                        'audit_status' => 'passed',
                        'problem_type_tag' => $question['problem_type_tag'] ?? null,
                    ]);

                    // Create options
                    foreach ($question['options'] as $idx => $optText) {
                        QuestionOption::create([
                            'question_id' => $questionModel->id,
                            'option_text' => $optText,
                            'is_correct' => $idx === (int)$question['correct_option_index'],
                        ]);
                    }

                    $savedCount++;
                }

                // Count skipped (invalid + duplicates)
                $skippedCount += count($validationResult['invalid']) + count($validationResult['duplicates']);
                
                // Add discarded texts from invalid and duplicate questions
                foreach ($validationResult['invalid'] as $invalid) {
                    $discardedTexts[] = $invalid['question']['question_text'] ?? '';
                }
                foreach ($validationResult['duplicates'] as $duplicate) {
                    $discardedTexts[] = $duplicate['question']['question_text'] ?? '';
                }
            });
        }

        return [
            'saved' => $savedCount,
            'skipped' => $skippedCount,
        ];
    }

    /**
     * Get existing questions for a category for duplicate prevention.
     */
    private function getExistingQuestionsForCategory(ExamCategory $category): array
    {
        return Question::where('exam_category_id', $category->id)
            ->orderBy('created_at', 'desc')
            ->take(40)
            ->get(['question_text', 'problem_type_tag'])
            ->map(function ($q) {
                return [
                    'question_text' => $q->question_text,
                    'problem_type_tag' => $q->problem_type_tag,
                ];
            })
            ->toArray();
    }

    /**
     * Get the display name for the job.
     */
    public function displayName(): string
    {
        $categoryName = $this->categoryId === 'all' ? 'All Categories' : 
            (ExamCategory::find($this->categoryId)?->name ?? 'Unknown');
        
        return "Generate {$this->count} questions for {$categoryName} ({$this->level})";
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateQuestionsJob failed permanently', [
            'job_id' => $this->job->getJobId(),
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);

        // You could send a notification here
        // $this->user->notify(new JobFailedNotification($this, $exception));
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'ai',
            'question-generation',
            'category:' . ($this->categoryId === 'all' ? 'all' : $this->categoryId),
            'level:' . $this->level,
            'user:' . $this->userId,
        ];
    }
}
