<?php

namespace App\Services\AI\Generators;

use App\DTOs\QuestionData;
use App\Services\AI\QuestionNormalizer;

/**
 * Mock question generator for fallback when AI APIs are unavailable.
 * Generates deterministic questions based on seed values.
 */
class MockQuestionGenerator extends BaseQuestionGenerator
{
    protected string $providerName = 'Mock';

    /**
     * {@inheritdoc}
     */
    protected function getApiKey(): ?string
    {
        return null; // Mock generator never uses API
    }

    /**
     * {@inheritdoc}
     */
    protected function generateFromApi(
        string $categoryName,
        string $level,
        int $batchCount,
        string $apiKey,
        array $excludeTexts
    ): ?array {
        // Mock generator doesn't use API
        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function generateMock(
        string $categoryName,
        string $level,
        int $count,
        int $seedOffset
    ): array {
        $questionsList = [];

        for ($i = 0; $i < $count; $i++) {
            $questionsList[] = $this->getSingleMockQuestion($categoryName, $level, $i + $seedOffset);
        }

        return $questionsList;
    }

    /**
     * Get a single mock question with randomized values to guarantee uniqueness when bulk-generating.
     */
    private function getSingleMockQuestion(string $categoryName, string $level, int $seed): array
    {
        // Simple seed offset to select template
        $index = $categoryName === 'Analytical Ability' ? $seed % 3 : $seed % 2;

        if ($categoryName === 'Numerical Ability') {
            return $this->generateNumericalQuestion($level, $index, $seed);
        }

        if ($categoryName === 'Verbal Ability') {
            return $this->generateVerbalQuestion($level, $index, $seed);
        }

        if ($categoryName === 'Analytical Ability') {
            return $this->generateAnalyticalQuestion($index, $seed);
        }

        if ($categoryName === 'Clerical Ability') {
            return $this->generateClericalQuestion($index, $seed);
        }

        // General Information fallback
        return $this->generateGeneralInformationQuestion($index, $seed);
    }

    /**
     * Generate a mock Numerical Ability question.
     */
    private function generateNumericalQuestion(string $level, int $index, int $seed): array
    {
        if ($level === 'professional') {
            if ($index === 0) {
                // Oranges profit template
                $oranges = 100 + ($seed * 10);
                $costPerOrange = 5;
                $spoiledPercent = 10;
                $profitPercent = 20;

                $totalCost = $oranges * $costPerOrange;
                $targetRevenue = $totalCost * (1 + ($profitPercent / 100));
                $remainingOranges = $oranges * (1 - ($spoiledPercent / 100));
                $requiredPrice = round($targetRevenue / $remainingOranges, 2);

                return [
                    'question_text' => "A vendor bought " . number_format($oranges) . " oranges at ₱" . number_format($costPerOrange, 2) . " each. If {$spoiledPercent}% of the oranges got spoiled, at what price per orange must he sell the remaining ones to make a {$profitPercent}% profit on his overall cost?",
                    'options' => [
                        "₱" . number_format($requiredPrice, 2) . " per orange",
                        "₱" . number_format($requiredPrice - 0.5, 2) . " per orange",
                        "₱" . number_format($requiredPrice + 0.8, 2) . " per orange",
                        "₱" . number_format($requiredPrice * 0.9, 2) . " per orange",
                    ],
                    'correct_option_index' => 0,
                    'explanation' => "1. Calculate overall cost: " . number_format($oranges) . " oranges * ₱" . number_format($costPerOrange, 2) . " = ₱" . number_format($totalCost, 2) . ".\n2. Target profit: {$profitPercent}% of ₱" . number_format($totalCost, 2) . " = ₱" . number_format($totalCost * $profitPercent / 100, 2) . ". Total revenue needed = ₱" . number_format($targetRevenue, 2) . ".\n3. Spoiled oranges: {$spoiledPercent}% of " . number_format($oranges) . " = " . number_format($oranges * $spoiledPercent / 100) . " oranges. Remaining oranges = " . number_format($remainingOranges) . ".\n4. Required selling price per orange: ₱" . number_format($targetRevenue, 2) . " / " . number_format($remainingOranges) . " oranges = ₱" . number_format($requiredPrice, 2) . ".",
                    'problem_type_tag' => 'percentage-profit-retail',
                ];
            } else {
                // Number sequence template
                $start = 3 + $seed;
                $seq = [$start, $start + 2, $start + 6, $start + 14, $start + 30];
                $nextVal = $start + 62;

                return [
                    'question_text' => "What is the next number in the sequence: " . implode(", ", $seq) . ", ...?",
                    'options' => [
                        (string)($nextVal - 8),
                        (string)$nextVal,
                        (string)($nextVal + 5),
                        (string)($nextVal + 12),
                    ],
                    'correct_option_index' => 1,
                    'explanation' => "The pattern in the sequence is that the difference between consecutive numbers doubles each time:\n" .
                                     "- " . $seq[1] . " - " . $seq[0] . " = 2\n" .
                                     "- " . $seq[2] . " - " . $seq[1] . " = 4\n" .
                                     "- " . $seq[3] . " - " . $seq[2] . " = 8\n" .
                                     "- " . $seq[4] . " - " . $seq[3] . " = 16\n" .
                                     "The next difference should be 16 * 2 = 32. Therefore, the next number is " . $seq[4] . " + 32 = {$nextVal}.",
                    'problem_type_tag' => 'sequence-number-doubling',
                ];
            }
        } else {
            // Sub-Professional Numerical: Basic arithmetic and fractions
            if ($index === 0) {
                $val1 = 15 + ($seed % 10);
                $val2 = 5 + ($seed % 5);
                $val3 = 4;
                $val4 = 10;
                $result = ($val1 + $val2) * $val3 - $val4;

                return [
                    'question_text' => "Solve the following arithmetic expression: ({$val1} + {$val2}) * {$val3} - {$val4} (Variation ID: " . ($seed + 1) . ")",
                    'options' => [
                        (string)$result,
                        (string)($result - 5),
                        (string)($result + 12),
                        (string)($result * 2),
                    ],
                    'correct_option_index' => 0,
                    'explanation' => "Perform operations inside the parentheses first:\n1. ({$val1} + {$val2}) = " . ($val1 + $val2) . ".\n2. Multiply by {$val3}: " . ($val1 + $val2) . " * {$val3} = " . (($val1 + $val2) * $val3) . ".\n3. Subtract {$val4}: " . (($val1 + $val2) * $val3) . " - {$val4} = {$result}.",
                    'problem_type_tag' => 'numerical-arithmetic-expression',
                ];
            } else {
                $num1 = 1 + ($seed % 3);
                $sumNum = $num1 + 1; // since num2/den2 is 2/8 = 1/4

                return [
                    'question_text' => "Find the sum of the fractions: {$num1}/4 and 2/8. (Variation ID: " . ($seed + 1) . ")",
                    'options' => [
                        "{$sumNum}/4",
                        "1/2",
                        "5/8",
                        "{$sumNum}/8",
                    ],
                    'correct_option_index' => 0,
                    'explanation' => "1. Simplify 2/8 to its lowest terms: 2/8 = 1/4.\n2. Add the two fractions with the same denominator: {$num1}/4 + 1/4 = (" . ($num1 + 1) . ")/4 = {$sumNum}/4.",
                    'problem_type_tag' => 'numerical-fraction-addition',
                ];
            }
        }
    }

    /**
     * Generate a mock Verbal Ability question.
     */
    private function generateVerbalQuestion(string $level, int $index, int $seed): array
    {
        if ($level === 'professional') {
            if ($index === 0) {
                return [
                    'question_text' => "Choose the word opposite in meaning to the quoted word: \"Flowers are \\\"ephemeral\\\" they bloom yet wither in a week or so later.\" (Variation ID: " . ($seed + 1) . ")",
                    'options' => [
                        "Transient",
                        "Permanent",
                        "Ethereal",
                        "Elusive",
                    ],
                    'correct_option_index' => 1,
                    'explanation' => "The context clues 'bloom yet wither in a week or so' indicate a very short lifespan. 'Ephemeral' means lasting for a very short time (transient). The opposite of short-lived is 'Permanent'. 'Ethereal' means delicate/heavenly, and 'Elusive' means hard to catch.",
                    'problem_type_tag' => 'verbal-vocabulary-antonym',
                ];
            } else {
                return [
                    'question_text' => "Choose the word that correctly completes the sentence: \"The committee was so ________ by the constant bickering that they failed to reach a consensus.\" (Variation ID: " . ($seed + 1) . ")",
                    'options' => [
                        "united",
                        "alienated",
                        "fractured",
                        "consolidated",
                    ],
                    'correct_option_index' => 2,
                    'explanation' => "The context clues 'constant bickering' and 'failed to reach a consensus' indicate a state of division or conflict. 'Fractured' perfectly fits as it means split into fragments or divided, which explains why they failed to agree.",
                    'problem_type_tag' => 'verbal-sentence-completion',
                ];
            }
        } else {
            // Sub-Professional Verbal: basic grammar and spelling
            if ($index === 0) {
                return [
                    'question_text' => "Choose the sentence that uses the correct form of the homophones 'their', 'there', or 'they're': (Variation ID: " . ($seed + 1) . ")",
                    'options' => [
                        "They're going to put their books over there.",
                        "There going to put their books over they're.",
                        "Their going to put there books over they're.",
                        "They're going to put there books over their.",
                    ],
                    'correct_option_index' => 0,
                    'explanation' => "'They're' is the contraction for 'they are' (They are going to...). 'Their' is the possessive pronoun (...their books). 'There' indicates place or position (...over there). Option A is the only sentence that applies all three correctly.",
                    'problem_type_tag' => 'verbal-homophones',
                ];
            } else {
                return [
                    'question_text' => "Identify the word that is spelled correctly: (Variation ID: " . ($seed + 1) . ")",
                    'options' => [
                        "Accommodation",
                        "Acomodation",
                        "Accomodation",
                        "Acommodation",
                    ],
                    'correct_option_index' => 0,
                    'explanation' => "The correct spelling is 'Accommodation' (with two 'c's and two 'm's).",
                    'problem_type_tag' => 'verbal-spelling-verification',
                ];
            }
        }
    }

    /**
     * Generate a mock Analytical Ability question.
     */
    private function generateAnalyticalQuestion(int $index, int $seed): array
    {
        if ($index === 0) {
            return [
                'question_text' => "Which shape option completes the sequence pattern?\n\n[diagram]{\"type\": \"sequence\", \"steps\": [{\"shapes\": [{\"shape\": \"circle\", \"fill\": \"none\", \"rotation\": 0, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}, {\"shapes\": [{\"shape\": \"circle\", \"fill\": \"none\", \"rotation\": 90, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}, {\"shapes\": [{\"shape\": \"circle\", \"fill\": \"none\", \"rotation\": 180, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}, {\"shapes\": [{\"shape\": \"circle\", \"fill\": \"none\", \"rotation\": 270, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}, {\"blank\": true}]}[/diagram]\n\n(Variation ID: " . ($seed + 1) . ")",
                'options' => [
                    "[diagram]{\"shapes\": [{\"shape\": \"circle\", \"fill\": \"none\", \"rotation\": 0, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}[/diagram]",
                    "[diagram]{\"shapes\": [{\"shape\": \"circle\", \"fill\": \"none\", \"rotation\": 90, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}[/diagram]",
                    "[diagram]{\"shapes\": [{\"shape\": \"circle\", \"fill\": \"shaded\", \"rotation\": 180, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}[/diagram]",
                    "[diagram]{\"shapes\": [{\"shape\": \"square\", \"fill\": \"none\", \"rotation\": 0, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}[/diagram]",
                ],
                'correct_option_index' => 0,
                'explanation' => "The dot inside the circle rotates clockwise by 90 degrees at each step:\n1. Step 1: Dot is at the top (0 degrees).\n2. Step 2: Dot is at the right (90 degrees).\n3. Step 3: Dot is at the bottom (180 degrees).\n4. Step 4: Dot is at the left (270 degrees).\nTherefore, in the fifth step, the dot rotates back to the top position (0/360 degrees) inside an empty circle, which matches Option A.",
                'problem_type_tag' => 'analytical-abstract-reasoning-sequence',
            ];
        } elseif ($index === 1) {
            return [
                'question_text' => "Point X is to the West of Point Y. Point Z is to the North of Point Y. In which direction is Point X relative to Point Z? (Variation ID: " . ($seed + 1) . ")",
                'options' => [
                    "South-East",
                    "North-West",
                    "South-West",
                    "North-East",
                ],
                'correct_option_index' => 2,
                'explanation' => "If Y is the origin (0,0):\n- X is West of Y -> X is at (-1, 0)\n- Z is North of Y -> Z is at (0, 1)\nTo find the direction of X relative to Z, look from Z to X. Moving from Z (0,1) to X (-1,0) requires going West (towards -1) and South (down from 1 to 0). Thus, X is South-West of Z.",
                'problem_type_tag' => 'analytical-spatial-direction',
            ];
        } else {
            return [
                'question_text' => "All applicants who score above 90 on the examination are invited for an interview. No applicant who has less than two years of experience is invited for an interview. What can be logically concluded? (Variation ID: " . ($seed + 1) . ")",
                'options' => [
                    "Some applicants with less than two years of experience scored above 90.",
                    "No applicant who scored above 90 has less than two years of experience.",
                    "All applicants with more than two years of experience are interviewed.",
                    "Anyone who is interviewed scored above 90.",
                ],
                'correct_option_index' => 1,
                'explanation' => "Let S = Scored above 90, I = Interviewed, E = At least 2 years of experience.\n1. S -> I (If score > 90, then invited for interview)\n2. Not E -> Not I (If experience < 2 years, not invited)\nContrapositive of 2: I -> E (If invited, experience must be >= 2 years).\nCombining 1 and 2: S -> I -> E. Therefore, anyone who scores above 90 must have at least 2 years of experience. This means no applicant who scored above 90 has less than 2 years of experience.",
                'problem_type_tag' => 'analytical-logical-syllogism',
            ];
        }
    }

    /**
     * Generate a mock Clerical Ability question.
     */
    private function generateClericalQuestion(int $index, int $seed): array
    {
        if ($index === 0) {
            $surnames = ['Santos', 'Cruz', 'Reyes', 'Ramos', 'Aquino', 'Garcia', 'Torres', 'Diaz', 'Castro', 'Villanueva'];
            $firstNames = ['Albert', 'Alicia', 'Anna', 'Arthur', 'Ben', 'Beth', 'Bob', 'Brian', 'Carl', 'Cole', 'David', 'Diana', 'Eric', 'Eva', 'Frank', 'Grace'];
            
            $surname = $surnames[$seed % count($surnames)];
            $name1 = $firstNames[($seed) % count($firstNames)];
            $name2 = $firstNames[($seed + 1) % count($firstNames)];
            $name3 = $firstNames[($seed + 2) % count($firstNames)];
            $name4 = $firstNames[($seed + 3) % count($firstNames)];
            
            $rawNames = [
                "$surname, $name1",
                "$surname, $name2",
                "$surname, $name3",
                "$surname, $name4",
            ];
            $rawNames = array_values(array_unique($rawNames));
            while (count($rawNames) < 4) {
                $rawNames[] = "$surname, Temp" . count($rawNames);
            }
            
            $sortedNames = $rawNames;
            sort($sortedNames);

            return [
                'question_text' => "In alphabetical filing, which name should be filed third among these four names?\n1. {$rawNames[0]}\n2. {$rawNames[1]}\n3. {$rawNames[2]}\n4. {$rawNames[3]}",
                'options' => [
                    $sortedNames[2],
                    $sortedNames[0],
                    $sortedNames[1],
                    $sortedNames[3],
                ],
                'correct_option_index' => 0,
                'explanation' => "Arranging alphabetically by surname and first names:\n1. {$sortedNames[0]}\n2. {$sortedNames[1]}\n3. {$sortedNames[2]}\n4. {$sortedNames[3]}\nTherefore, '{$sortedNames[2]}' is the third name.",
                'problem_type_tag' => 'clerical-alphabetizing',
            ];
        } else {
            $words = ['DESK', 'LAMP', 'BOOK', 'PAGE', 'NOTE', 'TASK', 'WORK', 'CARD', 'COIN', 'DATE', 'FILE', 'POST'];
            $word = $words[$seed % count($words)];
            
            $nums = [];
            for ($l = 0; $l < strlen($word); $l++) {
                $nums[] = ord($word[$l]) - 64;
            }
            $correctCode = implode('-', $nums);
            
            $distractors = [];
            $nums1 = $nums;
            $nums1[count($nums1) - 1] = ($nums1[count($nums1) - 1] + 2) % 26 ?: 1;
            $distractors[] = implode('-', $nums1);
            
            $nums2 = $nums;
            $temp = $nums2[0];
            $nums2[0] = $nums2[1];
            $nums2[1] = $temp;
            $distractors[] = implode('-', $nums2);
            
            $nums3 = $nums;
            $nums3[1] = ($nums3[1] + 1) % 26 ?: 1;
            $distractors[] = implode('-', $nums3);
            
            $uniqueOptions = array_values(array_unique(array_filter([$correctCode, ...$distractors])));
            while (count($uniqueOptions) < 4) {
                $uniqueOptions[] = "4-5-19-" . (count($uniqueOptions) * 5);
            }

            $explanationLines = [];
            foreach (str_split($word) as $idx => $char) {
                $explanationLines[] = "- {$char} is the {$nums[$idx]}th letter";
            }

            return [
                'question_text' => "If the clerical code for 'FILE' is '6-9-12-5' (representing letter positions in the alphabet), what is the correct code for '{$word}'?",
                'options' => $uniqueOptions,
                'correct_option_index' => 0,
                'explanation' => "The letters in '{$word}' correspond to the following alphabetical positions:\n" .
                                 implode("\n", $explanationLines) . "\n" .
                                 "Thus, the correct code is '{$correctCode}'.",
                'problem_type_tag' => 'clerical-coding-substitution',
            ];
        }
    }

    /**
     * Generate a mock General Information question.
     */
    private function generateGeneralInformationQuestion(int $index, int $seed): array
    {
        if ($index === 0) {
            return [
                'question_text' => "According to Article III of the 1987 Philippine Constitution (Bill of Rights), which of the following is correct regarding the right of the people to be secure against unreasonable searches and seizures? (Variation ID: " . ($seed + 1) . ")",
                'options' => [
                    "A search warrant can be issued by any police officer.",
                    "A search warrant must particularly describe the place to be searched and the persons or things to be seized.",
                    "Searches can be done at any time without probable cause.",
                    "A person's property can be seized without a warrant for state convenience.",
                ],
                'correct_option_index' => 1,
                'explanation' => "Section 2, Article III of the 1987 Constitution states: '...no search warrant or warrant of arrest shall issue except upon probable cause to be determined personally by the judge... and particularly describing the place to be searched, and the persons or things to be seized.'",
                'problem_type_tag' => 'general-info-constitution-rights',
            ];
        } else {
            return [
                'question_text' => "What Republic Act is otherwise known as the 'Code of Conduct and Ethical Standards for Public Officials and Employees'? (Variation ID: " . ($seed + 1) . ")",
                'options' => [
                    "RA 3019",
                    "RA 6713",
                    "RA 7877",
                    "RA 9485",
                ],
                'correct_option_index' => 1,
                'explanation' => "Republic Act No. 6713 is the Code of Conduct and Ethical Standards for Public Officials and Employees. RA 3019 is the Anti-Graft and Corrupt Practices Act, RA 7877 is the Anti-Sexual Harassment Act, and RA 9485 is the Anti-Red Tape Act.",
                'problem_type_tag' => 'general-info-ra6713',
            ];
        }
    }
}
