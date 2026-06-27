<?php

namespace App\Services\AI\Contracts;

use App\DTOs\QuestionData;

/**
 * Interface for question generation services.
 * Implementations generate questions for the Civil Service Exam.
 */
interface QuestionGeneratorInterface
{
    /**
     * Generate a batch of questions.
     *
     * @param string $categoryName The exam category (e.g., 'Numerical Ability')
     * @param string $level The exam level ('professional' or 'sub_professional')
     * @param int $count Number of questions to generate
     * @param int $seedOffset Offset for random seed (for uniqueness)
     * @param array $excludeTexts Texts to exclude (for duplicate prevention)
     * @return array<QuestionData> Generated questions
     */
    public function generate(
        string $categoryName,
        string $level,
        int $count,
        int $seedOffset = 0,
        array $excludeTexts = []
    ): array;
}
