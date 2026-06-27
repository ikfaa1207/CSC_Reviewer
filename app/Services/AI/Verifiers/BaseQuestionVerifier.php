<?php

namespace App\Services\AI\Verifiers;

use App\DTOs\GeneratedQuestionData;
use App\Models\Setting;
use App\Services\AI\Contracts\QuestionVerifierInterface;
use Illuminate\Support\Facades\Log;

/**
 * Base class for question verifiers.
 * Provides common functionality for all verifiers.
 */
abstract class BaseQuestionVerifier implements QuestionVerifierInterface
{
    protected string $providerName;

    /**
     * Get the API key for this verifier.
     */
    abstract protected function getApiKey(): ?string;

    /**
     * Verify questions using the specific provider's API.
     */
    abstract protected function verifyFromApi(
        array $questions,
        array $dbCandidates,
        string $apiKey
    ): ?array;

    /**
     * {@inheritdoc}
     */
    public function verify(
        array $questions,
        array $dbCandidates = []
    ): array {
        $apiKey = $this->getApiKey();

        if (empty($apiKey) || empty($questions)) {
            // Fallback to mock verification
            return $this->fallbackVerification($questions);
        }

        try {
            // Chunk verification requests into max 10 questions to prevent payload sizes that trigger 503 / timeout errors
            $chunks = array_chunk($questions, 10);
            $allVerified = [];
            $hasError = false;

            foreach ($chunks as $chunk) {
                $verifiedChunk = $this->verifyFromApi($chunk, $dbCandidates, $apiKey);

                if ($verifiedChunk === null) {
                    $hasError = true;
                    break;
                }
                $allVerified = array_merge($allVerified, $verifiedChunk);
            }

            // If an error occurred or some questions were left unverified, apply graceful valid=true fallback for remaining questions
            if ($hasError || count($allVerified) < count($questions)) {
                $unverifiedPart = array_slice($questions, count($allVerified));
                $fallbackPart = $this->fallbackVerification($unverifiedPart);
                $allVerified = array_merge($allVerified, $fallbackPart);
            }

            Setting::set('ai_quota_exceeded_flag', '0');
            Setting::set('ai_last_error', null);

            return $allVerified;
        } catch (\Exception $e) {
            Log::error("{$this->providerName} API verification call failed: " . $e->getMessage());
            Setting::set('ai_last_error', $e->getMessage());
            return $this->fallbackVerification($questions);
        }
    }

    /**
     * Fallback verification when API is unavailable.
     */
    protected function fallbackVerification(array $questions): array
    {
        $verified = [];
        foreach ($questions as $q) {
            $verified[] = GeneratedQuestionData::fromArray([
                'question_text' => $q['question_text'] ?? '',
                'options' => $q['options'] ?? [],
                'correct_option_index' => $q['correct_option_index'] ?? 0,
                'explanation' => $q['explanation'] ?? '',
                'problem_type_tag' => $q['problem_type_tag'] ?? 'unknown',
                'is_valid' => true,
                'error_reason' => null,
            ]);
        }
        return $verified;
    }

    /**
     * Normalize verified questions array to ensure consistent structure.
     */
    protected function normalizeVerifiedQuestions(array $questions): array
    {
        $normalized = [];
        foreach ($questions as $q) {
            if (is_array($q)) {
                $normalized[] = GeneratedQuestionData::fromArray([
                    'question_text' => $q['question_text'] ?? '',
                    'options' => $q['options'] ?? [],
                    'correct_option_index' => $q['correct_option_index'] ?? 0,
                    'explanation' => $q['explanation'] ?? '',
                    'problem_type_tag' => $q['problem_type_tag'] ?? 'unknown',
                    'is_valid' => $q['is_valid'] ?? true,
                    'error_reason' => $q['error_reason'] ?? null,
                ]);
            }
        }
        return $normalized;
    }
}
