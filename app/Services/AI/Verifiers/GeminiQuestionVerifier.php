<?php

namespace App\Services\AI\Verifiers;

use App\DTOs\GeneratedQuestionData;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini API question verifier.
 * Verifies the factual correctness and quality of generated questions.
 */
class GeminiQuestionVerifier extends BaseQuestionVerifier
{
    protected string $providerName = 'Gemini';
    
    private string $model = 'gemini-2.5-flash';
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent';

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
            $url = str_replace('{model}', $this->model, $this->apiUrl) . '?key=' . $apiKey;
            
            $prompt = $this->buildVerificationPrompt($questions, $dbCandidates);
            
            $body = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'ARRAY',
                        'description' => 'A list of verified questions',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'question_text' => ['type' => 'STRING'],
                                'options' => [
                                    'type' => 'ARRAY',
                                    'items' => ['type' => 'STRING']
                                ],
                                'correct_option_index' => ['type' => 'INTEGER'],
                                'explanation' => ['type' => 'STRING'],
                                'problem_type_tag' => ['type' => 'STRING'],
                                'is_valid' => ['type' => 'BOOLEAN'],
                                'error_reason' => ['type' => 'STRING']
                            ],
                            'required' => ['question_text', 'options', 'correct_option_index', 'explanation', 'problem_type_tag', 'is_valid']
                        ]
                    ]
                ]
            ];

            $response = Http::withoutVerifying()
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
                        
                        // Convert to GeneratedQuestionData objects
                        return $this->convertToGeneratedQuestionData($decoded);
                    }
                }
            }

            // Handle errors
            if ($response->status() === 429) {
                Setting::set('ai_quota_exceeded_flag', '1');
                Setting::set('ai_last_error', '429 Quota Exceeded (Free Tier limit met or billing issue).');
            } else if ($response->failed()) {
                Setting::set('ai_last_error', 'Gemini API verification call failed with status ' . $response->status());
            }

            Log::warning('Gemini API verification response format was invalid: ' . ($response->body() ?? 'Empty response'));
            return null;
            
        } catch (\Exception $e) {
            Log::error('Gemini API verification failed: ' . $e->getMessage());
            Setting::set('ai_last_error', 'Gemini API verification failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Build the verification prompt for Gemini.
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
                  "Return the verified questions as a JSON array of objects matching the required schema. Ensure you retain the 'problem_type_tag' of each question in the output.";

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
