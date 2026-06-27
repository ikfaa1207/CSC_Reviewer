<?php

namespace App\Services\AI;

use App\DTOs\GeneratedQuestionData;
use App\Enums\AIProvider;
use App\Models\Setting;
use App\Services\AI\Contracts\AIServiceInterface;
use App\Services\AI\Contracts\QuestionGeneratorInterface;
use App\Services\AI\Contracts\QuestionVerifierInterface;
use App\Services\AI\Generators\GeminiQuestionGenerator;
use App\Services\AI\Generators\GroqQuestionGenerator;
use App\Services\AI\Generators\MockQuestionGenerator;
use App\Services\AI\Verifiers\GeminiQuestionVerifier;
use App\Services\AI\Verifiers\GroqQuestionVerifier;
use Illuminate\Support\Facades\Log;

/**
 * Main AI Service for the CSC Reviewer.
 * Coordinates question generation and verification using the configured provider.
 * 
 * This class acts as a facade, delegating to specific generator and verifier implementations.
 */
class AIService implements AIServiceInterface
{
    private ?QuestionGeneratorInterface $generator = null;
    private ?QuestionVerifierInterface $verifier = null;
    private string $provider;

    /**
     * Create a new AIService instance.
     * 
     * @param QuestionGeneratorInterface|null $generator Custom generator (for testing)
     * @param QuestionVerifierInterface|null $verifier Custom verifier (for testing)
     */
    public function __construct(
        ?QuestionGeneratorInterface $generator = null,
        ?QuestionVerifierInterface $verifier = null
    ) {
        $this->provider = config('services.ai.provider', 'gemini');
        $this->generator = $generator;
        $this->verifier = $verifier;
    }

    /**
     * {@inheritdoc}
     */
    public function generateQuestions(
        string $categoryName,
        string $level,
        int $count,
        int $seedOffset = 0,
        array $extraExcludeTexts = []
    ): array {
        try {
            $generator = $this->getGenerator();
            $questions = $generator->generate(
                $categoryName,
                $level,
                $count,
                $seedOffset,
                $extraExcludeTexts
            );

            // Convert to GeneratedQuestionData with default validation
            return array_map(function ($q) {
                return GeneratedQuestionData::fromArray([
                    'question_text' => $q['question_text'] ?? '',
                    'options' => $q['options'] ?? [],
                    'correct_option_index' => $q['correct_option_index'] ?? 0,
                    'explanation' => $q['explanation'] ?? '',
                    'problem_type_tag' => $q['problem_type_tag'] ?? 'unknown',
                    'is_valid' => true,
                    'error_reason' => null,
                ]);
            }, $questions);
        } catch (\Exception $e) {
            Log::error('AIService generateQuestions failed: ' . $e->getMessage());
            // Return mock questions as fallback
            $mockGenerator = new MockQuestionGenerator();
            $questions = $mockGenerator->generate($categoryName, $level, $count, $seedOffset, $extraExcludeTexts);
            return array_map(function ($q) {
                return GeneratedQuestionData::fromArray([
                    'question_text' => $q['question_text'] ?? '',
                    'options' => $q['options'] ?? [],
                    'correct_option_index' => $q['correct_option_index'] ?? 0,
                    'explanation' => $q['explanation'] ?? '',
                    'problem_type_tag' => $q['problem_type_tag'] ?? 'unknown',
                    'is_valid' => true,
                    'error_reason' => 'Fallback to mock generator: ' . $e->getMessage(),
                ]);
            }, $questions);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verifyQuestions(
        array $questions,
        array $dbCandidates = []
    ): array {
        try {
            $verifier = $this->getVerifier();
            return $verifier->verify($questions, $dbCandidates);
        } catch (\Exception $e) {
            Log::error('AIService verifyQuestions failed: ' . $e->getMessage());
            // Return questions marked as valid (fallback)
            return array_map(function ($q) {
                if ($q instanceof GeneratedQuestionData) {
                    return $q;
                }
                return GeneratedQuestionData::fromArray([
                    'question_text' => $q['question_text'] ?? '',
                    'options' => $q['options'] ?? [],
                    'correct_option_index' => $q['correct_option_index'] ?? 0,
                    'explanation' => $q['explanation'] ?? '',
                    'problem_type_tag' => $q['problem_type_tag'] ?? 'unknown',
                    'is_valid' => true,
                    'error_reason' => 'Verification fallback: ' . $e->getMessage(),
                ]);
            }, $questions);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool
    {
        $apiKey = config('services.ai.key');
        return !empty($apiKey);
    }

    /**
     * Get the appropriate generator based on configuration.
     */
    private function getGenerator(): QuestionGeneratorInterface
    {
        if ($this->generator) {
            return $this->generator;
        }

        $provider = AIProvider::fromString($this->provider);

        return match ($provider) {
            AIProvider::GEMINI => new GeminiQuestionGenerator(),
            AIProvider::GROQ => new GroqQuestionGenerator(),
            AIProvider::MOCK => new MockQuestionGenerator(),
        };
    }

    /**
     * Get the appropriate verifier based on configuration.
     */
    private function getVerifier(): QuestionVerifierInterface
    {
        if ($this->verifier) {
            return $this->verifier;
        }

        $provider = AIProvider::fromString($this->provider);

        return match ($provider) {
            AIProvider::GEMINI => new GeminiQuestionVerifier(),
            AIProvider::GROQ => new GroqQuestionVerifier(),
            AIProvider::MOCK => new class extends BaseQuestionVerifier {
                protected string $providerName = 'Mock';
                
                protected function getApiKey(): ?string {
                    return null;
                }
                
                protected function verifyFromApi(array $questions, array $dbCandidates, string $apiKey): ?array {
                    return null; // Always use fallback
                }
            },
        };
    }

    /**
     * Normalize questions array to ensure consistent structure.
     * This is a helper method for backward compatibility.
     */
    public static function normalizeQuestionsArray(array $questions): array
    {
        $normalized = [];
        foreach ($questions as $q) {
            if (is_array($q)) {
                $normalized[] = [
                    'question_text' => $q['question_text'] ?? '',
                    'options' => $q['options'] ?? [],
                    'correct_option_index' => $q['correct_option_index'] ?? 0,
                    'explanation' => $q['explanation'] ?? '',
                    'problem_type_tag' => $q['problem_type_tag'] ?? 'unknown',
                ];
            }
        }
        return $normalized;
    }

    /**
     * Get a specific generator instance by provider name.
     */
    public static function getGeneratorByProvider(string $providerName): QuestionGeneratorInterface
    {
        $provider = AIProvider::fromString($providerName);

        return match ($provider) {
            AIProvider::GEMINI => new GeminiQuestionGenerator(),
            AIProvider::GROQ => new GroqQuestionGenerator(),
            AIProvider::MOCK => new MockQuestionGenerator(),
        };
    }

    /**
     * Get a specific verifier instance by provider name.
     */
    public static function getVerifierByProvider(string $providerName): QuestionVerifierInterface
    {
        $provider = AIProvider::fromString($providerName);

        return match ($provider) {
            AIProvider::GEMINI => new GeminiQuestionVerifier(),
            AIProvider::GROQ => new GroqQuestionVerifier(),
            AIProvider::MOCK => new class extends BaseQuestionVerifier {
                protected string $providerName = 'Mock';
                
                protected function getApiKey(): ?string {
                    return null;
                }
                
                protected function verifyFromApi(array $questions, array $dbCandidates, string $apiKey): ?array {
                    return null;
                }
            },
        };
    }
}
