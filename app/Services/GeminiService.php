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
    public static function generateQuestions(string $categoryName, string $level, int $count): array
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
        return self::generateMockQuestions($categoryName, $level, $count);
    }

    /**
     * Call Google Gemini API to generate N structured questions inside a single JSON array response.
     */
    private static function callGeminiApiBatch(string $categoryName, string $level, int $batchCount, string $apiKey): ?array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

        $prompt = "Generate exactly {$batchCount} unique multiple-choice questions for a Philippine Civil Service Exam (CSE) self-assessment tool.\n" .
                  "Category: {$categoryName}\n" .
                  "Level: " . ($level === 'professional' ? 'Professional' : 'Sub-Professional') . "\n\n" .
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

        Log::warning('Gemini API batch response format was invalid: ' . $response->body());
        return null;
    }

    /**
     * Provide N mock questions based on category and level.
     * Generates randomized variations for numerical problems.
     */
    private static function generateMockQuestions(string $categoryName, string $level, int $count): array
    {
        $questionsList = [];

        for ($i = 0; $i < $count; $i++) {
            $questionsList[] = self::getSingleMockQuestion($categoryName, $level, $i);
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
        }

        if ($categoryName === 'Verbal Ability') {
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
}
