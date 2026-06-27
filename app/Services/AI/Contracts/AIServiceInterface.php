<?php

namespace App\Services\AI\Contracts;

use App\DTOs\QuestionData;
use App\DTOs\GeneratedQuestionData;

/**
 * Main interface for AI services.
 * Combines generation and verification capabilities.
 */
interface AIServiceInterface
{
    /**
     * Generate questions using the configured provider.
     */
    public function generateQuestions(
        string $categoryName,
        string $level,
        int $count,
        int $seedOffset = 0,
        array $extraExcludeTexts = []
    ): array;

    /**
     * Verify questions using the configured provider.
     */
    public function verifyQuestions(
        array $questions,
        array $dbCandidates = []
    ): array;

    /**
     * Get the current AI provider.
     */
    public function getProvider(): string;

    /**
     * Check if the AI service is configured (has API key).
     */
    public function isConfigured(): bool;
}
