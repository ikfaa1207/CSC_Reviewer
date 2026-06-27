<?php

namespace App\Services\AI;

/**
 * Contains syllabus guidelines for each exam category and level.
 * Used by question generators to ensure questions align with CSE standards.
 */
class QuestionSyllabus
{
    private const SYLLABUS = [
        'Numerical Ability' => [
            'professional' => [
                'Advanced mathematics',
                'Word problems involving fractions, decimals, percentages',
                'Sequence completion (e.g., sequences like \'10 17 26 37\')',
                'Averages',
                'Age word problems',
                'Distance/speed/time',
                'Perimeter/area/volume',
                'Simple/compound interest',
                'Investment returns/bonds',
                'Basic algebra equations',
                'Data Sufficiency problems',
            ],
            'sub_professional' => [
                'Basic arithmetic operations',
                'Fractions',
                'Basic percentages',
                'Averages',
                'Rates',
                'Simple word problems (e.g., salary increase rate, price discounts)',
            ],
        ],
        'Verbal Ability' => [
            'professional' => [
                'Advanced grammar rules',
                'Extensive vocabulary (synonyms and antonyms)',
                'Analogies (both single-word and double-word)',
                'Correct usage',
                'Identifying errors',
                'Paragraph organization',
                'Reading comprehension of formal texts',
            ],
            'sub_professional' => [
                'Basic grammar rules',
                'Spelling identification (e.g., accommodation vs. accomodation)',
                'Simple vocabulary',
                'Analogies (single-word)',
                'Correct usage',
                'Paragraph organization',
                'General reading comprehension',
            ],
        ],
        'Analytical Ability' => [
            'professional' => [
                'Logical reasoning (syllogisms)',
                'Drawing valid conclusions from statements',
                'Identifying assumptions',
                'Word associations',
                'Inductive/sequence completion of number or letter series',
            ],
            'sub_professional' => [],
        ],
        'Clerical Ability' => [
            'sub_professional' => [
                'Clerical filing procedures (alphabetical ordering)',
                'English spelling rules',
                'Clerical tasks',
                'Coding',
                'Data verification',
            ],
            'professional' => [],
        ],
        'General Information' => [
            'both' => [
                '1987 Philippine Constitution (especially Article III Bill of Rights)',
                'R.A. 6713 (Code of Conduct and Ethical Standards for Public Officials and Employees)',
                'Peace & Human Rights',
                'Environmental Concepts (climate change, resource preservation)',
            ],
        ],
    ];

    /**
     * Get the syllabus guideline for a category and level.
     */
    public static function getGuideline(string $categoryName, string $level): string
    {
        $levelKey = $level === 'professional' ? 'professional' : 'sub_professional';
        
        if (isset(self::SYLLABUS[$categoryName][$levelKey])) {
            return implode(', ', self::SYLLABUS[$categoryName][$levelKey]);
        }
        
        if (isset(self::SYLLABUS[$categoryName]['both'])) {
            return implode(', ', self::SYLLABUS[$categoryName]['both']);
        }
        
        // Fallback for unknown categories
        return 'Test knowledge/skills relevant to the Philippine Civil Service Exam.';
    }

    /**
     * Get formatted level name.
     */
    public static function getFormattedLevel(string $level): string
    {
        return $level === 'professional' ? 'Professional' : 'Sub-Professional';
    }

    /**
     * Check if a category is valid for a level.
     */
    public static function isValidForLevel(string $categoryName, string $level): bool
    {
        $levelKey = $level === 'professional' ? 'professional' : 'sub_professional';
        
        if (isset(self::SYLLABUS[$categoryName][$levelKey])) {
            return true;
        }
        
        if (isset(self::SYLLABUS[$categoryName]['both'])) {
            return true;
        }
        
        // Analytical Ability is only for Professional
        if ($categoryName === 'Analytical Ability' && $level === 'professional') {
            return true;
        }
        
        // Clerical Ability is only for Sub-Professional
        if ($categoryName === 'Clerical Ability' && $level === 'sub_professional') {
            return true;
        }
        
        return false;
    }
}
