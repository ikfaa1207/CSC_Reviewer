<?php

namespace App\Services\AI\Verifiers;

use App\DTOs\GeneratedQuestionData;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Groq API question verifier.
 * Verifies the factual correctness and quality of generated questions.
 */
class GroqQuestionVerifier extends BaseQuestionVerifier
{
    protected string $providerName = 'Groq';
    
    private string $model;
    private string $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';

    /**
     * Create a new Groq verifier instance.
     */
    public function __construct(string $model = null)
    {
        $this->model = $model ?? config('services.ai.model', 'llama-3.3-70b-versatile');
    }

    /**
     * {@inheritdoc}
     */
    protected function getApiKey(): ?string
    {
        return config('services.ai.key');
    }

    /**
     * {@inheritdoc}
     */
    protected function verifyFromApi(
        array $questions,
        array $dbCandidates,
        string $apiKey
    ): ?array {
        try {
            $prompt = $this->buildVerificationPrompt($questions, $dbCandidates);
            
            $body = [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'response_format' => [
                    'type' => 'json_object'
                ],
                'temperature' => 0.3,
                'max_tokens' => 4096,
            ];

            $response = Http::withoutVerifying()
                ->timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey
                ])
                ->post($this->apiUrl, $body);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['choices'][0]['message']['content'] ?? null;
                
                if ($text) {
                    $decoded = json_decode($text, true);
                    
                    if (is_array($decoded)) {
                        Setting::set('ai_quota_exceeded_flag', '0');
                        Setting::set('ai_last_error', null);
                        
                        // Extract questions from various possible response formats
                        $questions = $this->extractQuestionsFromResponse($decoded);
                        
                        // Convert to GeneratedQuestionData objects
                        return $this->convertToGeneratedQuestionData($questions);
                    }
                }
            }

            // Handle errors
            if ($response->status() === 429) {
                Setting::set('ai_quota_exceeded_flag', '1');
                Setting::set('ai_last_error', '429 Quota Exceeded on Groq API.');
            } else if ($response->failed()) {
                Setting::set('ai_last_error', 'Groq API verification call failed with status ' . $response->status() . ': ' . $response->body());
            }

            Log::warning('Groq API verification response format was invalid: ' . ($response->body() ?? 'Empty response'));
            return null;
            
        } catch (\Exception $e) {
            Log::error('Groq API verification failed: ' . $e->getMessage());
            Setting::set('ai_last_error', 'Groq API verification failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract questions from various response formats.
     */
    private function extractQuestionsFromResponse(array $decoded): array
    {
        // Check for 'questions' key
        if (isset($decoded['questions']) && is_array($decoded['questions'])) {
            return $decoded['questions'];
        }
        
        // Check for 'data' key
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }
        
        // Check if it's a numeric array
        if (array_keys($decoded) === range(0, count($decoded) - 1)) {
            return $decoded;
        }
        
        // Check for nested structure
        if (isset($decoded['result']) && is_array($decoded['result'])) {
            return $decoded['result'];
        }
        
        // Last resort: return as-is
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build the verification prompt for Groq.
     */
    private function buildVerificationPrompt(array $questions, array $dbCandidates): string
    {
        $candidatesText = '';
        if (!empty($dbCandidates)) {
            $candidatesText = "\n\nEXISTING DATABASE CANDIDATES (For Semantic Duplicate Audit):\n" .
                             json_encode($dbCandidates, JSON_PRETTY_PRINT) . "\n";
        }

        $prompt = "You are an expert reviewer and quality auditor for the Philippine Civil Service Exam (CSE).\n" .
                  "Review the following JSON list of newly generated multiple-choice questions for structural accuracy, factual correctness, and semantic duplication against similar existing questions in our database.\n" .
                  $candidatesText . "\n" .
                  "QUESTIONS TO AUDIT & VERIFY:\n" .
                  json_encode($questions, JSON_PRETTY_PRINT) . "\n\n" .
                  "VERIFICATION RULES & CRITERIA:\n" .
                  "1. Structural Audit: Ensure the question has exactly 4 option choices and a valid correct_option_index (0-indexed integer from 0 to 3).\n" .
                  "2. Factual Audit: Verify that the correct option index points to the factually correct answer and the explanation aligns with the answer.\n" .
                  "3. CRITICAL SEMANTIC DEDUPLICATION AUDIT: A generated question is a semantic duplicate if it tests the exact same mathematical formula, logic pattern, or scenario type as any question in the EXISTING DATABASE CANDIDATES list, even if names, places, or numbers are changed. E.g., 'Juan travels 60km in 2 hrs' vs 'Maria drives 120km in 4 hrs' are semantic duplicates.\n" .
                  "4. If a question is factually incorrect, structurally invalid, or is a semantic duplicate of an existing database question, set `is_valid` to false and provide a brief explanation in `error_reason` (e.g. 'Semantic duplicate of DB question #42 - Speed rate scenario').\n" .
                  "5. Otherwise, set `is_valid` to true and `error_reason` to null.\n\n" .
                  "Return the verified questions as a JSON object containing a 'questions' key which is an array of objects matching the required schema. Ensure you retain the 'problem_type_tag' of each question in the output.";

        return $prompt;
    }

    /**
     * Convert API response to GeneratedQuestionData objects.
     */
    private function convertToGeneratedQuestionData(array $questions): array
    {
        $result = [];
        
        foreach ($questions as $q) {
            try {
                // Handle both array and object formats
                if (!is_array($q)) {
                    continue;
                }

                $result[] = GeneratedQuestionData::fromArray([
                    'question_text' => $q['question_text'] ?? '',
                    'options' => $q['options'] ?? [],
                    'correct_option_index' => $q['correct_option_index'] ?? 0,
                    'explanation' => $q['explanation'] ?? '',
                    'problem_type_tag' => $q['problem_type_tag'] ?? 'unknown',
                    'is_valid' => $q['is_valid'] ?? true,
                    'error_reason' => $q['error_reason'] ?? null,
                ]);
            } catch (\Exception $e) {
                Log::warning('Error converting verified question to DTO: ' . $e->getMessage());
                continue;
            }
        }
        
        return $result;
    }

    /**
     * Set the model to use.
     */
    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Get the current model.
     */
    public function getModel(): string
    {
        return $this->model;
    }
}
