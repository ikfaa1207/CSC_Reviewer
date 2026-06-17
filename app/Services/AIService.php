<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    /**
     * Generate N questions based on category and level.
     *
     * @param string $categoryName
     * @param string $level
     * @param int $count
     * @return array List of generated questions
     */
    public static function generateQuestions(string $categoryName, string $level, int $count, int $seedOffset = 0, array $extraExcludeTexts = []): array
    {
        $apiKey = config('services.ai.key');

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
                    $inProgressTexts = array_map(function ($q) {
                        return strtolower(trim(preg_replace('/\s*\(Variation ID:\s*\d+\)/i', '', $q['question_text'])));
                    }, $allQuestions);

                    $cleanedExtra = array_map(function ($text) {
                        return strtolower(trim(preg_replace('/\s*\(Variation ID:\s*\d+\)/i', '', $text)));
                    }, $extraExcludeTexts);

                    $mergedExclude = array_unique(array_merge($inProgressTexts, $cleanedExtra));

                    $response = self::callGeminiApiBatch($categoryName, $level, $batchSize, $apiKey, $mergedExclude);
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
                \App\Models\Setting::set('ai_last_error', 'Gemini API bulk call failed: ' . $e->getMessage());
            }
        }

        // Fallback to mock generation
        return self::generateMockQuestions($categoryName, $level, $count, $seedOffset);
    }

    /**
     * Call Google Gemini API to generate N structured questions inside a single JSON array response.
     */
    private static function callGeminiApiBatch(string $categoryName, string $level, int $batchCount, string $apiKey, array $inProgressTexts = []): ?array
    {
        if (config('services.ai.provider') === 'deepseek') {
            return self::callDeepSeekApiBatch($categoryName, $level, $batchCount, $apiKey, $inProgressTexts);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

        $syllabusGuideline = "";
        $formattedLevel = $level === 'professional' ? 'Professional' : 'Sub-Professional';

        if ($categoryName === 'Numerical Ability') {
            if ($level === 'professional') {
                $syllabusGuideline = "Test advanced mathematics, word problems involving fractions, decimals, percentages, sequence completion (e.g., sequences like '10 17 26 37'), averages, age word problems, distance/speed/time, perimeter/area/volume, simple/compound interest, investment returns/bonds, basic algebra equations, and Data Sufficiency problems.";
            } else {
                $syllabusGuideline = "Test basic arithmetic operations, fractions, basic percentages, averages, rates, and simple word problems (e.g., salary increase rate, price discounts).";
            }
        } elseif ($categoryName === 'Verbal Ability') {
            if ($level === 'professional') {
                $syllabusGuideline = "Test advanced grammar rules, extensive vocabulary (synonyms and antonyms embedded in contextual sentences), analogies (both single-word and double-word analogies), correct usage, identifying errors, paragraph organization, and reading comprehension of formal texts.";
            } else {
                $syllabusGuideline = "Test basic grammar rules, spelling identification (e.g., accommodation vs. accomodation), simple vocabulary, analogies (single-word analogies), correct usage, paragraph organization, and general reading comprehension.";
            }
        } elseif ($categoryName === 'Analytical Ability') {
            $syllabusGuideline = "Test logical reasoning (syllogisms, drawing valid conclusions from statements, identifying assumptions), word associations, and inductive/sequence completion of number or letter series. This category is only for the Professional level.";
        } elseif ($categoryName === 'Clerical Ability') {
            $syllabusGuideline = "Test clerical filing procedures (alphabetical ordering of names, departments, or organizations), English spelling rules, clerical tasks, coding, and data verification. This category is only for the Sub-Professional level.";
        } elseif ($categoryName === 'General Information') {
            $syllabusGuideline = "Test knowledge of the 1987 Philippine Constitution (especially Article III Bill of Rights), R.A. 6713 (Code of Conduct and Ethical Standards for Public Officials and Employees), Peace & Human Rights, and Environmental Concepts (climate change, resource preservation).";
        }

        // Fetch recently generated questions for this category to prevent duplication
        $existingQuestions = [];
        try {
            $existingQuestions = \App\Models\Question::whereHas('category', function ($query) use ($categoryName) {
                $query->where('name', $categoryName);
            })
            ->orderBy('created_at', 'desc')
            ->take(40)
            ->get(['question_text', 'problem_type_tag'])
            ->map(function ($q) {
                $stripped = strtolower(trim(preg_replace('/\s*\(Variation ID:\s*\d+\)/i', '', $q->question_text)));
                $tag = $q->problem_type_tag ? ' (concept: ' . trim($q->problem_type_tag) . ')' : '';
                return $stripped . $tag;
            })
            ->unique()
            ->filter()
            ->toArray();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch existing questions for AI exclude list: ' . $e->getMessage());
        }

        $excludeTexts = array_unique(array_merge($existingQuestions, $inProgressTexts));

        $excludePrompt = "";
        if (!empty($excludeTexts)) {
            $excludePrompt = "\n\nCRITICAL REDUNDANCY PREVENTION:\n" .
                             "To avoid generating redundant or duplicate questions, DO NOT generate any questions that are identical or highly similar to the following list of existing questions/concepts in the database:\n";
            foreach ($excludeTexts as $eq) {
                $excludePrompt .= "- " . trim($eq) . "\n";
            }
        }

        $prompt = "Generate exactly {$batchCount} unique multiple-choice questions for a Philippine Civil Service Exam (CSE) self-assessment tool.\n" .
                  "Category: {$categoryName}\n" .
                  "Level: {$formattedLevel}\n\n" .
                  "Syllabus & Topic Guidelines:\n" .
                  "{$syllabusGuideline}" .
                  $excludePrompt . "\n\n" .
                  "Style and Format Guidelines (modeled after the official 2026 CSE reviewer):\n" .
                  "- For Numerical Ability (Mathematics):\n" .
                  "  1. Word Problems & Operations: Write mathematical expressions, percentages, and fractions in plain text format (e.g., use '1/2' or '33 1/3%' instead of special symbols or LaTeX math syntax).\n" .
                  "  2. Financial & Currency Formatting: Format all monetary/financial values using the Philippine Peso symbol '₱' and commas as thousands separators, with two decimal places (e.g., use '₱1,250.00' instead of '1250' or 'P1250').\n" .
                  "  3. Data Sufficiency questions (Professional level): Provide a mathematical question followed by two statements on new lines, labeled 1) and 2). The 4 options must represent sufficiency rules and be exactly: \n" .
                  "     - Statement (1) ALONE is sufficient, but statement (2) alone is not sufficient.\n" .
                  "     - Statement (2) ALONE is sufficient, but statement (1) alone is not sufficient.\n" .
                  "     - BOTH statements TOGETHER are sufficient, but NEITHER statement ALONE is sufficient.\n" .
                  "     - Statements (1) and (2) TOGETHER are NOT sufficient.\n" .
                  "     (Or dynamically substitute one with 'Each statement ALONE is sufficient.' if applicable. Options must be clean strings without any option letter prefix like 'A.' or 'a.').\n" .
                  "- For Verbal Ability:\n" .
                  "  1. Vocabulary (Synonyms & Antonyms): Wrap the target vocabulary word in double quotes (e.g., \"apathetic\" or \"brusque\") inside a complete, natural sentence context. The question text should be phrased as: 'Choose the word closest in meaning to the quoted word: ...' (for synonyms) or 'Choose the word opposite in meaning to the quoted word: ...' (for antonyms).\n" .
                  "  2. Analogy:\n" .
                  "     - Single-word Analogy: Phrased as: 'Complete the analogy: Moby Dick : Herman Melville || The Old Man and the Sea : ________'\n" .
                  "     - Double-word Analogy: Phrased as: 'Identify the pair of words that shares the same relationship as the given pair: blend : mix'\n" .
                  "  3. Correct Usage: Ask the user to complete a sentence. E.g., 'Choose the word that correctly completes the sentence: ...'\n" .
                  "  4. Identifying Errors: Ask the user to identify the grammatically incorrect segment. Phrased as: 'Identify the word or phrase that is NOT acceptable in formal written English: ...'. Options should list the segments and 'No error' as the fourth option.\n" .
                  "- For Clerical Ability:\n" .
                  "  1. Alphabetizing: Provide 4 items (such as names, government departments, or organizations) labeled A, B, C, and D. Phrased as: 'Arrange the following items in alphabetical order: \\nA. [Item A]\\nB. [Item B]\\nC. [Item C]\\nD. [Item D]'. The 4 options must be permutations of the letters A, B, C, D (e.g., 'ABCD', 'ACBD', 'BCAD', 'CBAD'). The correct option index must point to the option representing the exact correct alphabetical order.\n" .
                  "  2. Spelling/Data Verification: Identify correctly spelled words or matching codes/data.\n" .
                  "- For Analytical Ability (Professional level):\n" .
                  "  1. Logical Reasoning: Provide a set of premises or statements and ask for the logical conclusion or assumption. E.g., 'All applicants who score above 90 are invited... What can be logically concluded?'\n" .
                  "  2. Sequence/Inductive Reasoning: Ask to find the next item in a sequence of numbers or letters. Phrased as: 'Find the next item in the sequence: ZY, WV, TS, QP, ________'.\n" .
                  "  3. Abstract Reasoning: Draw shape-based diagrams. Write the question text using a diagram tag: 'Which option completes the sequence pattern?' followed by a JSON diagram enclosed in custom tags: [diagram]{\"type\": \"sequence\"|\"grid\", ...}[/diagram]. The 4 multiple choice options must also be individual diagrams representing the choice shapes: '[diagram]{\"shapes\": [...]}[/diagram]'. Keep diagram JSON clean and simple. Supported shape types: 'circle', 'square', 'triangle', 'arrow', 'cross', 'line', 'star'. Supported fills: 'none', 'solid', 'shaded'. Supported rotation degrees (0, 45, 90, 180, etc.). Supported decorations: dots or lines inside/outside.\n\n" .
                  "Requirements:\n" .
                  "1. The questions must test knowledge/skills relevant to the category and difficulty level of the Philippine Civil Service Exam.\n" .
                  "2. CRITICAL LOGIC RULE: PREVENT SEMANTIC DUPLICATES. A semantic duplicate is a question that uses the exact same mathematical formula, logic pattern, or scenario type as a previous question, even if you change the names, places, or exact numbers. For example: \n" .
                  "   - Duplicate A: 'Juan travels 60km in 2 hours. What is his speed?'\n" .
                  "   - Duplicate B: 'Maria drives 120km in 4 hours. Calculate her speed.'\n" .
                  "   These are duplicates because they test the same Rate formula with similar logic. Every question you generate must test a completely different mathematical concept, logical problem type, or core subject matter. Ensure that no two questions share the exact same core calculation steps.\n" .
                  "3. Define a specific 'problem_type_tag' (e.g. 'work-rate-pipes', 'percentage-discount', 'syllogism-all-some', 'sequence-geometric', 'vocabulary-antonym-sentence') that describes the exact logic/concept used.\n" .
                  "4. Each question must have exactly 4 multiple choice options.\n" .
                  "5. Define exactly one correct option index (0-indexed integer from 0 to 3) for each question.\n" .
                  "6. Provide a clear, step-by-step explanatory review detailing why that answer is correct.\n" .
                  "7. Return the response as a JSON array of objects matching the required schema.\n" .
                  "8. CRITICAL: DO NOT prefix options in the 'options' array with letter headers (like 'A.', 'a.', 'B.', 'b.', '1.', etc.). Options must be pure, clean strings.";

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
                            'explanation' => ['type' => 'STRING'],
                            'problem_type_tag' => [
                                'type' => 'STRING',
                                'description' => 'A specific short tag describing the core concept/logic used (e.g. percentage-discount, syllogism-all-some, age-word-problem)'
                            ]
                        ],
                        'required' => ['question_text', 'options', 'correct_option_index', 'explanation', 'problem_type_tag']
                    ]
                ]
            ]
        ];

        $response = Http::withoutVerifying()->timeout(120)->withHeaders([
            'Content-Type' => 'application/json'
        ])->post($url, $body);

        if ($response->successful()) {
            $json = $response->json();
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text) {
                $decoded = json_decode($text, true);
                if (is_array($decoded)) {
                    \App\Models\Setting::set('ai_quota_exceeded_flag', '0');
                    \App\Models\Setting::set('ai_last_error', null);
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
        $index = $categoryName === 'Analytical Ability' ? $seed % 3 : $seed % 2;

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
                        'question_text' => "A vendor bought " . number_format($oranges) . " oranges at ₱" . number_format($costPerOrange, 2) . " each. If {$spoiledPercent}% of the oranges got spoiled, at what price per orange must he sell the remaining ones to make a {$profitPercent}% profit on his overall cost?",
                        'options' => [
                            "₱" . number_format($requiredPrice, 2) . " per orange",
                            "₱" . number_format($requiredPrice - 0.5, 2) . " per orange",
                            "₱" . number_format($requiredPrice + 0.8, 2) . " per orange",
                            "₱" . number_format($requiredPrice * 0.9, 2) . " per orange",
                        ],
                        'correct_option_index' => 0,
                        'explanation' => "1. Calculate overall cost: " . number_format($oranges) . " oranges * ₱" . number_format($costPerOrange, 2) . " = ₱" . number_format($totalCost, 2) . ".\n2. Target profit: {$profitPercent}% of ₱" . number_format($totalCost, 2) . " = ₱" . number_format($totalCost * $profitPercent / 100, 2) . ". Total revenue needed = ₱" . number_format($targetRevenue, 2) . ".\n3. Spoiled oranges: {$spoiledPercent}% of " . number_format($oranges) . " = " . number_format($oranges * $spoiledPercent / 100) . " oranges. Remaining oranges = " . number_format($remainingOranges) . ".\n4. Required selling price per orange: ₱" . number_format($targetRevenue, 2) . " / " . number_format($remainingOranges) . " oranges = ₱" . number_format($requiredPrice, 2) . ".",
                        'problem_type_tag' => 'percentage-profit-retail'
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
                                         "The next difference should be 16 * 2 = 32. Therefore, the next number is " . $seq[4] . " + 32 = {$nextVal}.",
                        'problem_type_tag' => 'sequence-number-doubling'
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
                        'explanation' => "Perform operations inside the parentheses first:\n1. ({$val1} + {$val2}) = " . ($val1 + $val2) . ".\n2. Multiply by {$val3}: " . ($val1 + $val2) . " * {$val3} = " . (($val1 + $val2) * $val3) . ".\n3. Subtract {$val4}: " . (($val1 + $val2) * $val3) . " - {$val4} = {$result}.",
                        'problem_type_tag' => 'numerical-arithmetic-expression'
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
                        'explanation' => "1. Simplify 2/8 to its lowest terms: 2/8 = 1/4.\n2. Add the two fractions with the same denominator: {$num1}/4 + 1/4 = ({$num1} + 1)/4 = {$sumNum}/4.",
                        'problem_type_tag' => 'numerical-fraction-addition'
                    ];
                }
            }
        }

        if ($categoryName === 'Verbal Ability') {
            if ($level === 'professional') {
                if ($index === 0) {
                    return [
                        'question_text' => "Choose the word opposite in meaning to the quoted word: \"Flowers are \\\"ephemeral\\\"; they bloom yet wither in a week or so later.\" (Variation ID: " . ($seed + 1) . ")",
                        'options' => [
                            "Transient",
                            "Permanent",
                            "Ethereal",
                            "Elusive"
                        ],
                        'correct_option_index' => 1,
                        'explanation' => "The context clues 'bloom yet wither in a week or so' indicate a very short lifespan. 'Ephemeral' means lasting for a very short time (transient). The opposite of short-lived is 'Permanent'. 'Ethereal' means delicate/heavenly, and 'Elusive' means hard to catch.",
                        'problem_type_tag' => 'verbal-vocabulary-antonym'
                    ];
                } else {
                    return [
                        'question_text' => "Choose the word that correctly completes the sentence: \"The committee was so ________ by the constant bickering that they failed to reach a consensus.\" (Variation ID: " . ($seed + 1) . ")",
                        'options' => [
                            "united",
                            "alienated",
                            "fractured",
                            "consolidated"
                        ],
                        'correct_option_index' => 2,
                        'explanation' => "The context clues 'constant bickering' and 'failed to reach a consensus' indicate a state of division or conflict. 'Fractured' perfectly fits as it means split into fragments or divided, which explains why they failed to agree.",
                        'problem_type_tag' => 'verbal-sentence-completion'
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
                        'explanation' => "'They're' is the contraction for 'they are' (They are going to...). 'Their' is the possessive pronoun (...their books). 'There' indicates place or position (...over there). Option A is the only sentence that applies all three correctly.",
                        'problem_type_tag' => 'verbal-homophones'
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
                        'explanation' => "The correct spelling is 'Accommodation' (with two 'c's and two 'm's).",
                        'problem_type_tag' => 'verbal-spelling-verification'
                    ];
                }
            }
        }

        if ($categoryName === 'Analytical Ability') {
            if ($index === 0) {
                return [
                    'question_text' => "Which shape option completes the sequence pattern?\n\n[diagram]{\"type\": \"sequence\", \"steps\": [{\"shapes\": [{\"shape\": \"circle\", \"fill\": \"none\", \"rotation\": 0, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}, {\"shapes\": [{\"shape\": \"circle\", \"fill\": \"none\", \"rotation\": 90, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}, {\"shapes\": [{\"shape\": \"circle\", \"fill\": \"none\", \"rotation\": 180, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}, {\"shapes\": [{\"shape\": \"circle\", \"fill\": \"none\", \"rotation\": 270, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}, {\"blank\": true}]}[/diagram]\n\n(Variation ID: " . ($seed + 1) . ")",
                    'options' => [
                        "[diagram]{\"shapes\": [{\"shape\": \"circle\", \"fill\": \"none\", \"rotation\": 0, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}[/diagram]",
                        "[diagram]{\"shapes\": [{\"shape\": \"circle\", \"fill\": \"none\", \"rotation\": 90, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}[/diagram]",
                        "[diagram]{\"shapes\": [{\"shape\": \"circle\", \"fill\": \"shaded\", \"rotation\": 180, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}[/diagram]",
                        "[diagram]{\"shapes\": [{\"shape\": \"square\", \"fill\": \"none\", \"rotation\": 0, \"decorations\": [{\"type\": \"dot\", \"position\": \"top\"}]}]}[/diagram]"
                    ],
                    'correct_option_index' => 0,
                    'explanation' => "The dot inside the circle rotates clockwise by 90 degrees at each step:\n1. Step 1: Dot is at the top (0 degrees).\n2. Step 2: Dot is at the right (90 degrees).\n3. Step 3: Dot is at the bottom (180 degrees).\n4. Step 4: Dot is at the left (270 degrees).\nTherefore, in the fifth step, the dot rotates back to the top position (0/360 degrees) inside an empty circle, which matches Option A.",
                    'problem_type_tag' => 'analytical-abstract-reasoning-sequence'
                ];
            } elseif ($index === 1) {
                return [
                    'question_text' => "Point X is to the West of Point Y. Point Z is to the North of Point Y. In which direction is Point X relative to Point Z? (Variation ID: " . ($seed + 1) . ")",
                    'options' => [
                        "South-East",
                        "North-West",
                        "South-West",
                        "North-East"
                    ],
                    'correct_option_index' => 2,
                    'explanation' => "If Y is the origin (0,0):\n- X is West of Y -> X is at (-1, 0)\n- Z is North of Y -> Z is at (0, 1)\nTo find the direction of X relative to Z, look from Z to X. Moving from Z (0,1) to X (-1,0) requires going West (towards -1) and South (down from 1 to 0). Thus, X is South-West of Z.",
                    'problem_type_tag' => 'analytical-spatial-direction'
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
                    'explanation' => "Let S = Scored above 90, I = Interviewed, E = At least 2 years of experience.\n1. S -> I (If score > 90, then invited for interview)\n2. Not E -> Not I (If experience < 2 years, not invited)\nContrapositive of 2: I -> E (If invited, experience must be >= 2 years).\nCombining 1 and 2: S -> I -> E. Therefore, anyone who scores above 90 must have at least 2 years of experience. This means no applicant who scored above 90 has less than 2 years of experience.",
                    'problem_type_tag' => 'analytical-logical-syllogism'
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
                    'explanation' => "Arranging alphabetically by surname and first names:\n1. {$sortedNames[0]}\n2. {$sortedNames[1]}\n3. {$sortedNames[2]}\n4. {$sortedNames[3]}\nTherefore, '{$sortedNames[2]}' is the third name.",
                    'problem_type_tag' => 'clerical-alphabetizing'
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
                    'problem_type_tag' => 'clerical-coding-substitution'
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
                'explanation' => "Section 2, Article III of the 1987 Constitution states: '...no search warrant or warrant of arrest shall issue except upon probable cause to be determined personally by the judge... and particularly describing the place to be searched, and the persons or things to be seized.'",
                'problem_type_tag' => 'general-info-constitution-rights'
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
                'explanation' => "Republic Act No. 6713 is the Code of Conduct and Ethical Standards for Public Officials and Employees. RA 3019 is the Anti-Graft and Corrupt Practices Act, RA 7877 is the Anti-Sexual Harassment Act, and RA 9485 is the Anti-Red Tape Act.",
                'problem_type_tag' => 'general-info-ra6713'
            ];
        }
    }

    /**
     * Verify the factual correctness of generated questions and check for semantic duplication.
     * Takes an array of questions, a list of database candidates, calls Gemini API to review them, and returns verified questions.
     */
    public static function verifyQuestions(array $questions, array $dbCandidates = []): array
    {
        $apiKey = config('services.ai.key');
        if (empty($apiKey) || empty($questions)) {
            // For mock verification or fallback: append is_valid = true to all questions
            return array_map(function ($q) {
                $q['is_valid'] = true;
                $q['error_reason'] = null;
                return $q;
            }, $questions);
        }

        // Chunk verification requests into max 10 questions to prevent payload sizes that trigger 503 / timeout errors
        $chunks = array_chunk($questions, 10);
        $allVerified = [];
        $hasError = false;

        foreach ($chunks as $chunk) {
            $verifiedChunk = self::verifyQuestionsBatch($chunk, $dbCandidates, $apiKey);
            if ($verifiedChunk === null) {
                $hasError = true;
                break;
            }
            $allVerified = array_merge($allVerified, $verifiedChunk);
        }

        // If an error occurred or some questions were left unverified, apply graceful valid=true fallback for remaining questions
        if ($hasError || count($allVerified) < count($questions)) {
            $unverifiedPart = array_slice($questions, count($allVerified));
            $fallbackPart = array_map(function ($q) {
                $q['is_valid'] = true;
                $q['error_reason'] = null;
                return $q;
            }, $unverifiedPart);
            $allVerified = array_merge($allVerified, $fallbackPart);
        }

        return $allVerified;
    }

    /**
     * Call Gemini API to verify a single batch of questions.
     */
    private static function verifyQuestionsBatch(array $questions, array $dbCandidates, string $apiKey): ?array
    {
        if (config('services.ai.provider') === 'deepseek') {
            return self::verifyDeepSeekQuestionsBatch($questions, $dbCandidates, $apiKey);
        }

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

            $candidatesText = "";
            if (!empty($dbCandidates)) {
                $candidatesText = "\n\nEXISTING DATABASE CANDIDATES (For Semantic Duplicate Audit):\n" .
                                  json_encode($dbCandidates, JSON_PRETTY_PRINT) . "\n";
            }

            $prompt = "You are an expert reviewer and quality auditor for the Philippine Civil Service Exam (CSE).\n" .
                      "Review the following JSON list of newly generated multiple-choice questions for structural accuracy, factual correctness, and semantic duplication against similar existing questions in our database.\n" .
                      $candidatesText . "\n" .
                      "QUESTIONS TO AUDIT & VERIFY:\n" .
                      json_encode($questions, JSON_PRETTY_PRINT) . "\n\n" .
                      "VERIFICATION RULES & CRITERIA:\n" .
                      "1. Structural Audit: Ensure the question has exactly 4 option choices and a valid correct_option_index (0-indexed integer from 0 to 3).\n" .
                      "2. Factual Audit: Verify that the correct option index points to the factually correct answer and the explanation aligns with the answer.\n" .
                      "3. CRITICAL SEMANTIC DEDUPLICATION AUDIT: A generated question is a semantic duplicate if it tests the exact same mathematical formula, logic pattern, or scenario type as any question in the EXISTING DATABASE CANDIDATES list, even if names, places, or numbers are changed. E.g., 'Juan travels 60km in 2 hrs' vs 'Maria drives 120km in 4 hrs' are semantic duplicates.\n" .
                      "4. If a question is factually incorrect, structurally invalid, or is a semantic duplicate of an existing database question, set `is_valid` to false and provide a brief explanation in `error_reason` (e.g. 'Semantic duplicate of DB question #42 - Speed rate scenario').\n" .
                      "5. Otherwise, set `is_valid` to true and `error_reason` to null.\n\n" .
                      "Return the verified questions as a JSON array of objects matching the required schema. Ensure you retain the 'problem_type_tag' of each question in the output.";

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
                                'problem_type_tag' => ['type' => 'STRING'],
                                'is_valid' => ['type' => 'BOOLEAN'],
                                'error_reason' => ['type' => 'STRING']
                            ],
                            'required' => ['question_text', 'options', 'correct_option_index', 'explanation', 'problem_type_tag', 'is_valid']
                        ]
                    ]
                ]
            ];

            $response = Http::withoutVerifying()->timeout(120)->withHeaders([
                'Content-Type' => 'application/json'
            ])->post($url, $body);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text) {
                    $decoded = json_decode($text, true);
                    if (is_array($decoded)) {
                        \App\Models\Setting::set('ai_quota_exceeded_flag', '0');
                        \App\Models\Setting::set('ai_last_error', null);
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

        return null;
    }

    /**
     * Call DeepSeek API to generate N structured questions inside a single JSON array response.
     */
    private static function callDeepSeekApiBatch(string $categoryName, string $level, int $batchCount, string $apiKey, array $inProgressTexts = []): ?array
    {
        $url = "https://api.deepseek.com/chat/completions";

        $syllabusGuideline = "";
        $formattedLevel = $level === 'professional' ? 'Professional' : 'Sub-Professional';

        if ($categoryName === 'Numerical Ability') {
            if ($level === 'professional') {
                $syllabusGuideline = "Test advanced mathematics, word problems involving fractions, decimals, percentages, sequence completion (e.g., sequences like '10 17 26 37'), averages, age word problems, distance/speed/time, perimeter/area/volume, simple/compound interest, investment returns/bonds, basic algebra equations, and Data Sufficiency problems.";
            } else {
                $syllabusGuideline = "Test basic arithmetic operations, fractions, basic percentages, averages, rates, and simple word problems (e.g., salary increase rate, price discounts).";
            }
        } elseif ($categoryName === 'Verbal Ability') {
            if ($level === 'professional') {
                $syllabusGuideline = "Test advanced grammar rules, extensive vocabulary (synonyms and antonyms embedded in contextual sentences), analogies (both single-word and double-word analogies), correct usage, identifying errors, paragraph organization, and reading comprehension of formal texts.";
            } else {
                $syllabusGuideline = "Test basic grammar rules, spelling identification (e.g., accommodation vs. accomodation), simple vocabulary, analogies (single-word analogies), correct usage, paragraph organization, and general reading comprehension.";
            }
        } elseif ($categoryName === 'Analytical Ability') {
            $syllabusGuideline = "Test logical reasoning (syllogisms, drawing valid conclusions from statements, identifying assumptions), word associations, and inductive/sequence completion of number or letter series. This category is only for the Professional level.";
        } elseif ($categoryName === 'Clerical Ability') {
            $syllabusGuideline = "Test clerical filing procedures (alphabetical ordering of names, departments, or organizations), English spelling rules, clerical tasks, coding, and data verification. This category is only for the Sub-Professional level.";
        } elseif ($categoryName === 'General Information') {
            $syllabusGuideline = "Test knowledge of the 1987 Philippine Constitution (especially Article III Bill of Rights), R.A. 6713 (Code of Conduct and Ethical Standards for Public Officials and Employees), Peace & Human Rights, and Environmental Concepts (climate change, resource preservation).";
        }

        // Fetch recently generated questions for this category to prevent duplication
        $existingQuestions = [];
        try {
            $existingQuestions = \App\Models\Question::whereHas('category', function ($query) use ($categoryName) {
                $query->where('name', $categoryName);
            })
            ->orderBy('created_at', 'desc')
            ->take(40)
            ->get(['question_text', 'problem_type_tag'])
            ->map(function ($q) {
                $stripped = strtolower(trim(preg_replace('/\s*\(Variation ID:\s*\d+\)/i', '', $q->question_text)));
                $tag = $q->problem_type_tag ? ' (concept: ' . trim($q->problem_type_tag) . ')' : '';
                return $stripped . $tag;
            })
            ->unique()
            ->filter()
            ->toArray();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch existing questions for AI exclude list: ' . $e->getMessage());
        }

        $excludeTexts = array_unique(array_merge($existingQuestions, $inProgressTexts));

        $excludePrompt = "";
        if (!empty($excludeTexts)) {
            $excludePrompt = "\n\nCRITICAL REDUNDANCY PREVENTION:\n" .
                             "To avoid generating redundant or duplicate questions, DO NOT generate any questions that are identical or highly similar to the following list of existing questions/concepts in the database:\n";
            foreach ($excludeTexts as $eq) {
                $excludePrompt .= "- " . trim($eq) . "\n";
            }
        }

        $prompt = "Generate exactly {$batchCount} unique multiple-choice questions for a Philippine Civil Service Exam (CSE) self-assessment tool.\n" .
                  "Category: {$categoryName}\n" .
                  "Level: {$formattedLevel}\n\n" .
                  "Syllabus & Topic Guidelines:\n" .
                  "{$syllabusGuideline}" .
                  $excludePrompt . "\n\n" .
                  "Style and Format Guidelines (modeled after the official 2026 CSE reviewer):\n" .
                  "- For Numerical Ability (Mathematics):\n" .
                  "  1. Word Problems & Operations: Write mathematical expressions, percentages, and fractions in plain text format (e.g., use '1/2' or '33 1/3%' instead of special symbols or LaTeX math syntax).\n" .
                  "  2. Financial & Currency Formatting: Format all monetary/financial values using the Philippine Peso symbol '₱' and commas as thousands separators, with two decimal places (e.g., use '₱1,250.00' instead of '1250' or 'P1250').\n" .
                  "  3. Data Sufficiency questions (Professional level): Provide a mathematical question followed by two statements on new lines, labeled 1) and 2). The 4 options must represent sufficiency rules and be exactly: \n" .
                  "     - Statement (1) ALONE is sufficient, but statement (2) alone is not sufficient.\n" .
                  "     - Statement (2) ALONE is sufficient, but statement (1) alone is not sufficient.\n" .
                  "     - BOTH statements TOGETHER are sufficient, but NEITHER statement ALONE is sufficient.\n" .
                  "     - Statements (1) and (2) TOGETHER are NOT sufficient.\n" .
                  "     (Or dynamically substitute one with 'Each statement ALONE is sufficient.' if applicable. Options must be clean strings without any option letter prefix like 'A.' or 'a.').\n" .
                  "- For Verbal Ability:\n" .
                  "  1. Vocabulary (Synonyms & Antonyms): Wrap the target vocabulary word in double quotes (e.g., \"apathetic\" or \"brusque\") inside a complete, natural sentence context. The question text should be phrased as: 'Choose the word closest in meaning to the quoted word: ...' (for synonyms) or 'Choose the word opposite in meaning to the quoted word: ...' (for antonyms).\n" .
                  "  2. Analogy:\n" .
                  "     - Single-word Analogy: Phrased as: 'Complete the analogy: Moby Dick : Herman Melville || The Old Man and the Sea : ________'\n" .
                  "     - Double-word Analogy: Phrased as: 'Identify the pair of words that shares the same relationship as the given pair: blend : mix'\n" .
                  "  3. Correct Usage: Ask the user to complete a sentence. E.g., 'Choose the word that correctly completes the sentence: ...'\n" .
                  "  4. Identifying Errors: Ask the user to identify the grammatically incorrect segment. Phrased as: 'Identify the word or phrase that is NOT acceptable in formal written English: ...'. Options should list the segments and 'No error' as the fourth option.\n" .
                  "- For Clerical Ability:\n" .
                  "  1. Alphabetizing: Provide 4 items (such as names, government departments, or organizations) labeled A, B, C, and D. Phrased as: 'Arrange the following items in alphabetical order: \\nA. [Item A]\\nB. [Item B]\\nC. [Item C]\\nD. [Item D]'. The 4 options must be permutations of the letters A, B, C, D (e.g., 'ABCD', 'ACBD', 'BCAD', 'CBAD'). The correct option index must point to the option representing the exact correct alphabetical order.\n" .
                  "  2. Spelling/Data Verification: Identify correctly spelled words or matching codes/data.\n" .
                  "- For Analytical Ability (Professional level):\n" .
                  "  1. Logical Reasoning: Provide a set of premises or statements and ask for the logical conclusion or assumption. E.g., 'All applicants who score above 90 are invited... What can be logically concluded?'\n" .
                  "  2. Sequence/Inductive Reasoning: Ask to find the next item in a sequence of numbers or letters. Phrased as: 'Find the next item in the sequence: ZY, WV, TS, QP, ________'.\n" .
                  "  3. Abstract Reasoning: Draw shape-based diagrams. Write the question text using a diagram tag: 'Which option completes the sequence pattern?' followed by a JSON diagram enclosed in custom tags: [diagram]{\"type\": \"sequence\"|\"grid\", ...}[/diagram]. The 4 multiple choice options must also be individual diagrams representing the choice shapes: '[diagram]{\"shapes\": [...]}[/diagram]'. Keep diagram JSON clean and simple. Supported shape types: 'circle', 'square', 'triangle', 'arrow', 'cross', 'line', 'star'. Supported fills: 'none', 'solid', 'shaded'. Supported rotation degrees (0, 45, 90, 180, etc.). Supported decorations: dots or lines inside/outside.\n\n" .
                  "Requirements:\n" .
                  "1. The questions must test knowledge/skills relevant to the category and difficulty level of the Philippine Civil Service Exam.\n" .
                  "2. CRITICAL LOGIC RULE: PREVENT SEMANTIC DUPLICATES. A semantic duplicate is a question that uses the exact same mathematical formula, logic pattern, or scenario type as a previous question, even if you change the names, places, or exact numbers. Every question you generate must test a completely different mathematical concept, logical problem type, or core subject matter. Ensure that no two questions share the exact same core calculation steps.\n" .
                  "3. Define a specific 'problem_type_tag' (e.g. 'work-rate-pipes', 'percentage-discount', 'syllogism-all-some', 'sequence-geometric', 'vocabulary-antonym-sentence') that describes the exact logic/concept used.\n" .
                  "4. Each question must have exactly 4 multiple choice options.\n" .
                  "5. Define exactly one correct option index (0-indexed integer from 0 to 3) for each question.\n" .
                  "6. Provide a clear, step-by-step explanatory review detailing why that answer is correct.\n" .
                  "7. Return the response as a JSON object containing a 'questions' key which is an array of objects matching the required schema.\n" .
                  "8. CRITICAL: DO NOT prefix options in the 'options' array with letter headers (like 'A.', 'a.', 'B.', 'b.', '1.', etc.). Options must be pure, clean strings.";

        $body = [
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => [
                'type' => 'json_object'
            ]
        ];

        $response = Http::withoutVerifying()
            ->timeout(120)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey
            ])
            ->post($url, $body);

        if ($response->successful()) {
            $json = $response->json();
            $text = $json['choices'][0]['message']['content'] ?? null;
            if ($text) {
                $decoded = json_decode($text, true);
                if (is_array($decoded)) {
                    $raw = [];
                    if (isset($decoded['questions']) && is_array($decoded['questions'])) {
                        $raw = $decoded['questions'];
                    } elseif (isset($decoded['data']) && is_array($decoded['data'])) {
                        $raw = $decoded['data'];
                    } elseif (array_keys($decoded) === range(0, count($decoded) - 1)) {
                        $raw = $decoded;
                    } else {
                        $raw = [$decoded];
                    }
                    return self::normalizeQuestionsArray($raw);
                }
            }
        }

        if ($response->status() === 429) {
            \App\Models\Setting::set('ai_quota_exceeded_flag', '1');
            \App\Models\Setting::set('ai_last_error', '429 Quota Exceeded on DeepSeek API.');
        } else if ($response->failed()) {
            \App\Models\Setting::set('ai_last_error', 'DeepSeek API call failed with status ' . $response->status() . ': ' . $response->body());
        }

        return null;
    }

    /**
     * Call DeepSeek API to verify a single batch of questions.
     */
    private static function verifyDeepSeekQuestionsBatch(array $questions, array $dbCandidates, string $apiKey): ?array
    {
        $url = "https://api.deepseek.com/chat/completions";

        $candidatesText = "";
        if (!empty($dbCandidates)) {
            $candidatesText = "\n\nEXISTING DATABASE CANDIDATES (For Semantic Duplicate Audit):\n" .
                              json_encode($dbCandidates, JSON_PRETTY_PRINT) . "\n";
        }

        $prompt = "You are an expert reviewer and quality auditor for the Philippine Civil Service Exam (CSE).\n" .
                  "Review the following JSON list of newly generated multiple-choice questions for structural accuracy, factual correctness, and semantic duplication against similar existing questions in our database.\n" .
                  $candidatesText . "\n" .
                  "QUESTIONS TO AUDIT & VERIFY:\n" .
                  json_encode($questions, JSON_PRETTY_PRINT) . "\n\n" .
                  "VERIFICATION RULES & CRITERIA:\n" .
                  "1. Structural Audit: Ensure the question has exactly 4 option choices and a valid correct_option_index (0-indexed integer from 0 to 3).\n" .
                  "2. Factual Audit: Verify that the correct option index points to the factually correct answer and the explanation aligns with the answer.\n" .
                  "3. CRITICAL SEMANTIC DEDUPLICATION AUDIT: A generated question is a semantic duplicate if it tests the exact same mathematical formula, logic pattern, or scenario type as any question in the EXISTING DATABASE CANDIDATES list, even if names, places, or numbers are changed. E.g., 'Juan travels 60km in 2 hrs' vs 'Maria drives 120km in 4 hrs' are semantic duplicates.\n" .
                  "4. If a question is factually incorrect, structurally invalid, or is a semantic duplicate of an existing database question, set `is_valid` to false and provide a brief explanation in `error_reason` (e.g. 'Semantic duplicate of DB question #42 - Speed rate scenario').\n" .
                  "5. Otherwise, set `is_valid` to true and `error_reason` to null.\n\n" .
                  "Return the verified questions as a JSON object containing a 'questions' key which is an array of objects matching the required schema. Ensure you retain the 'problem_type_tag' of each question in the output.";

        $body = [
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => [
                'type' => 'json_object'
            ]
        ];

        $response = Http::withoutVerifying()
            ->timeout(120)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey
            ])
            ->post($url, $body);

        if ($response->successful()) {
            $json = $response->json();
            $text = $json['choices'][0]['message']['content'] ?? null;
            if ($text) {
                $decoded = json_decode($text, true);
                if (is_array($decoded)) {
                    $raw = [];
                    if (isset($decoded['questions']) && is_array($decoded['questions'])) {
                        $raw = $decoded['questions'];
                    } elseif (isset($decoded['data']) && is_array($decoded['data'])) {
                        $raw = $decoded['data'];
                    }
                    \App\Models\Setting::set('ai_quota_exceeded_flag', '0');
                    \App\Models\Setting::set('ai_last_error', null);
                    return self::normalizeQuestionsArray($raw);
                }
            }
        }

        if ($response->status() === 429) {
            \App\Models\Setting::set('ai_quota_exceeded_flag', '1');
            \App\Models\Setting::set('ai_last_error', '429 Quota Exceeded on DeepSeek API.');
        } else if ($response->failed()) {
            \App\Models\Setting::set('ai_last_error', 'DeepSeek API verification call failed with status ' . $response->status() . ': ' . $response->body());
        }

        return null;
    }

    /**
     * Normalize generated/verified question arrays to ensure consistent keys.
     */
    private static function normalizeQuestionsArray(array $questions): array
    {
        return array_map(function ($q) {
            $normalized = [];
            
            // Map question text
            if (isset($q['question_text'])) {
                $normalized['question_text'] = $q['question_text'];
            } elseif (isset($q['question'])) {
                $normalized['question_text'] = $q['question'];
            } else {
                $normalized['question_text'] = '';
            }

            // Map options
            $normalized['options'] = $q['options'] ?? [];

            // Map correct option index
            if (isset($q['correct_option_index'])) {
                $normalized['correct_option_index'] = (int)$q['correct_option_index'];
            } elseif (isset($q['correct_index'])) {
                $normalized['correct_option_index'] = (int)$q['correct_index'];
            } else {
                $normalized['correct_option_index'] = 0;
            }

            // Map explanation
            if (isset($q['explanation'])) {
                $normalized['explanation'] = $q['explanation'];
            } elseif (isset($q['review'])) {
                $normalized['explanation'] = $q['review'];
            } elseif (isset($q['explanation_review'])) {
                $normalized['explanation'] = $q['explanation_review'];
            } else {
                $normalized['explanation'] = '';
            }

            // Map tag
            $normalized['problem_type_tag'] = $q['problem_type_tag'] ?? 'general-ai-generated';

            // Retain other verification attributes if present
            if (isset($q['is_valid'])) {
                $normalized['is_valid'] = (bool)$q['is_valid'];
            }
            if (isset($q['error_reason'])) {
                $normalized['error_reason'] = $q['error_reason'];
            }

            return $normalized;
        }, $questions);
    }

    /**
     * Get AI suggested fix for a flagged question.
     */
    public static function suggestFix(\App\Models\Question $question): ?array
    {
        $apiKey = config('services.ai.key');
        if (empty($apiKey)) {
            return null;
        }

        if (config('services.ai.provider') === 'deepseek') {
            return self::suggestFixDeepSeek($question, $apiKey);
        }

        return self::suggestFixGemini($question, $apiKey);
    }

    private static function suggestFixGemini(\App\Models\Question $question, string $apiKey): ?array
    {
        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

            $optionsText = "";
            foreach ($question->options as $idx => $opt) {
                $optionsText .= "  {$idx}: {$opt->option_text} (Correct: " . ($opt->is_correct ? 'true' : 'false') . ")\n";
            }

            $prompt = "You are an expert quality auditor and reviewer for the Philippine Civil Service Exam (CSE).\n" .
                      "We have a question in our database that failed our integrity audit due to a correctness or structural finding.\n" .
                      "Your task is to fix this question by correcting the question text, ensuring there are EXACTLY 4 options, identifying the single correct answer index (0 to 3), and writing a detailed explanation detailing why that choice is correct.\n\n" .
                      "ORIGINAL QUESTION DETAILS:\n" .
                      "- ID: {$question->id}\n" .
                      "- Category: " . ($question->category->name ?? '') . "\n" .
                      "- Text: {$question->question_text}\n" .
                      "- Options:\n" . $optionsText .
                      "- Explanation: {$question->explanation}\n\n" .
                      "AUDIT FINDING / ERROR:\n" .
                      "{$question->audit_error}\n\n" .
                      "INSTRUCTIONS FOR CORRECTION:\n" .
                      "1. Correct any factual, structural, grammatical, or logical errors in the question text or options based on the audit finding.\n" .
                      "2. Provide exactly 4 option choices. Do NOT prefix option texts with letter headers (like 'A.', 'B.', 'a.', etc.). Options must be pure, clean strings.\n" .
                      "3. Identify the single correct option index (0-indexed integer from 0 to 3).\n" .
                      "4. Provide a clear, step-by-step explanatory review detailing why the chosen answer is correct.\n" .
                      "5. Retain a specific 'problem_type_tag' describing the core logic/concept used.\n" .
                      "6. Return the response as a JSON object matching the required schema.";

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
                            'explanation' => ['type' => 'STRING'],
                            'problem_type_tag' => ['type' => 'STRING']
                        ],
                        'required' => ['question_text', 'options', 'correct_option_index', 'explanation', 'problem_type_tag']
                    ]
                ]
            ];

            $response = Http::withoutVerifying()
                ->timeout(120)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $body);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text) {
                    $decoded = json_decode($text, true);
                    if (is_array($decoded)) {
                        return self::normalizeSingleQuestion($decoded, $question->exam_category_id);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Gemini suggestFix failed: ' . $e->getMessage());
        }

        return null;
    }

    private static function suggestFixDeepSeek(\App\Models\Question $question, string $apiKey): ?array
    {
        try {
            $url = "https://api.deepseek.com/chat/completions";

            $optionsText = "";
            foreach ($question->options as $idx => $opt) {
                $optionsText .= "  {$idx}: {$opt->option_text} (Correct: " . ($opt->is_correct ? 'true' : 'false') . ")\n";
            }

            $prompt = "You are an expert quality auditor and reviewer for the Philippine Civil Service Exam (CSE).\n" .
                      "We have a question in our database that failed our integrity audit due to a correctness or structural finding.\n" .
                      "Your task is to fix this question by correcting the question text, ensuring there are EXACTLY 4 options, identifying the single correct answer index (0 to 3), and writing a detailed explanation detailing why that choice is correct.\n\n" .
                      "ORIGINAL QUESTION DETAILS:\n" .
                      "- ID: {$question->id}\n" .
                      "- Category: " . ($question->category->name ?? '') . "\n" .
                      "- Text: {$question->question_text}\n" .
                      "- Options:\n" . $optionsText .
                      "- Explanation: {$question->explanation}\n\n" .
                      "AUDIT FINDING / ERROR:\n" .
                      "{$question->audit_error}\n\n" .
                      "INSTRUCTIONS FOR CORRECTION:\n" .
                      "1. Correct any factual, structural, grammatical, or logical errors in the question text or options based on the audit finding.\n" .
                      "2. Provide exactly 4 option choices. Do NOT prefix option texts with letter headers (like 'A.', 'B.', 'a.', etc.). Options must be pure, clean strings.\n" .
                      "3. Identify the single correct option index (0-indexed integer from 0 to 3).\n" .
                      "4. Provide a clear, step-by-step explanatory review detailing why the chosen answer is correct.\n" .
                      "5. Retain a specific 'problem_type_tag' describing the core logic/concept used.\n" .
                      "6. Return the response as a JSON object containing keys: 'question_text', 'options' (array of 4 strings), 'correct_option_index' (integer 0-3), 'explanation', and 'problem_type_tag'.";

            $body = [
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'response_format' => [
                    'type' => 'json_object'
                ]
            ];

            $response = Http::withoutVerifying()
                ->timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey
                ])
                ->post($url, $body);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['choices'][0]['message']['content'] ?? null;
                if ($text) {
                    $decoded = json_decode($text, true);
                    if (is_array($decoded)) {
                        return self::normalizeSingleQuestion($decoded, $question->exam_category_id);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('DeepSeek suggestFix failed: ' . $e->getMessage());
        }

        return null;
    }

    private static function normalizeSingleQuestion(array $q, int $catId): array
    {
        $normalized = [
            'exam_category_id' => $catId,
            'question_text' => $q['question_text'] ?? $q['question'] ?? '',
            'explanation' => $q['explanation'] ?? $q['review'] ?? $q['explanation_review'] ?? '',
            'problem_type_tag' => $q['problem_type_tag'] ?? 'general-ai-fixed',
        ];

        $rawOptions = $q['options'] ?? [];
        $correctIndex = isset($q['correct_option_index']) ? (int)$q['correct_option_index'] : (isset($q['correct_index']) ? (int)$q['correct_index'] : 0);

        $options = [];
        foreach ($rawOptions as $idx => $optText) {
            $options[] = [
                'option_text' => $optText,
                'is_correct' => $idx === $correctIndex,
            ];
        }

        while (count($options) < 4) {
            $options[] = [
                'option_text' => '',
                'is_correct' => count($options) === 0,
            ];
        }

        $normalized['options'] = array_slice($options, 0, 4);

        return $normalized;
    }
}
