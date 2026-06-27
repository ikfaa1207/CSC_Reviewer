<?php

namespace App\DTOs;

/**
 * Extended DTO for questions with validation metadata.
 * Used for questions that have been verified by the AI verifier.
 */
readonly class GeneratedQuestionData extends QuestionData
{
    public function __construct(
        string $question_text,
        array $options,
        int $correct_option_index,
        string $explanation,
        string $problem_type_tag,
        public bool $is_valid,
        public ?string $error_reason,
        ?string $variation_id = null,
    ) {
        parent::__construct($question_text, $options, $correct_option_index, $explanation, $problem_type_tag, $variation_id);
    }

    /**
     * Create from a QuestionData with validation results.
     */
    public static function fromQuestionData(QuestionData $question, bool $is_valid, ?string $error_reason): self
    {
        return new self(
            question_text: $question->question_text,
            options: $question->options,
            correct_option_index: $question->correct_option_index,
            explanation: $question->explanation,
            problem_type_tag: $question->problem_type_tag,
            is_valid: $is_valid,
            error_reason: $error_reason,
            variation_id: $question->variation_id,
        );
    }

    /**
     * Create from an array (e.g., from verification API response).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            question_text: $data['question_text'] ?? '',
            options: $data['options'] ?? [],
            correct_option_index: $data['correct_option_index'] ?? 0,
            explanation: $data['explanation'] ?? '',
            problem_type_tag: $data['problem_type_tag'] ?? 'unknown',
            is_valid: $data['is_valid'] ?? true,
            error_reason: $data['error_reason'] ?? null,
            variation_id: $data['variation_id'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'is_valid' => $this->is_valid,
            'error_reason' => $this->error_reason,
        ]);
    }

    /**
     * Check if this question passed verification.
     */
    public function passedVerification(): bool
    {
        return $this->is_valid && $this->isStructurallyValid();
    }
}
