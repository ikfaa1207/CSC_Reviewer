<?php

namespace App\DTOs;

/**
 * Data Transfer Object for a single question.
 * Used for type safety when passing question data between services.
 */
readonly class QuestionData
{
    public function __construct(
        public string $question_text,
        public array $options,
        public int $correct_option_index,
        public string $explanation,
        public string $problem_type_tag,
        public ?string $variation_id = null,
    ) {
        // Validate structure
        if (count($options) !== 4) {
            throw new \InvalidArgumentException('A question must have exactly 4 options.');
        }
        if ($correct_option_index < 0 || $correct_option_index > 3) {
            throw new \InvalidArgumentException('correct_option_index must be between 0 and 3.');
        }
    }

    /**
     * Create from an array (e.g., from API response).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            question_text: $data['question_text'] ?? '',
            options: $data['options'] ?? [],
            correct_option_index: $data['correct_option_index'] ?? 0,
            explanation: $data['explanation'] ?? '',
            problem_type_tag: $data['problem_type_tag'] ?? 'unknown',
            variation_id: $data['variation_id'] ?? null,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        $array = [
            'question_text' => $this->question_text,
            'options' => $this->options,
            'correct_option_index' => $this->correct_option_index,
            'explanation' => $this->explanation,
            'problem_type_tag' => $this->problem_type_tag,
        ];
        if ($this->variation_id) {
            $array['variation_id'] = $this->variation_id;
        }
        return $array;
    }

    /**
     * Check if this question is valid (structural check).
     */
    public function isStructurallyValid(): bool
    {
        return count($this->options) === 4
            && $this->correct_option_index >= 0
            && $this->correct_option_index < 4
            && !empty($this->question_text)
            && !empty($this->explanation);
    }
}
