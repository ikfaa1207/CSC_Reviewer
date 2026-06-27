<?php

namespace App\Services\AI;

use App\DTOs\QuestionData;
use App\DTOs\GeneratedQuestionData;
use App\Models\Question;
use Illuminate\Support\Facades\Log;

/**
 * Validates and improves question output.
 * Ensures questions meet CSE standards before being saved.
 */
class QuestionValidator
{
    /**
     * Validate a single question structure.
     */
    public static function validateQuestion(array $question): array
    {
        $errors = [];
        $warnings = [];

        // Check required fields
        if (empty($question['question_text'])) {
            $errors[] = 'Question text is empty';
        }

        // Check options
        if (!isset($question['options']) || !is_array($question['options'])) {
            $errors[] = 'Options must be an array';
        } elseif (count($question['options']) !== 4) {
            $errors[] = 'Must have exactly 4 options (found: ' . count($question['options']) . ')';
        }

        // Check correct_option_index
        if (!isset($question['correct_option_index'])) {
            $errors[] = 'correct_option_index is required';
        } elseif (!is_int($question['correct_option_index'])) {
            $errors[] = 'correct_option_index must be an integer';
        } elseif ($question['correct_option_index'] < 0 || $question['correct_option_index'] > 3) {
            $errors[] = 'correct_option_index must be between 0 and 3';
        }

        // Check explanation
        if (empty($question['explanation'])) {
            $warnings[] = 'Explanation is empty';
        }

        // Check problem_type_tag
        if (empty($question['problem_type_tag'])) {
            $warnings[] = 'problem_type_tag is empty';
        }

        // Check for duplicate options
        if (isset($question['options']) && is_array($question['options'])) {
            $uniqueOptions = array_unique($question['options']);
            if (count($uniqueOptions) !== count($question['options'])) {
                $errors[] = 'Duplicate options found';
            }
        }

        // Check for empty options
        if (isset($question['options']) && is_array($question['options'])) {
            foreach ($question['options'] as $index => $option) {
                if (empty(trim($option))) {
                    $errors[] = "Option {$index} is empty";
                }
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate a batch of questions.
     */
    public static function validateBatch(array $questions): array
    {
        $valid = [];
        $invalid = [];
        $warnings = [];

        foreach ($questions as $index => $question) {
            $validation = self::validateQuestion($question);
            
            if ($validation['is_valid']) {
                if (!empty($validation['warnings'])) {
                    $warnings[$index] = $validation['warnings'];
                }
                $valid[] = $question;
            } else {
                $invalid[$index] = [
                    'question' => $question,
                    'errors' => $validation['errors'],
                ];
            }
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'warnings' => $warnings,
            'valid_count' => count($valid),
            'invalid_count' => count($invalid),
        ];
    }

    /**
     * Improve question formatting.
     */
    public static function improveFormatting(array $question): array
    {
        $improved = $question;

        // Clean question text
        $improved['question_text'] = self::cleanText($question['question_text'] ?? '');

        // Clean options
        if (isset($improved['options']) && is_array($improved['options'])) {
            $improved['options'] = array_map([self, 'cleanText'], $improved['options']);
        }

        // Clean explanation
        $improved['explanation'] = self::cleanText($question['explanation'] ?? '');

        // Clean problem_type_tag
        $improved['problem_type_tag'] = self::cleanTag($question['problem_type_tag'] ?? 'unknown');

        // Remove variation ID from question text
        $improved['question_text'] = QuestionNormalizer::removeVariationId($improved['question_text']);

        // Ensure correct_option_index is an integer
        if (isset($improved['correct_option_index'])) {
            $improved['correct_option_index'] = (int) $improved['correct_option_index'];
        }

        return $improved;
    }

    /**
     * Improve a batch of questions.
     */
    public static function improveBatch(array $questions): array
    {
        return array_map([self, 'improveFormatting'], $questions);
    }

    /**
     * Check for semantic duplicates in a batch.
     */
    public static function checkSemanticDuplicates(array $questions, array $existingQuestions = []): array
    {
        $duplicates = [];
        $seenHashes = [];
        $seenNormalized = [];

        // Add existing questions to seen lists
        foreach ($existingQuestions as $eq) {
            if (isset($eq['question_text'])) {
                $hash = Question::computeHash($eq['question_text']);
                $normalized = QuestionNormalizer::normalize($eq['question_text']);
                $seenHashes[$hash] = true;
                $seenNormalized[$normalized] = true;
            }
        }

        foreach ($questions as $index => $question) {
            if (empty($question['question_text'])) {
                continue;
            }

            $hash = Question::computeHash($question['question_text']);
            $normalized = QuestionNormalizer::normalize($question['question_text']);

            // Check for exact hash match
            if (isset($seenHashes[$hash])) {
                $duplicates[$index] = [
                    'type' => 'exact_duplicate',
                    'question_text' => $question['question_text'],
                    'message' => 'Exact duplicate found',
                ];
                continue;
            }

            // Check for semantic similarity (normalized text)
            foreach ($seenNormalized as $seenNorm => $true) {
                if (self::areSemanticallySimilar($normalized, $seenNorm)) {
                    $duplicates[$index] = [
                        'type' => 'semantic_duplicate',
                        'question_text' => $question['question_text'],
                        'similar_to' => $seenNorm,
                        'message' => 'Semantic duplicate found',
                    ];
                    break;
                }
            }

            // Add to seen lists
            $seenHashes[$hash] = true;
            $seenNormalized[$normalized] = true;
        }

        return $duplicates;
    }

    /**
     * Check if two normalized texts are semantically similar.
     */
    private static function areSemanticallySimilar(string $text1, string $text2): bool
    {
        // If texts are identical, they're similar
        if ($text1 === $text2) {
            return true;
        }

        // Check if one contains the other (with some tolerance)
        $words1 = explode(' ', $text1);
        $words2 = explode(' ', $text2);

        // If they share a significant number of words, they might be similar
        $commonWords = array_intersect($words1, $words2);
        $similarityRatio = count($commonWords) / max(count($words1), count($words2));

        // If more than 70% of words are the same, consider it a semantic duplicate
        return $similarityRatio > 0.7;
    }

    /**
     * Clean text by removing markdown and special formatting.
     */
    public static function cleanText(string $text): string
    {
        // Remove markdown bold/italic
        $text = preg_replace('/[\*_~`]/', '', $text);
        
        // Remove markdown headers
        $text = preg_replace('/^#+\s*/m', '', $text);
        
        // Remove markdown lists
        $text = preg_replace('/^[-*+]\s*/m', '', $text);
        $text = preg_replace('/^\d+\.\s*/m', '', $text);
        
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * Clean problem type tag.
     */
    public static function cleanTag(string $tag): string
    {
        // Remove special characters
        $tag = preg_replace('/[^a-zA-Z0-9\-_]/', '', $tag);
        
        // Ensure it's lowercase with hyphens
        $tag = strtolower($tag);
        $tag = preg_replace('/[\s_]+/', '-', $tag);
        
        return trim($tag, '-');
    }

    /**
     * Validate and improve a question.
     */
    public static function validateAndImprove(array $question): array
    {
        $validation = self::validateQuestion($question);
        
        if (!$validation['is_valid']) {
            return [
                'is_valid' => false,
                'errors' => $validation['errors'],
                'question' => null,
            ];
        }

        $improved = self::improveFormatting($question);

        return [
            'is_valid' => true,
            'errors' => [],
            'warnings' => $validation['warnings'],
            'question' => $improved,
        ];
    }

    /**
     * Validate, improve, and check for duplicates in a batch.
     */
    public static function validateImproveAndCheckDuplicates(
        array $questions,
        array $existingQuestions = []
    ): array {
        $results = [
            'valid' => [],
            'invalid' => [],
            'duplicates' => [],
            'warnings' => [],
        ];

        // First, validate and improve all questions
        foreach ($questions as $index => $question) {
            $validation = self::validateQuestion($question);
            
            if (!$validation['is_valid']) {
                $results['invalid'][$index] = [
                    'question' => $question,
                    'errors' => $validation['errors'],
                ];
                continue;
            }

            $improved = self::improveFormatting($question);
            $questions[$index] = $improved;
            
            if (!empty($validation['warnings'])) {
                $results['warnings'][$index] = $validation['warnings'];
            }
        }

        // Then check for duplicates among valid questions
        $validQuestions = array_filter($questions, function ($q) use ($questions) {
            $index = array_search($q, $questions, true);
            return !isset($results['invalid'][$index]);
        });

        $duplicates = self::checkSemanticDuplicates($validQuestions, $existingQuestions);

        // Separate valid questions into unique and duplicate
        foreach ($questions as $index => $question) {
            if (isset($results['invalid'][$index])) {
                continue;
            }

            if (isset($duplicates[$index])) {
                $results['duplicates'][$index] = [
                    'question' => $question,
                    'duplicate_info' => $duplicates[$index],
                ];
            } else {
                $results['valid'][] = $question;
            }
        }

        $results['valid_count'] = count($results['valid']);
        $results['invalid_count'] = count($results['invalid']);
        $results['duplicate_count'] = count($results['duplicates']);

        return $results;
    }
}
