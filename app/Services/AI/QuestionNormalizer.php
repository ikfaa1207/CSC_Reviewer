<?php

namespace App\Services\AI;

/**
 * Utility class for normalizing question text for duplicate detection.
 */
class QuestionNormalizer
{
    /**
     * Normalize question text for duplicate detection.
     * Strips Variation ID tag, removes punctuation, collapses whitespace, lowercases.
     */
    public static function normalize(string $text): string
    {
        // Strip "(Variation ID: N)" tags
        $text = preg_replace('/\s*\(Variation ID:\s*\d+\)/i', '', $text);
        // Remove punctuation (keep alphanumeric and spaces)
        $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
        // Collapse multiple whitespace into single space and trim
        $text = trim(preg_replace('/\s+/', ' ', $text));
        // Lowercase
        return strtolower($text);
    }

    /**
     * Compute a 32-char MD5 hash from normalized question text.
     */
    public static function computeHash(string $text): string
    {
        return md5(self::normalize($text));
    }

    /**
     * Extract the problem type tag from a question array.
     */
    public static function extractProblemTypeTag(array $question): string
    {
        return $question['problem_type_tag'] ?? 'unknown';
    }

    /**
     * Extract the variation ID from question text.
     */
    public static function extractVariationId(string $text): ?string
    {
        if (preg_match('/\(Variation ID:\s*(\d+)\)/i', $text, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Remove variation ID from question text.
     */
    public static function removeVariationId(string $text): string
    {
        return preg_replace('/\s*\(Variation ID:\s*\d+\)/i', '', $text);
    }
}
