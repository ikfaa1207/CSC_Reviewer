<?php

namespace App\Jobs;

use App\Models\Question;
use App\Models\Setting;
use App\Services\AI\AIService;
use App\Traits\TracksJobStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Queue job for verifying questions using AI.
 * 
 * This job handles bulk question verification in the background.
 */
class VerifyQuestionsJob implements ShouldQueue
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
     * @param int $userId The user who initiated the verification
     * @param array $questionIds The IDs of questions to verify
     * @param array $metadata Additional metadata for tracking
     */
    public function __construct(
        public int $userId,
        public array $questionIds,
        public array $metadata = []
    ) {
        $this->queue = 'ai-verification';
    }

    /**
     * Execute the job.
     */
    public function handle(AIService $aiService): void
    {
        Log::info('Starting VerifyQuestionsJob', [
            'user_id' => $this->userId,
            'question_count' => count($this->questionIds),
        ]);

        // Create job status tracking
        $this->createJobStatus([
            'total' => count($this->questionIds),
            'question_ids' => $this->questionIds,
        ]);

        try {
            $questions = Question::with('options')
                ->whereIn('id', $this->questionIds)
                ->where(function ($q) {
                    $q->whereNull('audit_status')->orWhere('audit_status', '!=', 'passed');
                })
                ->get();

            if ($questions->isEmpty()) {
                Log::info('No questions to verify', [
                    'question_ids' => $this->questionIds,
                ]);
                $this->markJobCompleted(['message' => 'No questions to verify']);
                return;
            }

            $verifiedCount = 0;
            $failedCount = 0;
            $unchangedCount = 0;
            $totalQuestions = count($questions);
            $processedQuestions = 0;

            // Process in chunks to avoid memory issues
            $chunkSize = 10;
            $chunks = array_chunk($questions->toArray(), $chunkSize);

            foreach ($chunks as $chunk) {
                $result = $this->verifyChunk($chunk, $aiService);
                $verifiedCount += $result['verified'];
                $failedCount += $result['failed'];
                $unchangedCount += $result['unchanged'];
                $processedQuestions += count($chunk);

                // Update progress after each chunk
                $progress = (int) (($processedQuestions / $totalQuestions) * 100);
                $this->updateJobProgress($progress, $verifiedCount, $failedCount);
            }

            Log::info('VerifyQuestionsJob completed', [
                'user_id' => $this->userId,
                'verified' => $verifiedCount,
                'failed' => $failedCount,
                'unchanged' => $unchangedCount,
            ]);

            // Mark job as completed with results
            $this->markJobCompleted([
                'verified_count' => $verifiedCount,
                'failed_count' => $failedCount,
                'unchanged_count' => $unchangedCount,
                'message' => "Verified {$verifiedCount}, failed {$failedCount}, unchanged {$unchangedCount}",
            ]);

        } catch (\Exception $e) {
            Log::error('VerifyQuestionsJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->markJobFailed($e, ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Verify a chunk of questions.
     */
    private function verifyChunk(array $chunk, AIService $aiService): array
    {
        $verifiedCount = 0;
        $failedCount = 0;
        $unchangedCount = 0;

        // Prepare questions for verification
        $questionsToVerify = array_map(function ($q) {
            return [
                'id' => $q['id'],
                'question_text' => $q['question_text'],
                'options' => $q['options']->pluck('option_text')->toArray(),
                'correct_option_index' => $q['options']->search(fn($o) => $o['is_correct']),
                'explanation' => $q['explanation'],
                'problem_type_tag' => $q['problem_type_tag'],
            ];
        }, $chunk);

        // Get database candidates for duplicate checking
        $dbCandidates = Question::whereIn('id', array_column($chunk, 'id'))
            ->where('id', '!=', function($q) use ($chunk) {
                // This is a simplified approach - in practice, you'd want to get
                // questions from the same category or with similar tags
            })
            ->take(30)
            ->get(['question_text', 'problem_type_tag'])
            ->toArray();

        // Verify using AI
        $verifiedQuestions = $aiService->verifyQuestions($questionsToVerify, $dbCandidates);

        // Process results
        DB::transaction(function () use ($verifiedQuestions, &$verifiedCount, &$failedCount, &$unchangedCount) {
            foreach ($verifiedQuestions as $verified) {
                $questionId = $verified['id'] ?? null;
                if (!$questionId) {
                    continue;
                }

                $question = Question::find($questionId);
                if (!$question) {
                    continue;
                }

                if ($verified['is_valid'] ?? true) {
                    // Question passed verification
                    if ($question->audit_status !== 'passed') {
                        $question->update([
                            'audit_status' => 'passed',
                            'audit_error' => null,
                        ]);
                        $verifiedCount++;
                    } else {
                        $unchangedCount++;
                    }
                } else {
                    // Question failed verification
                    $errorReason = $verified['error_reason'] ?? 'Verification failed';
                    
                    // Determine error type
                    if (str_contains(strtolower($errorReason), 'duplicate')) {
                        $status = 'duplicate';
                    } elseif (str_contains(strtolower($errorReason), 'structure')) {
                        $status = 'failed_structure';
                    } else {
                        $status = 'failed_facts';
                    }

                    $question->update([
                        'audit_status' => $status,
                        'audit_error' => $errorReason,
                    ]);
                    $failedCount++;
                }
            }
        });

        return [
            'verified' => $verifiedCount,
            'failed' => $failedCount,
            'unchanged' => $unchangedCount,
        ];
    }

    /**
     * Get the display name for the job.
     */
    public function displayName(): string
    {
        return "Verify " . count($this->questionIds) . " questions";
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('VerifyQuestionsJob failed permanently', [
            'job_id' => $this->job->getJobId(),
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'ai',
            'question-verification',
            'user:' . $this->userId,
        ];
    }
}
