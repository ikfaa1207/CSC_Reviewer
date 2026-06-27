<?php

namespace App\Services\AI\Contracts;

use App\DTOs\GeneratedQuestionData;

/**
 * Interface for question verification services.
 * Implementations verify the factual correctness and quality of generated questions.
 */
interface QuestionVerifierInterface
{
    /**
     * Verify a batch of questions.
     *
     * @param array $questions Array of question data to verify
     * @param array $dbCandidates Existing questions from the database for duplicate checking
     * @return array<GeneratedQuestionData> Verified questions with validation metadata
     */
    public function verify(
        array $questions,
        array $dbCandidates = []
    ): array;
}
