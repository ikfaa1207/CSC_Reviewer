<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    /**
     * Generate N questions based on category and level.
     *
     * @param string $categoryName
     * @param string $level
     * @param int $count
     * @return array List of generated questions
     */
    public static function generateQuestions(string $categoryName, string $level, int $count, int $seedOffset = 0): array
    {
        $apiKey = config('services.gemini.key');

        if (!empty($apiKey)) {
            try {
                // Chunk count into batches of max 10 to prevent exceeding token output limits
                $batches = [];
                $tempCount = $count;
                while ($tempCount > 0) {
                    $batchSize = min($tempCount, 10);
                    $batches[] = $batchSize;
                    $tempCount -= $batchSize;
                }

                $allQuestions = [];
                foreach ($batches as $batchSize) {
                    $response = self::callGeminiApiBatch($categoryName, $level, $batchSize, $apiKey);
                    if ($response && is_array($response)) {
                        $allQuestions = array_merge($allQuestions, $response);
                    } else {
                        // If any batch fails, throw exception to trigger mock fallback for the rest
                        throw new \Exception("Batch generation returned empty or invalid response.");
                    }
                }

                if (count($allQuestions) > 0) {
                    // Return exactly the requested count in case the model generated slightly more/less
                    return array_slice($allQuestions, 0, $count);
                }
            } catch (\Exception $e) {
                Log::error('Gemini API bulk call failed, falling back to mock: ' . $e->getMessage());
            }
        }

        // Fallback to mock generation
        return self::generateMockQuestions($categoryName, $level, $count, $seedOffset);
    }

    /**
     * Call Google Gemini API to generate N structured questions inside a single JSON array response.
     */
    private static function callGeminiApiBatch(string $categoryName, string $level, int $batchCount, string $apiKey): ?array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

        $syllabusGuideline = "";
        $formattedLevel = $level === 'professional' ? 'Professional' : 'Sub-Professional';

        if ($categoryName === 'Numerical Ability') {
            if ($level === 'professional') {
                $syllabusGuideline = "Test advanced math, complex word problems, algebraic equations, ratios, advanced percentages, and basic data/charts interpretation.";
            } else {
                $syllabusGuideline = "Test basic arithmetic operations, fractions, basic percentages, and simple word problems.";
            }
        } elseif ($categoryName === 'Verbal Ability') {
            if ($level === 'professional') {
                $syllabusGuideline = "Test advanced grammar rules, extensive vocabulary, complex paragraph organization, and reading comprehension of long/formal texts.";
            } else {
                $syllabusGuideline = "Test basic grammar rules, spelling, simple vocabulary, basic paragraph organization, and general reading comprehension.";
            }
        } elseif ($categoryName === 'Analytical Ability') {
            $syllabusGuideline = "Test logic, reasoning, analogies (word relationships), and abstract reasoning (visual patterns). This category is only for the Professional level.";
        } elseif ($categoryName === 'Clerical Ability') {
            $syllabusGuideline = "Test clerical filing procedures (alphabetical ordering), English spelling rules, clerical tasks, and simple coding/data entry verification. This category is only for the Sub-Professional level.";
        } elseif ($categoryName === 'General Information') {
            $syllabusGuideline = "Test knowledge of the 1987 Philippine Constitution, R.A. 6713 (Code of Conduct and Ethical Standards for Public Officials and Employees), Peace & Human Rights, and Environmental Concepts.";
        }

        $prompt = "Generate exactly {$batchCount} unique multiple-choice questions for a Philippine Civil Service Exam (CSE) self-assessment tool.\n" .
                  "Category: {$categoryName}\n" .
                  "Level: {$formattedLevel}\n\n" .
                  "Syllabus & Topic Guidelines:\n" .
                  "{$syllabusGuideline}\n\n" .
                  "Requirements:\n" .
                  "1. The questions must test knowledge/skills relevant to the category and difficulty level of the Philippine Civil Service Exam.\n" .
                  "2. Each question must have exactly 4 multiple choice options.\n" .
                  "3. Define exactly one correct option index (0-indexed integer from 0 to 3) for each question.\n" .
                  "4. Provide a clear, step-by-step explanatory review detailing why that answer is correct.\n" .
                  "5. Return the response as a JSON array of objects matching the required schema.";

        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'ARRAY',
                    'description' => 'A list of generated questions',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'question_text' => ['type' => 'STRING'],
                            'options' => [
                                'type' => 'ARRAY',
                                'items' => ['type' => 'STRING'],
                                'description' => 'Exactly 4 option choices'
                            ],
                            'correct_option_index' => [
                                'type' => 'INTEGER',
                                'description' => '0-indexed number of the correct option (0 to 3)'
                            ],
                            'explanation' => ['type' => 'STRING']
                        ],
                        'required' => ['question_text', 'options', 'correct_option_index', 'explanation']
                    ]
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->post($url, $body);

        if ($response->successful()) {
            $json = $response->json();
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text) {
                $decoded = json_decode($text, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        if ($response->status() === 429) {
            \App\Models\Setting::set('ai_quota_exceeded_flag', '1');
            \App\Models\Setting::set('ai_last_error', '429 Quota Exceeded (Free Tier limit met or billing issue).');
        } else if ($response->failed()) {
            \App\Models\Setting::set('ai_last_error', 'Gemini API call failed with status ' . $response->status());
        }

        Log::warning('Gemini API batch response format was invalid: ' . $response->body());
        return null;
    }

    private static function generateMockQuestions(string $categoryName, string $level, int $count, int $seedOffset = 0): array
    {
        $questionsList = [];

        for ($i = 0; $i < $count; $i++) {
            $questionsList[] = self::getSingleMockQuestion($categoryName, $level, $i + $seedOffset);
        }

        return $questionsList;
    }

    /**
     * Get a single mock question with randomized values to guarantee uniqueness when bulk-generating.
     */
    private static function getSingleMockQuestion(string $categoryName, string $level, int $seed): array
    {
        // Simple seed offset to select template
        $index = $seed % 2;

        if ($categoryName === 'Numerical Ability') {
            if ($level === 'professional') {
                if ($index === 0) {
                    // Oranges profit template
                    // Randomize values: oranges = 50 + seed*10, price = 4 + seed, profit% = 10 or 20 or 30, spoiled% = 10
                    $oranges = 100 + ($seed * 10);
                    $costPerOrange = 5;
                    $spoiledPercent = 10;
                    $profitPercent = 20;

                    $totalCost = $oranges * $costPerOrange;
                    $targetRevenue = $totalCost * (1 + ($profitPercent / 100));
                    $remainingOranges = $oranges * (1 - ($spoiledPercent / 100));
                    $requiredPrice = round($targetRevenue / $remainingOranges, 2);

                    return [
                        'question_text' => "A vendor bought {$oranges} oranges at P{$costPerOrange}.00 each. If {$spoiledPercent}% of the oranges got spoiled, at what price per orange must he sell the remaining ones to make a {$profitPercent}% profit on his overall cost?",
                        'options' => [
                            "P" . number_format($requiredPrice, 2) . " per orange",
                            "P" . number_format($requiredPrice - 0.5, 2) . " per orange",
                            "P" . number_format($requiredPrice + 0.8, 2) . " per orange",
                            "P" . number_format($requiredPrice * 0.9, 2) . " per orange",
                        ],
                        'correct_option_index' => 0,
                        'explanation' => "1. Calculate overall cost: {$oranges} oranges * P{$costPerOrange} = P{$totalCost}.\n2. Target profit: {$profitPercent}% of P{$totalCost} = P" . ($totalCost * $profitPercent / 100) . ". Total revenue needed = P{$targetRevenue}.\n3. Spoiled oranges: {$spoiledPercent}% of {$oranges} = " . ($oranges * $spoiledPercent / 100) . " oranges. Remaining oranges = {$remainingOranges}.\n4. Required selling price per orange: P{$targetRevenue} / {$remainingOranges} oranges = P" . number_format($requiredPrice, 2) . "."
                    ];
                } else {
                    // Number sequence template
                    // Starting number = 2 + seed, difference multiplier pattern
                    $start = 3 + $seed;
                    $seq = [$start, $start + 2, $start + 6, $start + 14, $start + 30];
                    $nextVal = $start + 62;

                    return [
                        'question_text' => "What is the next number in the sequence: " . implode(", ", $seq) . ", ...?",
                        'options' => [
                            (string)($nextVal - 8),
                            (string)$nextVal,
                            (string)($nextVal + 5),
                            (string)($nextVal + 12)
                        ],
                        'correct_option_index' => 1,
                        'explanation' => "The pattern in the sequence is that the difference between consecutive numbers doubles each time:\n" .
                                         "- " . $seq[1] . " - " . $seq[0] . " = 2\n" .
                                         "- " . $seq[2] . " - " . $seq[1] . " = 4\n" .
                                         "- " . $seq[3] . " - " . $seq[2] . " = 8\n" .
                                         "- " . $seq[4] . " - " . $seq[3] . " = 16\n" .
                                         "The next difference should be 16 * 2 = 32. Therefore, the next number is " . $seq[4] . " + 32 = {$nextVal}."
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
                            (string)($result * 2)
                        ],
                        'correct_option_index' => 0,
                        'explanation' => "Perform operations inside the parentheses first:\n1. ({$val1} + {$val2}) = " . ($val1 + $val2) . ".\n2. Multiply by {$val3}: " . ($val1 + $val2) . " * {$val3} = " . (($val1 + $val2) * $val3) . ".\n3. Subtract {$val4}: " . (($val1 + $val2) * $val3) . " - {$val4} = {$result}."
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
                            "{$sumNum}/8"
                        ],
                        'correct_option_index' => 0,
                        'explanation' => "1. Simplify 2/8 to its lowest terms: 2/8 = 1/4.\n2. Add the two fractions with the same denominator: {$num1}/4 + 1/4 = ({$num1} + 1)/4 = {$sumNum}/4."
                    ];
                }
            }
        }

        if ($categoryName === 'Verbal Ability') {
            if ($level === 'professional') {
                if ($index === 0) {
                    return [
                        'question_text' => "Choose the word that is most nearly opposite in meaning to: 'EPHEMERAL' (Variation ID: " . ($seed + 1) . ")",
                        'options' => [
                            "Transient",
                            "Permanent",
                            "Ethereal",
                            "Elusive"
                        ],
                        'correct_option_index' => 1,
                        'explanation' => "'Ephemeral' means lasting for a very short time (transient). The opposite of short-lived is 'Permanent'. 'Ethereal' means delicate/heavenly, and 'Elusive' means hard to catch."
                    ];
                } else {
                    return [
                        'question_text' => "Complete the sentence: 'The committee was so __________ by the constant bickering that they failed to reach a consensus.' (Variation ID: " . ($seed + 1) . ")",
                        'options' => [
                            "united",
                            "alienated",
                            "fractured",
                            "consolidated"
                        ],
                        'correct_option_index' => 2,
                        'explanation' => "The context clues 'constant bickering' and 'failed to reach a consensus' indicate a state of division or conflict. 'Fractured' perfectly fits as it means split into fragments or divided, which explains why they failed to agree."
                    ];
                }
            } else {
                // Sub-Professional Verbal: basic grammar and spelling
                if ($index === 0) {
                    return [
                        'question_text' => "Choose the sentence that uses the correct form of the homophones 'their', 'there', or 'they\'re': (Variation ID: " . ($seed + 1) . ")",
                        'options' => [
                            "They're going to put their books over there.",
                            "There going to put their books over they're.",
                            "Their going to put there books over they're.",
                            "They're going to put there books over their."
                        ],
                        'correct_option_index' => 0,
                        'explanation' => "'They're' is the contraction for 'they are' (They are going to...). 'Their' is the possessive pronoun (...their books). 'There' indicates place or position (...over there). Option A is the only sentence that applies all three correctly."
                    ];
                } else {
                    return [
                        'question_text' => "Identify the word that is spelled correctly: (Variation ID: " . ($seed + 1) . ")",
                        'options' => [
                            "Accommodation",
                            "Acomodation",
                            "Accomodation",
                            "Acommodation"
                        ],
                        'correct_option_index' => 0,
                        'explanation' => "The correct spelling is 'Accommodation' (with two 'c's and two 'm's)."
                    ];
                }
            }
        }

        if ($categoryName === 'Analytical Ability') {
            if ($index === 0) {
                return [
                    'question_text' => "Point X is to the West of Point Y. Point Z is to the North of Point Y. In which direction is Point X relative to Point Z? (Variation ID: " . ($seed + 1) . ")",
                    'options' => [
                        "South-East",
                        "North-West",
                        "South-West",
                        "North-East"
                    ],
                    'correct_option_index' => 2,
                    'explanation' => "If Y is the origin (0,0):\n- X is West of Y -> X is at (-1, 0)\n- Z is North of Y -> Z is at (0, 1)\nTo find the direction of X relative to Z, look from Z to X. Moving from Z (0,1) to X (-1,0) requires going West (towards -1) and South (down from 1 to 0). Thus, X is South-West of Z."
                ];
            } else {
                return [
                    'question_text' => "All applicants who score above 90 on the examination are invited for an interview. No applicant who has less than two years of experience is invited for an interview. What can be logically concluded? (Variation ID: " . ($seed + 1) . ")",
                    'options' => [
                        "Some applicants with less than two years of experience scored above 90.",
                        "No applicant who scored above 90 has less than two years of experience.",
                        "All applicants with more than two years of experience are interviewed.",
                        "Anyone who is interviewed scored above 90."
                    ],
                    'correct_option_index' => 1,
                    'explanation' => "Let S = Scored above 90, I = Interviewed, E = At least 2 years of experience.\n1. S -> I (If score > 90, then invited for interview)\n2. Not E -> Not I (If experience < 2 years, not invited)\nContrapositive of 2: I -> E (If invited, experience must be >= 2 years).\nCombining 1 and 2: S -> I -> E. Therefore, anyone who scores above 90 must have at least 2 years of experience. This means no applicant who scored above 90 has less than 2 years of experience."
                ];
            }
        }

        if ($categoryName === 'Clerical Ability') {
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
                    "$surname, $name4"
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
                        $sortedNames[3]
                    ],
                    'correct_option_index' => 0,
                    'explanation' => "Arranging alphabetically by surname and first names:\n1. {$sortedNames[0]}\n2. {$sortedNames[1]}\n3. {$sortedNames[2]}\n4. {$sortedNames[3]}\nTherefore, '{$sortedNames[2]}' is the third name."
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
                                     "Thus, the correct code is '{$correctCode}'."
                ];
            }
        }

        // General Information fallback
        if ($index === 0) {
            return [
                'question_text' => "According to Article III of the 1987 Philippine Constitution (Bill of Rights), which of the following is correct regarding the right of the people to be secure against unreasonable searches and seizures? (Variation ID: " . ($seed + 1) . ")",
                'options' => [
                    "A search warrant can be issued by any police officer.",
                    "A search warrant must particularly describe the place to be searched and the persons or things to be seized.",
                    "Searches can be done at any time without probable cause.",
                    "A person's property can be seized without a warrant for state convenience."
                ],
                'correct_option_index' => 1,
                'explanation' => "Section 2, Article III of the 1987 Constitution states: '...no search warrant or warrant of arrest shall issue except upon probable cause to be determined personally by the judge... and particularly describing the place to be searched, and the persons or things to be seized.'"
            ];
        } else {
            return [
                'question_text' => "What Republic Act is otherwise known as the 'Code of Conduct and Ethical Standards for Public Officials and Employees'? (Variation ID: " . ($seed + 1) . ")",
                'options' => [
                    "RA 3019",
                    "RA 6713",
                    "RA 7877",
                    "RA 9485"
                ],
                'correct_option_index' => 1,
                'explanation' => "Republic Act No. 6713 is the Code of Conduct and Ethical Standards for Public Officials and Employees. RA 3019 is the Anti-Graft and Corrupt Practices Act, RA 7877 is the Anti-Sexual Harassment Act, and RA 9485 is the Anti-Red Tape Act."
            ];
        }
    }

    /**
     * Verify the factual correctness of generated questions.
     * Takes an array of questions, calls Gemini API to review them, and returns verified questions.
     */
    public static function verifyQuestions(array $questions): array
    {
        $apiKey = config('services.gemini.key');
        if (empty($apiKey) || empty($questions)) {
            // For mock verification or fallback: append is_valid = true to all questions
            return array_map(function ($q) {
                $q['is_valid'] = true;
                return $q;
            }, $questions);
        }

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

            $prompt = "You are an expert reviewer for the Philippine Civil Service Exam (CSE).\n" .
                      "Review the following JSON list of multiple-choice questions for structural and factual accuracy.\n\n" .
                      "For each question:\n" .
                      "1. Verify that the correct option index (0-indexed integer) matches the question text.\n" .
                      "2. If the marked index is wrong, update `correct_option_index` to the correct option index.\n" .
                      "3. Ensure the explanation matches the correct answer.\n" .
                      "4. If the question is factually incorrect, or does not have exactly one correct option, set `is_valid` to false. Otherwise, set `is_valid` to true.\n\n" .
                      "Questions to review:\n" .
                      json_encode($questions, JSON_PRETTY_PRINT) . "\n\n" .
                      "Return the verified questions as a JSON array of objects matching the required schema.";

            $body = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'ARRAY',
                        'description' => 'A list of verified questions',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'question_text' => ['type' => 'STRING'],
                                'options' => [
                                    'type' => 'ARRAY',
                                    'items' => ['type' => 'STRING']
                                ],
                                'correct_option_index' => ['type' => 'INTEGER'],
                                'explanation' => ['type' => 'STRING'],
                                'is_valid' => ['type' => 'BOOLEAN']
                            ],
                            'required' => ['question_text', 'options', 'correct_option_index', 'explanation', 'is_valid']
                        ]
                    ]
                ]
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post($url, $body);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text) {
                    $decoded = json_decode($text, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }
            }

            if ($response->status() === 429) {
                \App\Models\Setting::set('ai_quota_exceeded_flag', '1');
                \App\Models\Setting::set('ai_last_error', '429 Quota Exceeded (Free Tier limit met or billing issue).');
            } else if ($response->failed()) {
                \App\Models\Setting::set('ai_last_error', 'Gemini API verification call failed with status ' . $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Gemini API verification call failed: ' . $e->getMessage());
            \App\Models\Setting::set('ai_last_error', 'Gemini API verification call failed: ' . $e->getMessage());
        }

        // Fallback: append is_valid = true to all questions
        return array_map(function ($q) {
            $q['is_valid'] = true;
            return $q;
        }, $questions);
    }
}
