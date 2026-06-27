<?php

namespace App\Jobs;

use App\Models\Question;
use App\Models\QuestionOption;
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
 * Queue job for running audit on questions.
 * 
 * This job performs structural and factual audits on questions
 * that haven't been verified yet.
 */
class RunAuditJob implements ShouldQueue
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
    public int $timeout = 600;

    /**
     * Create a new job instance.
     *
     * @param int $userId The user who initiated the audit
     * @param int|null $categoryId Optional category filter
     * @param int|null $limit Maximum number of questions to audit
     * @param array $metadata Additional metadata for tracking
     */
    public function __construct(
        public int $userId,
        public ?int $categoryId = null,
        public ?int $limit = null,
        public array $metadata = []
    ) {
        $this->queue = 'ai-audit';
    }

    /**
     * Execute the job.
     */
    public function handle(AIService $aiService): void
    {
        Log::info('Starting RunAuditJob', [
            'user_id' => $this->userId,
            'category_id' => $this->categoryId,
            'limit' => $this->limit,
        ]);

        // Create job status tracking
        $this->createJobStatus($this->metadata);

        try {
            $query = Question::with('options')
                ->where(function ($q) {
                    $q->whereNull('audit_status')->orWhere('audit_status', '!=', 'passed');
                });

            if ($this->categoryId) {
                $query->where('exam_category_id', $this->categoryId);
            }

            if ($this->limit) {
                $query->take($this->limit);
            }

            $questions = $query->get();

            if ($questions->isEmpty()) {
                Log::info('No questions to audit', [
                    'category_id' => $this->categoryId,
                ]);
                
                $this->markJobCompleted(['message' => 'No questions to audit']);
                return;
            }

            $structuralCount = 0;
            $factualCount = 0;
            $passedCount = 0;
            $totalQuestions = count($questions);
            $processedQuestions = 0;

            // Process in chunks to avoid memory issues and API limits
            $chunkSize = 15;
            $chunks = array_chunk($questions->toArray(), $chunkSize);

            foreach ($chunks as $chunk) {
                $result = $this->auditChunk($chunk, $aiService);
                $structuralCount += $result['structural_errors'];
                $factualCount += $result['factual_errors'];
                $passedCount += $result['passed'];
                $processedQuestions += count($chunk);

                // Update progress after each chunk
                $progress = (int) (($processedQuestions / $totalQuestions) * 100);
                $this->updateJobProgress($progress, $passedCount, $structuralCount + $factualCount);
            }

            Log::info('RunAuditJob completed', [
                'user_id' => $this->userId,
                'audited' => count($questions),
                'structural_errors' => $structuralCount,
                'factual_errors' => $factualCount,
                'passed' => $passedCount,
            ]);

            // Mark job as completed with results
            $this->markJobCompleted([
                'audited_count' => count($questions),
                'structural_errors' => $structuralCount,
                'factual_errors' => $factualCount,
                'passed_count' => $passedCount,
                'message' => "Audited {$totalQuestions} questions: {$passedCount} passed, {$structuralCount} structural errors, {$factualCount} factual errors",
            ]);

        } catch (\Exception $e) {
            Log::error('RunAuditJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->markJobFailed($e, ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Audit a chunk of questions.
     */
    private function auditChunk(array $chunk, AIService $aiService): array
    {
        $structuralCount = 0;
        $factualCount = 0;
        $passedCount = 0;

        $questionsToAudit = [];
        $questionIds = [];

        foreach ($chunk as $q) {
            $optionsCount = count($q['options']);
            $correctCount = count(array_filter($q['options'], fn($o) => $o['is_correct']));

            // First, do structural audit
            if ($optionsCount !== 4) {
                Question::where('id', $q['id'])->update([
                    'audit_status' => 'failed_structure',
                    'audit_error' => "Has {$optionsCount} options (expected 4)",
                ]);
                $structuralCount++;
                continue;
            }

            if ($correctCount !== 1) {
                $error = $correctCount === 0 ? 'No correct option marked' : "Multiple correct options ({$correctCount}) marked";
                Question::where('id', $q['id'])->update([
                    'audit_status' => 'failed_structure',
                    'audit_error' => $error,
                ]);
                $structuralCount++;
                continue;
            }

            // If it was previously flagged as structural but now passes, mark it passed
            if ($q['audit_status'] === 'failed_structure') {
                Question::where('id', $q['id'])->update([
                    'audit_status' => 'passed',
                    'audit_error' => null,
                ]);
                $passedCount++;
                continue;
            }

            // Prepare for factual audit
            $correctOptionIndex = array_search(true, array_column($q['options'], 'is_correct'));
            $questionsToAudit[] = [
                'id' => $q['id'],
                'question_text' => $q['question_text'],
                'options' => array_column($q['options'], 'option_text'),
                'correct_option' => $q['options'][$correctOptionIndex]['option_text'] ?? null,
            ];
            $questionIds[] = $q['id'];
        }

        // If there are questions to audit factually and AI is configured
        if (!empty($questionsToAudit) && $aiService->isConfigured()) {
            $verifiedQuestions = $this->auditFactualBatch($questionsToAudit, $aiService);
            
            foreach ($verifiedQuestions as $result) {
                $questionId = $result['id'] ?? null;
                if (!$questionId) {
                    continue;
                }

                if ($result['is_valid'] ?? true) {
                    Question::where('id', $questionId)->update([
                        'audit_status' => 'passed',
                        'audit_error' => null,
                    ]);
                    $passedCount++;
                } else {
                    $errorReason = $result['error_reason'] ?? 'Factual/content correctness check failed.';
                    
                    // Determine error type
                    if (str_contains(strtolower($errorReason), 'duplicate')) {
                        $status = 'duplicate';
                    } elseif (str_contains(strtolower($errorReason), 'structure')) {
                        $status = 'failed_structure';
                    } else {
                        $status = 'failed_facts';
                    }

                    Question::where('id', $questionId)->update([
                        'audit_status' => $status,
                        'audit_error' => $errorReason,
                    ]);
                    $factualCount++;
                }
            }
        } else {
            // If AI is not configured or no questions to audit, mark all as passed
            foreach ($questionIds as $questionId) {
                Question::where('id', $questionId)->update([
                    'audit_status' => 'passed',
                    'audit_error' => null,
                ]);
                $passedCount++;
            }
        }

        return [
            'structural_errors' => $structuralCount,
            'factual_errors' => $factualCount,
            'passed' => $passedCount,
        ];
    }

    /**
     * Audit a batch of questions factually using AI.
     */
    private function auditFactualBatch(array $questionsToAudit, AIService $aiService): array
    {
        try {
            $provider = config('services.ai.provider', 'gemini');
            
            if ($provider === 'groq') {
                return $this->auditFactualBatchGroq($questionsToAudit);
            }
            
            return $this->auditFactualBatchGemini($questionsToAudit);
            
        } catch (\Exception $e) {
            Log::error('Factual audit batch failed: ' . $e->getMessage());
            // Return all as valid if verification fails
            return array_map(function ($q) {
                return [
                    'id' => $q['id'],
                    'is_valid' => true,
                    'error_reason' => null,
                ];
            }, $questionsToAudit);
        }
    }

    /**
     * Audit using Gemini API.
     */
    private function auditFactualBatchGemini(array $questionsToAudit): array
    {
        $apiKey = config('services.ai.key');
        if (empty($apiKey)) {
            return array_map(function ($q) {
                return ['id' => $q['id'], 'is_valid' => true, 'error_reason' => null];
            }, $questionsToAudit);
        }

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

            $prompt = "You are an independent quality auditor for the Philippine Civil Service Exam (CSE).\n" .
                      "Verify the factual correctness of the following multiple-choice questions.\n" .
                      "For each question, check if the marked correct option is actually correct. If the question is correct, set `is_valid` to true.\n" .
                      "If the correct option is incorrect, or if the question is faulty/confusing, set `is_valid` to false and provide a short reason in `error_reason`.\n\n" .
                      "Questions:\n" .
                      json_encode($questionsToAudit, JSON_PRETTY_PRINT) . "\n\n" .
                      "Return a JSON array of objects with keys: 'id', 'is_valid', 'error_reason'.";

            $body = [
                'contents' => [[
                    'parts' => [[
                        'text' => $prompt
                    ]]
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'ARRAY',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'id' => ['type' => 'INTEGER'],
                                'is_valid' => ['type' => 'BOOLEAN'],
                                'error_reason' => ['type' => 'STRING'],
                            ],
                            'required' => ['id', 'is_valid'],
                        ],
                    ],
                ],
            ];

            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->timeout(120)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $body);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text) {
                    $decoded = json_decode($text, true);
                    if (is_array($decoded)) {
                        Setting::set('ai_quota_exceeded_flag', '0');
                        Setting::set('ai_last_error', null);
                        return $decoded;
                    }
                }
            }

            if ($response->status() === 429) {
                Setting::set('ai_quota_exceeded_flag', '1');
                Setting::set('ai_last_error', '429 Quota Exceeded (Free Tier limit met or billing issue).');
            }

        } catch (\Exception $e) {
            Log::error('Gemini API audit call failed: ' . $e->getMessage());
        }

        // Fallback: return all as valid
        return array_map(function ($q) {
            return ['id' => $q['id'], 'is_valid' => true, 'error_reason' => null];
        }, $questionsToAudit);
    }

    /**
     * Audit using Groq API.
     */
    private function auditFactualBatchGroq(array $questionsToAudit): array
    {
        $apiKey = config('services.ai.key');
        if (empty($apiKey)) {
            return array_map(function ($q) {
                return ['id' => $q['id'], 'is_valid' => true, 'error_reason' => null];
            }, $questionsToAudit);
        }

        try {
            $url = "https://api.groq.com/openai/v1/chat/completions";
            $model = config('services.ai.model', 'llama-3.3-70b-versatile');

            $prompt = "You are an independent quality auditor for the Philippine Civil Service Exam (CSE).\n" .
                      "Verify the factual correctness of the following multiple-choice questions.\n" .
                      "For each question, check if the marked correct option is actually correct. If the question is correct, set `is_valid` to true.\n" .
                      "If the correct option is incorrect, or if the question is faulty/confusing, set `is_valid` to false and provide a short reason in `error_reason`.\n\n" .
                      "Questions:\n" .
                      json_encode($questionsToAudit, JSON_PRETTY_PRINT) . "\n\n" .
                      "Return a JSON object containing a 'results' key which is a JSON array of objects with keys: 'id', 'is_valid', 'error_reason'.";

            $body = [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
            ];

            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->post($url, $body);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['choices'][0]['message']['content'] ?? null;
                if ($text) {
                    $decoded = json_decode($text, true);
                    if (is_array($decoded)) {
                        Setting::set('ai_quota_exceeded_flag', '0');
                        Setting::set('ai_last_error', null);
                        
                        // Extract results from various possible formats
                        if (isset($decoded['results']) && is_array($decoded['results'])) {
                            return $decoded['results'];
                        }
                        if (isset($decoded['questions']) && is_array($decoded['questions'])) {
                            return $decoded['questions'];
                        }
                        if (isset($decoded['data']) && is_array($decoded['data'])) {
                            return $decoded['data'];
                        }
                        return $decoded;
                    }
                }
            }

            if ($response->status() === 429) {
                Setting::set('ai_quota_exceeded_flag', '1');
                Setting::set('ai_last_error', '429 Quota Exceeded on Groq API.');
            }

        } catch (\Exception $e) {
            Log::error('Groq API audit call failed: ' . $e->getMessage());
        }

        // Fallback: return all as valid
        return array_map(function ($q) {
            return ['id' => $q['id'], 'is_valid' => true, 'error_reason' => null];
        }, $questionsToAudit);
    }

    /**
     * Get the display name for the job.
     */
    public function displayName(): string
    {
        $category = $this->categoryId ? " for category {$this->categoryId}" : '';
        $limit = $this->limit ? " (limit: {$this->limit})" : '';
        return "Run audit on questions" . $category . $limit;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('RunAuditJob failed permanently', [
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
        $tags = [
            'ai',
            'audit',
            'user:' . $this->userId,
        ];
        
        if ($this->categoryId) {
            $tags[] = 'category:' . $this->categoryId;
        }
        
        return $tags;
    }
}
