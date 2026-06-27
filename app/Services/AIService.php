<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    /**
     * Registry of all official Civil Service Exam subtopics.
     */
    public static function getSyllabusRegistry(): array
    {
        return [
            'Numerical Ability' => [
                'professional' => [
                    [
                        'tag' => 'numerical-work-rate',
                        'guideline' => 'A word problem testing joint work rate, e.g. two workers or pipes completing a task together.'
                    ],
                    [
                        'tag' => 'numerical-speed-distance',
                        'guideline' => 'A word problem testing speed, distance, time, relative motion, or average speed.'
                    ],
                    [
                        'tag' => 'numerical-percentage-discount',
                        'guideline' => 'A word problem testing percentages, retail markups, discounts, profit/loss, or sales tax.'
                    ],
                    [
                        'tag' => 'numerical-interest-investment',
                        'guideline' => 'A word problem testing simple or compound interest, loans, or returns on investments.'
                    ],
                    [
                        'tag' => 'numerical-age-problem',
                        'guideline' => 'A word problem calculating ages of people at different points in time.'
                    ],
                    [
                        'tag' => 'numerical-sequence-completion',
                        'guideline' => 'A sequence of numbers where the user must determine the logical next number.'
                    ],
                    [
                        'tag' => 'numerical-ratio-proportion',
                        'guideline' => 'A word problem testing ratios, direct or inverse proportions, or mixture problems.'
                    ],
                    [
                        'tag' => 'numerical-geometry-measurement',
                        'guideline' => 'A word problem testing perimeter, area, volume, or geometric relationships.'
                    ],
                    [
                        'tag' => 'numerical-algebraic-equations',
                        'guideline' => 'Solving systems of linear equations or basic algebraic word problems.'
                    ],
                    [
                        'tag' => 'numerical-data-sufficiency',
                        'guideline' => 'A Data Sufficiency problem. You must provide a math question followed by two numbered statements, 1) and 2). The four options must strictly represent the standard sufficiency choices.'
                    ]
                ],
                'sub_professional' => [
                    [
                        'tag' => 'numerical-basic-arithmetic',
                        'guideline' => 'A basic arithmetic calculation testing PEMDAS order of operations.'
                    ],
                    [
                        'tag' => 'numerical-fraction-operations',
                        'guideline' => 'A problem requiring addition, subtraction, multiplication, or division of fractions or mixed numbers.'
                    ],
                    [
                        'tag' => 'numerical-decimal-percentage',
                        'guideline' => 'A basic problem on decimal arithmetic or simple percentage calculations.'
                    ],
                    [
                        'tag' => 'numerical-basic-averages',
                        'guideline' => 'Calculating the arithmetic mean, weighted average, or simple rates.'
                    ],
                    [
                        'tag' => 'numerical-simple-word-problems',
                        'guideline' => 'A simple everyday math word problem (e.g. calculating total cost, simple discounts, or change from a transaction).'
                    ]
                ]
            ],
            'Verbal Ability' => [
                'professional' => [
                    [
                        'tag' => 'verbal-synonym-context',
                        'guideline' => 'Choose the word closest in meaning to a vocabulary word wrapped in double quotes in a formal context sentence.'
                    ],
                    [
                        'tag' => 'verbal-antonym-context',
                        'guideline' => 'Choose the word opposite in meaning to a vocabulary word wrapped in double quotes in a formal context sentence.'
                    ],
                    [
                        'tag' => 'verbal-single-analogy',
                        'guideline' => 'Complete a single-word analogy (e.g., A : B || C : ________).'
                    ],
                    [
                        'tag' => 'verbal-double-analogy',
                        'guideline' => 'Identify the pair of words that shares the same relationship as the given pair.'
                    ],
                    [
                        'tag' => 'verbal-identifying-errors',
                        'guideline' => 'Identify the grammatically incorrect segment of a sentence, with "No error" as the fourth option.'
                    ],
                    [
                        'tag' => 'verbal-paragraph-organization',
                        'guideline' => 'Reorder five sentences (labeled A, B, C, D, E or numbered) to form a coherent paragraph. The question should ask for the correct order.'
                    ],
                    [
                        'tag' => 'verbal-reading-comprehension',
                        'guideline' => 'Read a formal, technical, or legislative passage and answer a comprehension/inference question about it.'
                    ],
                    [
                        'tag' => 'verbal-correct-usage',
                        'guideline' => 'Complete a sentence by choosing the grammatically correct word/phrase (subject-verb agreement, tenses, subjunction).'
                    ]
                ],
                'sub_professional' => [
                    [
                        'tag' => 'verbal-spelling-verification',
                        'guideline' => 'Identify the correctly or incorrectly spelled word from commonly confused words.'
                    ],
                    [
                        'tag' => 'verbal-simple-synonym',
                        'guideline' => 'Identify the synonym of a word in a simple sentence.'
                    ],
                    [
                        'tag' => 'verbal-simple-antonym',
                        'guideline' => 'Identify the antonym of a word in a simple sentence.'
                    ],
                    [
                        'tag' => 'verbal-single-analogy-sub',
                        'guideline' => 'Complete a basic single analogy.'
                    ],
                    [
                        'tag' => 'verbal-paragraph-org-sub',
                        'guideline' => 'Reorder a simple narrative paragraph.'
                    ],
                    [
                        'tag' => 'verbal-correct-usage-sub',
                        'guideline' => 'Choose the correct word completion, particularly testing common homophones like their/there/they\'re.'
                    ],
                    [
                        'tag' => 'verbal-reading-comprehension-sub',
                        'guideline' => 'Read a short narrative passage and answer a basic reading comprehension question.'
                    ]
                ]
            ],
            'Analytical Ability' => [
                'professional' => [
                    [
                        'tag' => 'analytical-logical-syllogism',
                        'guideline' => 'A logical reasoning problem requiring the user to draw valid conclusions from two or three premises (syllogisms).'
                    ],
                    [
                        'tag' => 'analytical-identifying-assumptions',
                        'guideline' => 'Identify the unstated assumption in a short argument or statement.'
                    ],
                    [
                        'tag' => 'analytical-word-association',
                        'guideline' => 'Identify the word or pair that does not belong, or represents a specific association.'
                    ],
                    [
                        'tag' => 'analytical-number-letter-sequence',
                        'guideline' => 'Complete a logical sequence of letters, numbers, or alphanumeric characters.'
                    ],
                    [
                        'tag' => 'analytical-abstract-reasoning',
                        'guideline' => 'A visual pattern problem using shape codes. You must provide a JSON pattern diagram enclosed in [diagram]...[/diagram] tags.'
                    ]
                ],
                'sub_professional' => []
            ],
            'Clerical Ability' => [
                'professional' => [],
                'sub_professional' => [
                    [
                        'tag' => 'clerical-alphabetizing-names',
                        'guideline' => 'Arrange four names in alphabetical order (Surname, First Name format) and identify the correct filing order.'
                    ],
                    [
                        'tag' => 'clerical-alphabetizing-filing',
                        'guideline' => 'Alphabetical ordering of government offices, departments, or organizations.'
                    ],
                    [
                        'tag' => 'clerical-spelling-rules',
                        'guideline' => 'Verify spelling correctness according to standard clerical rules (handling suffixes, double consonants).'
                    ],
                    [
                        'tag' => 'clerical-coding-substitution',
                        'guideline' => 'Substitute letters or words with numerical codes based on a given key.'
                    ],
                    [
                        'tag' => 'clerical-data-verification',
                        'guideline' => 'Verify whether two sets of records (names, numbers, or addresses) are exactly the same or different.'
                    ]
                ]
            ],
            'General Information' => [
                'professional' => [
                    [
                        'tag' => 'general-info-constitution-rights',
                        'guideline' => 'Test knowledge of Article III (Bill of Rights) of the 1987 Philippine Constitution.'
                    ],
                    [
                        'tag' => 'general-info-constitution-structure',
                        'guideline' => 'Test knowledge of the branches of the Philippine government, term limits, or key provisions of the 1987 Constitution.'
                    ],
                    [
                        'tag' => 'general-info-ra6713-conduct',
                        'guideline' => 'Test knowledge of Republic Act No. 6713 (Code of Conduct and Ethical Standards for Public Officials and Employees).'
                    ],
                    [
                        'tag' => 'general-info-peace-human-rights',
                        'guideline' => 'Test basic concepts of peace education, human rights issues, or civic responsibilities.'
                    ],
                    [
                        'tag' => 'general-info-environmental-concepts',
                        'guideline' => 'Test knowledge of environmental laws, climate change, conservation, and resource protection.'
                    ]
                ],
                'sub_professional' => [
                    [
                        'tag' => 'general-info-constitution-rights',
                        'guideline' => 'Test basic knowledge of Article III (Bill of Rights) of the 1987 Philippine Constitution.'
                    ],
                    [
                        'tag' => 'general-info-ra6713-conduct',
                        'guideline' => 'Test knowledge of basic duties and prohibitions under RA 6713.'
                    ],
                    [
                        'tag' => 'general-info-peace-human-rights',
                        'guideline' => 'Test basic peace and human rights concepts.'
                    ],
                    [
                        'tag' => 'general-info-environmental-concepts',
                        'guideline' => 'Test environmental awareness, clean air act, or recycling rules.'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get the least-represented subtopics for the given category and level.
     */
    public static function getTargetSubtopics(string $categoryName, string $level, int $batchSize): array
    {
        $registry = self::getSyllabusRegistry();
        $categorySubtopics = $registry[$categoryName] ?? null;

        if (!$categorySubtopics) {
            return [];
        }

        $subtopics = $categorySubtopics[$level] ?? [];
        if (empty($subtopics)) {
            $subtopics = $categorySubtopics['professional'] ?? $categorySubtopics['sub_professional'] ?? [];
        }

        if (empty($subtopics)) {
            return [];
        }

        $subtopicTags = array_column($subtopics, 'tag');
        $counts = [];

        try {
            $cat = \App\Models\ExamCategory::where('name', $categoryName)->first();
            if ($cat) {
                $counts = \App\Models\Question::where('exam_category_id', $cat->id)
                    ->whereIn('problem_type_tag', $subtopicTags)
                    ->groupBy('problem_type_tag')
                    ->selectRaw('problem_type_tag, count(*) as count')
                    ->pluck('count', 'problem_type_tag')
                    ->toArray();
            }
        } catch (\Exception $e) {
            Log::warning("Failed to count subtopics from database: " . $e->getMessage());
        }

        $subtopicsWithCounts = array_map(function ($subtopic) use ($counts) {
            $subtopic['count'] = $counts[$subtopic['tag']] ?? 0;
            return $subtopic;
        }, $subtopics);

        // Sort by count ascending, using a stable/random secondary factor to prevent duplicate-pattern generation
        usort($subtopicsWithCounts, function ($a, $b) {
            if ($a['count'] === $b['count']) {
                return rand(-1, 1);
            }
            return $a['count'] <=> $b['count'];
        });

        $selected = [];
        for ($i = 0; $i < $batchSize; $i++) {
            $selected[] = $subtopicsWithCounts[$i % count($subtopicsWithCounts)];
        }

        return $selected;
    }

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
                $provider = config('services.ai.provider', 'gemini');
                $allQuestions = [];

                // Retrieve the targeted subtopics (underrepresented ones) from the registry.
                // We fetch $count subtopics so each single-item call gets a distinct subtopic.
                $selectedSubtopics = self::getTargetSubtopics($categoryName, $level, $count);

                for ($i = 0; $i < $count; $i++) {
                    $inProgressTexts = array_map(function ($q) {
                        return strtolower(trim(preg_replace('/\s*\(Variation ID:\s*\d+\)/i', '', $q['question_text'])));
                    }, $allQuestions);

                    $cleanedExtra = array_map(function ($text) {
                        return strtolower(trim(preg_replace('/\s*\(Variation ID:\s*\d+\)/i', '', $text)));
                    }, $extraExcludeTexts);

                    $mergedExclude = array_unique(array_merge($inProgressTexts, $cleanedExtra));

                    // Get the subtopic for this sequential item
                    $subtopic = $selectedSubtopics[$i % count($selectedSubtopics)] ?? null;
                    if (!$subtopic) {
                        $validator = \Illuminate\Support\Facades\Validator::make([], []);
                        $validator->errors()->add('category', 'Target subtopic could not be resolved.');
                        throw new \Illuminate\Validation\ValidationException($validator);
                    }

                    $subtopicTag = $subtopic['tag'];

                    // Query the database to find the least-used reference question for this subtopic and level.
                    // We join with the generated questions count to find the least-used.
                    $reference = \App\Models\ReferenceQuestion::where('subtopic_tag', $subtopicTag)
                        ->where('level', $level)
                        ->withCount('generatedQuestions')
                        ->orderBy('generated_questions_count', 'asc')
                        ->first();

                    if (!$reference) {
                        // Throw validation exception as strictly requested by the user
                        $validator = \Illuminate\Support\Facades\Validator::make([], []);
                        $validator->errors()->add('reference', "No reference question found in the database for subtopic: '{$subtopicTag}' (level: '{$level}').");
                        throw new \Illuminate\Validation\ValidationException($validator);
                    }

                    $uniquenessToken = "seed-" . ($seedOffset + $i) . "-" . bin2hex(random_bytes(4));

                    $prompt = self::buildElitePrompt($categoryName, $subtopic['guideline'], $uniquenessToken, $mergedExclude, $reference);

                    if ($provider === 'groq') {
                        $response = self::callGroqApiSingle($prompt, $apiKey, $subtopicTag);
                    } else {
                        $response = self::callGeminiApiSingle($prompt, $apiKey, $subtopicTag);
                    }

                    if ($response) {
                        $response['reference_question_id'] = $reference->id;
                        $allQuestions[] = $response;
                    } else {
                        // If any sequential generation fails, throw to fallback
                        throw new \Exception("Sequential question generation returned empty or invalid response.");
                    }
                }

                if (count($allQuestions) > 0) {
                    return $allQuestions;
                }
            } catch (\Illuminate\Validation\ValidationException $ve) {
                // Reraise validation exceptions so they reach the controller and user
                throw $ve;
            } catch (\Exception $e) {
                $providerName = ucfirst(config('services.ai.provider', 'gemini'));
                Log::error("{$providerName} API sequential call failed, falling back to mock: " . $e->getMessage());
            }
        }

        // Fallback to mock generation
        return self::generateMockQuestions($categoryName, $level, $count, $seedOffset);
    }

    /**
     * Build the user-defined Elite Psychometrician prompt format.
     */
    public static function buildElitePrompt(string $majorSection, string $subTopic, string $uniquenessToken, array $exclusionList, \App\Models\ReferenceQuestion $reference): string
    {
        $exclusionText = "";
        if (!empty($exclusionList)) {
            foreach ($exclusionList as $item) {
                $exclusionText .= "- " . trim($item) . "\n";
            }
        } else {
            $exclusionText = "None. Feel free to use any standard scenario or formula.";
        }

        $refOptions = $reference->options;
        $refOptionsText = "- a: " . ($refOptions[0] ?? $refOptions['a'] ?? '') . "\n" .
                         "- b: " . ($refOptions[1] ?? $refOptions['b'] ?? '') . "\n" .
                         "- c: " . ($refOptions[2] ?? $refOptions['c'] ?? '') . "\n" .
                         "- d: " . ($refOptions[3] ?? $refOptions['d'] ?? '') . "\n";

        $refCorrectLetter = 'a';
        if ($reference->correct_option_index === 1) $refCorrectLetter = 'b';
        elseif ($reference->correct_option_index === 2) $refCorrectLetter = 'c';
        elseif ($reference->correct_option_index === 3) $refCorrectLetter = 'd';

        return "You are an elite psychometrician and automated item writer for the Civil Service Examination (CSE). Your primary objective is to generate highly distinct, high-quality multiple-choice questions that have zero thematic or logical overlap with past entries.

### INPUT VARIABLE SCHEMA
Target Major Section: {$majorSection}
Target Sub-Topic: {$subTopic}
Entropy Seed: {$uniquenessToken}

[REFERENCE_QUESTION]
Here is a real exam question testing this concept:
Question Text: {$reference->question_text}
Options:
{$refOptionsText}Correct Answer: {$refCorrectLetter}
Explanation: {$reference->explanation}

[EXCLUSION_LIST]
{$exclusionText}

### PHASED EXECUTION LOGIC
You must run through the following cognitive phases before generating your output:

1. Phase I (Deconstruction): Analyze the [EXCLUSION_LIST] and the [REFERENCE_QUESTION]. Pinpoint the exact mathematical formulas, scenarios, or grammatical mechanics already utilized. Blacklist those specific paths.
2. Phase II (Concept Pivoting): Generate a brand new, highly distinct question that tests the EXACT same concept, logic structure, and difficulty as the [REFERENCE_QUESTION], but mutate the situation, context, variables, names, and phrasing completely. Use the \"Entropy Seed\" to forcefully mutate variables, situational contexts, names, and structural phrasing. The new question must feel like an official, real exam question of the same caliber.

### GENERATION RULES
- Distractor Quality: Distractors must reflect common cognitive errors (e.g., misapplying order of operations, common grammatical misconceptions). Do not write obvious or nonsensical distractors.
- Self-Contained: Do not reference your internal execution steps or the exclusion list anywhere in your final responses.

### OUTPUT FORMAT
You must return your response strictly as a valid JSON object matching the exact structure below. Do not wrap the JSON object in markdown blocks (e.g., do not use ```json). Ensure all special characters within text strings are properly escaped.

{
  \"question\": {
    \"question_text\": \"Clear, concise, and grammatically perfect question stem.\",
    \"options\": {
      \"a\": \"Distractor A\",
      \"b\": \"Distractor B\",
      \"c\": \"Distractor C\",
      \"d\": \"Correct Choice\"
    },
    \"correct_answer\": \"d\",
    \"concept_fingerprint\": \"A dense 3-to-5 word comma-separated tag summarizing the exact underlying mechanic used.\",
    \"explanation\": \"Detailed professional rationale explaining why the correct answer is valid and why the specific distractors are incorrect.\"
  }
}";
    }

    /**
     * Clean JSON markdown wrappers if present.
     */
    private static function cleanJsonResponse(string $text): string
    {
        $text = preg_replace('/^```(?:json)?/i', '', $text);
        $text = preg_replace('/```$/', '', $text);
        return trim($text);
    }

    /**
     * Parser to map Elite prompt output JSON structures onto our database structure.
     */
    public static function parseEliteResponse(array $decoded, string $fallbackTag): ?array
    {
        $qData = $decoded['question'] ?? $decoded;

        if (!isset($qData['question_text']) || !isset($qData['options'])) {
            return null;
        }

        $questionText = $qData['question_text'];
        $rawOptions = $qData['options'];

        $optionsKeys = ['a', 'b', 'c', 'd'];
        $options = [];
        foreach ($optionsKeys as $key) {
            if (isset($rawOptions[$key])) {
                $options[] = (string)$rawOptions[$key];
            }
        }

        if (count($options) < 4) {
            $options = array_values(array_map('strval', $rawOptions));
        }

        if (count($options) !== 4) {
            return null;
        }

        $correctAnswer = strtolower(trim($qData['correct_answer'] ?? 'a'));
        $correctIndex = array_search($correctAnswer, $optionsKeys);
        if ($correctIndex === false) {
            $correctIndex = is_numeric($correctAnswer) ? (int)$correctAnswer : 0;
        }

        $explanation = $qData['explanation'] ?? '';
        $tag = $qData['concept_fingerprint'] ?? $fallbackTag;

        return [
            'question_text' => $questionText,
            'options' => $options,
            'correct_option_index' => $correctIndex,
            'explanation' => $explanation,
            'problem_type_tag' => $tag,
        ];
    }

    /**
     * Call Google Gemini API to generate a single structured question.
     */
    private static function callGeminiApiSingle(string $prompt, string $apiKey, string $fallbackTag): ?array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

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
                'temperature' => 0.85,
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'question' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'question_text' => ['type' => 'STRING'],
                                'options' => [
                                    'type' => 'OBJECT',
                                    'properties' => [
                                        'a' => ['type' => 'STRING'],
                                        'b' => ['type' => 'STRING'],
                                        'c' => ['type' => 'STRING'],
                                        'd' => ['type' => 'STRING']
                                    ],
                                    'required' => ['a', 'b', 'c', 'd']
                                ],
                                'correct_answer' => ['type' => 'STRING', 'description' => 'a, b, c, or d'],
                                'concept_fingerprint' => ['type' => 'STRING'],
                                'explanation' => ['type' => 'STRING']
                            ],
                            'required' => ['question_text', 'options', 'correct_answer', 'concept_fingerprint', 'explanation']
                        ]
                    ],
                    'required' => ['question']
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
                $cleanedText = self::cleanJsonResponse($text);
                $decoded = json_decode($cleanedText, true);
                if (is_array($decoded)) {
                    \App\Models\Setting::set('ai_quota_exceeded_flag', '0');
                    \App\Models\Setting::set('ai_last_error', null);
                    $parsed = self::parseEliteResponse($decoded, $fallbackTag);
                    if ($parsed) {
                        return $parsed;
                    }
                }
            }
        }

        if ($response->status() === 429) {
            \App\Models\Setting::set('ai_quota_exceeded_flag', '1');
            \App\Models\Setting::set('ai_last_error', '429 Quota Exceeded (Free Tier limit met or billing issue).');
        } else if ($response->failed()) {
            \App\Models\Setting::set('ai_last_error', 'Gemini API call failed with status ' . $response->status());
        }

        Log::warning('Gemini API single response format was invalid or failed: ' . $response->body());
        return null;
    }

    private static function generateMockQuestions(string $categoryName, string $level, int $count, int $seedOffset = 0): array
    {
        $questionsList = [];

        for ($i = 0; $i < $count; $i++) {
            $q = self::getSingleMockQuestion($categoryName, $level, $i + $seedOffset);
            if (app()->environment('testing')) {
                $q['question_text'] .= " [Mock ID: " . ($i + $seedOffset) . "]";
            }
            $questionsList[] = $q;
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
        return array_map(function ($q) {
            $q['is_valid'] = true;
            $q['error_reason'] = null;
            return $q;
        }, $questions);
    }

    /**
     * Call Gemini API to verify a single batch of questions.
     */
    private static function verifyGeminiQuestionsBatch(array $questions, array $dbCandidates, string $apiKey): ?array
    {

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
     * Call Groq API to generate a single structured question.
     */
    private static function callGroqApiSingle(string $prompt, string $apiKey, string $fallbackTag): ?array
    {
        $url = "https://api.groq.com/openai/v1/chat/completions";

        $body = [
            'model' => config('services.ai.model', 'llama-3.3-70b-versatile'),
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => [
                'type' => 'json_object'
            ],
            'temperature' => 0.85
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
                $cleanedText = self::cleanJsonResponse($text);
                $decoded = json_decode($cleanedText, true);
                if (is_array($decoded)) {
                    \App\Models\Setting::set('ai_quota_exceeded_flag', '0');
                    \App\Models\Setting::set('ai_last_error', null);
                    $parsed = self::parseEliteResponse($decoded, $fallbackTag);
                    if ($parsed) {
                        return $parsed;
                    }
                }
            }
        }

        if ($response->status() === 429) {
            \App\Models\Setting::set('ai_quota_exceeded_flag', '1');
            \App\Models\Setting::set('ai_last_error', '429 Quota Exceeded on Groq API.');
        } else if ($response->failed()) {
            \App\Models\Setting::set('ai_last_error', 'Groq API call failed with status ' . $response->status() . ': ' . $response->body());
        }

        return null;
    }

    /**
     * Call Groq API to verify a single batch of questions.
     */
    private static function verifyGroqQuestionsBatch(array $questions, array $dbCandidates, string $apiKey): ?array
    {
        $url = "https://api.groq.com/openai/v1/chat/completions";

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
            'model' => config('services.ai.model', 'llama-3.3-70b-versatile'),
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
            \App\Models\Setting::set('ai_last_error', '429 Quota Exceeded on Groq API.');
        } else if ($response->failed()) {
            \App\Models\Setting::set('ai_last_error', 'Groq API verification call failed with status ' . $response->status() . ': ' . $response->body());
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

            // Map options - ensure they are always flat strings
            $rawOptions = $q['options'] ?? [];
            $cleanOptions = [];
            foreach ($rawOptions as $opt) {
                if (is_array($opt)) {
                    $cleanOptions[] = (string)($opt['option_text'] ?? $opt['text'] ?? $opt['option'] ?? $opt['value'] ?? json_encode($opt));
                } else if (is_object($opt)) {
                    $cleanOptions[] = (string)($opt->option_text ?? $opt->text ?? $opt->option ?? $opt->value ?? json_encode($opt));
                } else {
                    $cleanOptions[] = (string)$opt;
                }
            }
            $normalized['options'] = $cleanOptions;

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

        if (config('services.ai.provider') === 'groq') {
            return self::suggestFixGroq($question, $apiKey);
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

    private static function suggestFixGroq(\App\Models\Question $question, string $apiKey): ?array
    {
        try {
            $url = "https://api.groq.com/openai/v1/chat/completions";

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
                'model' => config('services.ai.model', 'llama-3.3-70b-versatile'),
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
            Log::error('Groq suggestFix failed: ' . $e->getMessage());
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
