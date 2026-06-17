<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Services\AIService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminQuestionController extends Controller
{
    /**
     * Check if the authenticated user is an admin.
     */
    private function authorizeAdmin(): void
    {
        if (!Auth::check() || !Auth::user()->is_admin) {
            abort(403, 'Unauthorized access. Admins only.');
        }
    }

    /**
     * Display a list of all questions and categories.
     */
    public function index()
    {
        $this->authorizeAdmin();

        $questions = Question::with(['category', 'options'])->orderBy('created_at', 'desc')->get();
        
        // Identify duplicate questions globally (case-insensitive, trimmed, Variation ID stripped) in PHP
        $strippedTexts = $questions->map(function ($q) {
            return strtolower(trim(preg_replace('/\s*\(Variation ID:\s*\d+\)/i', '', $q->question_text)));
        });

        $counts = array_count_values($strippedTexts->toArray());

        $questions->each(function ($q) use ($counts) {
            $stripped = strtolower(trim(preg_replace('/\s*\(Variation ID:\s*\d+\)/i', '', $q->question_text)));
            $q->is_duplicate = isset($counts[$stripped]) && $counts[$stripped] > 1;
        });

        $categories = ExamCategory::all();

        $apiKey = config('services.ai.key');
        $aiUsage = [
            'count' => (int)\App\Models\Setting::get('ai_generated_count', 0),
            'limit' => (int)\App\Models\Setting::get('ai_generated_limit', 500),
            'quotaExceeded' => (bool)\App\Models\Setting::get('ai_quota_exceeded_flag', false),
            'lastError' => \App\Models\Setting::get('ai_last_error', null),
            'hasApiKey' => !empty($apiKey) && strlen($apiKey) > 10 && !str_contains(strtolower($apiKey), 'your_'),
        ];

        return Inertia::render('Admin/Questions', [
            'questions' => $questions,
            'categories' => $categories,
            'aiUsage' => $aiUsage,
        ]);
    }

    /**
     * Store a newly created question in the database.
     */
    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'exam_category_id' => 'required|exists:exam_categories,id',
            'question_text' => 'required|string',
            'explanation' => 'nullable|string',
            'options' => 'required|array|min:2',
            'options.*.option_text' => 'required|string',
            'options.*.is_correct' => 'required|boolean',
        ]);

        // Validate that at least one option is correct
        $hasCorrect = false;
        foreach ($request->options as $opt) {
            if ($opt['is_correct']) {
                $hasCorrect = true;
                break;
            }
        }

        if (!$hasCorrect) {
            return back()->withErrors(['options' => 'At least one option must be marked as correct.']);
        }

        DB::transaction(function () use ($request) {
            $question = Question::create([
                'exam_category_id' => $request->exam_category_id,
                'question_text' => $request->question_text,
                'explanation' => $request->explanation,
            ]);

            foreach ($request->options as $opt) {
                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => $opt['option_text'],
                    'is_correct' => $opt['is_correct'],
                ]);
            }
        });

        return redirect()->route('admin.questions.index')->with('success', 'Question created successfully.');
    }

    /**
     * Update the specified question in the database.
     */
    public function update(Request $request, Question $question)
    {
        $this->authorizeAdmin();

        $request->validate([
            'exam_category_id' => 'required|exists:exam_categories,id',
            'question_text' => 'required|string',
            'explanation' => 'nullable|string',
            'options' => 'required|array|min:2',
            'options.*.option_text' => 'required|string',
            'options.*.is_correct' => 'required|boolean',
        ]);

        $hasCorrect = false;
        foreach ($request->options as $opt) {
            if ($opt['is_correct']) {
                $hasCorrect = true;
                break;
            }
        }

        if (!$hasCorrect) {
            return back()->withErrors(['options' => 'At least one option must be marked as correct.']);
        }

        DB::transaction(function () use ($request, $question) {
            $question->update([
                'exam_category_id' => $request->exam_category_id,
                'question_text' => $request->question_text,
                'explanation' => $request->explanation,
                'audit_status' => 'passed',
                'audit_error' => null,
            ]);

            // Simple update: delete existing options and recreate
            $question->options()->delete();

            foreach ($request->options as $opt) {
                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => $opt['option_text'],
                    'is_correct' => $opt['is_correct'],
                ]);
            }
        });

        return redirect()->route('admin.questions.index')->with('success', 'Question updated successfully.');
    }

    /**
     * Remove the specified question from the database.
     */
    public function destroy(Question $question)
    {
        $this->authorizeAdmin();

        $question->delete();

        return redirect()->route('admin.questions.index')->with('success', 'Question deleted successfully.');
    }

    public function generateAI(Request $request)
    {
        $this->authorizeAdmin();

        // Prevent php request execution timeout for long-running AI generation batches
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        $request->validate([
            'exam_category_id' => 'required',
            'level' => 'required|in:professional,sub_professional',
            'count' => 'required|integer|in:1,5,10,15,20,30,40,50,150',
        ]);

        if ($request->exam_category_id === 'all') {
            $categories = ExamCategory::where('level', 'both')
                ->orWhere('level', $request->level)
                ->get();
        } else {
            $categories = collect([ExamCategory::findOrFail($request->exam_category_id)]);
        }

        $count = (int)$request->count;
        $categoryCount = $categories->count();
        $baseCount = (int)($count / $categoryCount);
        $remainder = $count % $categoryCount;

        $distributions = [];
        foreach ($categories as $index => $cat) {
            $catCount = $baseCount + ($index < $remainder ? 1 : 0);
            if ($catCount > 0) {
                $distributions[] = [
                    'category' => $cat,
                    'count' => $catCount,
                ];
            }
        }

        $totalSavedCount = 0;
        $totalSkippedCount = 0;

        foreach ($distributions as $dist) {
            $category = $dist['category'];
            $catTargetCount = $dist['count'];

            $savedCount = 0;
            $totalSkipped = 0;
            $maxAttempts = 3;
            $discardedTexts = [];

            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                $remaining = $catTargetCount - $savedCount;
                if ($remaining <= 0) {
                    break;
                }

                // Generate seed offset to ensure unique questions across subsequent requests and attempts
                if (app()->environment('testing')) {
                    $seedOffset = $attempt * $catTargetCount;
                } else {
                    $seedOffset = (int)(microtime(true) * 1000) % 1000000 + rand(1000, 9999) + ($attempt * 100);
                }

                // Call AI Service for bulk generation, passing discarded texts to exclude
                $questionsList = AIService::generateQuestions($category->name, $request->level, $remaining, $seedOffset, $discardedTexts);

                // Extract keywords from all generated tags to fetch potential database duplicate candidates
                $keywords = [];
                foreach ($questionsList as $q) {
                    if (!empty($q['problem_type_tag'])) {
                        $parts = explode('-', strtolower($q['problem_type_tag']));
                        foreach ($parts as $part) {
                            $part = trim($part);
                            if (strlen($part) > 2) {
                                $keywords[] = $part;
                            }
                        }
                    }
                }
                $keywords = array_unique($keywords);

                $dbCandidates = [];
                if (!empty($keywords)) {
                    $dbCandidates = Question::where('exam_category_id', $category->id)
                        ->where(function ($query) use ($keywords) {
                            foreach ($keywords as $kw) {
                                $query->orWhere('problem_type_tag', 'LIKE', '%' . $kw . '%');
                            }
                        })
                        ->take(30)
                        ->get(['id', 'question_text', 'problem_type_tag'])
                        ->toArray();
                }

                // Verify the questions structurally, factually, and check for semantic duplicates
                $verifiedQuestions = AIService::verifyQuestions($questionsList, $dbCandidates);

                $seenInBatch = [];
                DB::transaction(function () use ($verifiedQuestions, $category, &$totalSkipped, &$savedCount, &$seenInBatch, &$discardedTexts) {
                    foreach ($verifiedQuestions as $generated) {
                        // Factual check: Skip if the AI auditor marked it as invalid
                        if (isset($generated['is_valid']) && !$generated['is_valid']) {
                            $totalSkipped++;
                            $discardedTexts[] = $generated['question_text'];
                            continue;
                        }

                        // Structural checks: Ensure exactly 4 options and a valid correct_option_index
                        if (!isset($generated['options']) || count($generated['options']) !== 4) {
                            $totalSkipped++;
                            $discardedTexts[] = $generated['question_text'];
                            continue;
                        }

                        if (!isset($generated['correct_option_index']) || $generated['correct_option_index'] < 0 || $generated['correct_option_index'] > 3) {
                            $totalSkipped++;
                            $discardedTexts[] = $generated['question_text'];
                            continue;
                        }

                        $cleanText = strtolower(trim($generated['question_text']));

                        // Avoid duplicate questions within the same generation batch
                        if (in_array($cleanText, $seenInBatch)) {
                            $totalSkipped++;
                            $discardedTexts[] = $generated['question_text'];
                            continue;
                        }

                        // Avoid duplicates against database questions using exact match (to preserve mock generation unique variation IDs)
                        $exists = Question::whereRaw('LOWER(TRIM(question_text)) = ?', [$cleanText])->exists();

                        if ($exists) {
                            $totalSkipped++;
                            $discardedTexts[] = $generated['question_text'];
                            continue;
                        }

                        $seenInBatch[] = $cleanText;

                        $question = Question::create([
                            'exam_category_id' => $category->id,
                            'question_text' => $generated['question_text'],
                            'explanation' => $generated['explanation'] ?? null,
                            'audit_status' => 'passed',
                            'problem_type_tag' => $generated['problem_type_tag'] ?? null,
                        ]);

                        foreach ($generated['options'] as $idx => $optText) {
                            QuestionOption::create([
                                'question_id' => $question->id,
                                'option_text' => $optText,
                                'is_correct' => $idx === (int)$generated['correct_option_index'],
                            ]);
                        }
                        $savedCount++;
                    }
                });
            }

            $totalSavedCount += $savedCount;
            $totalSkippedCount += $totalSkipped;
        }

        if ($totalSavedCount > 0) {
            $currentCount = (int)\App\Models\Setting::get('ai_generated_count', 0);
            \App\Models\Setting::set('ai_generated_count', $currentCount + $totalSavedCount);
        }

        if ($totalSavedCount === $count) {
            return redirect()->route('admin.questions.index')->with('success', "{$count} questions generated and saved successfully using AI.");
        }

        return redirect()->route('admin.questions.index')->with('success', "{$totalSavedCount} questions generated successfully using AI. {$totalSkippedCount} invalid or duplicate question(s) were skipped.");
    }

    public function cleanDuplicates()
    {
        $this->authorizeAdmin();

        $questions = Question::all();
        $grouped = [];

        foreach ($questions as $q) {
            $stripped = preg_replace('/\s*\(Variation ID:\s*\d+\)/i', '', $q->question_text);
            $key = strtolower(trim($stripped));
            $grouped[$key][] = $q;
        }

        $deletedCount = 0;

        DB::transaction(function () use ($grouped, &$deletedCount) {
            foreach ($grouped as $key => $group) {
                if (count($group) > 1) {
                    // Sort the group by ID ascending to ensure we keep the oldest
                    usort($group, function ($a, $b) {
                        return $a->id <=> $b->id;
                    });

                    // Keep the first one
                    array_shift($group);

                    // Delete the rest
                    foreach ($group as $dup) {
                        $dup->delete();
                        $deletedCount++;
                    }
                }
            }
        });

        return redirect()->route('admin.questions.index')->with('success', "Successfully cleaned {$deletedCount} duplicate question(s).");
    }

    /**
     * Run structural and factual integrity audits on all questions in the database.
     */
    public function runAudit()
    {
        $this->authorizeAdmin();

        // Prevent php request execution timeout for long-running AI audit batches
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        $questions = Question::with('options')->get();
        $apiKey = config('services.ai.key');

        $structuralCount = 0;
        $factualCount = 0;
        $passedCount = 0;

        $questionsToAuditFactual = [];

        foreach ($questions as $question) {
            $optionsCount = $question->options->count();
            $correctCount = $question->options->where('is_correct', true)->count();

            // Structural check: must have exactly 4 options
            if ($optionsCount !== 4) {
                $question->update([
                    'audit_status' => 'failed_structure',
                    'audit_error' => "Has {$optionsCount} options (expected 4)",
                ]);
                $structuralCount++;
                continue;
            }

            // Structural check: must have exactly 1 correct answer
            if ($correctCount !== 1) {
                $question->update([
                    'audit_status' => 'failed_structure',
                    'audit_error' => $correctCount === 0 ? 'No correct option marked' : "Multiple correct options ({$correctCount}) marked",
                ]);
                $structuralCount++;
                continue;
            }

            // Prepare for factual audit
            $correctOptionText = $question->options->where('is_correct', true)->first()->option_text;
            $optionsText = $question->options->pluck('option_text')->toArray();

            $questionsToAuditFactual[] = [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'options' => $optionsText,
                'correct_option' => $correctOptionText,
            ];
        }

        // Factual batch audit via Gemini API (if key is present)
        if (!empty($apiKey) && !empty($questionsToAuditFactual)) {
            $chunks = array_chunk($questionsToAuditFactual, 15);
            foreach ($chunks as $chunk) {
                $results = $this->auditFactualBatch($chunk, $apiKey);
                foreach ($results as $res) {
                    $qId = $res['id'] ?? null;
                    if ($qId) {
                        $q = $questions->firstWhere('id', $qId);
                        if ($q) {
                            if ($res['is_valid'] ?? true) {
                                $q->update([
                                    'audit_status' => 'passed',
                                    'audit_error' => null,
                                ]);
                                $passedCount++;
                            } else {
                                $q->update([
                                    'audit_status' => 'failed_facts',
                                    'audit_error' => $res['error_reason'] ?? 'Factual/content correctness check failed.',
                                ]);
                                $factualCount++;
                            }
                        }
                    }
                }
            }

            // Mark any missed questions from factual chunk as passed
            foreach ($questionsToAuditFactual as $item) {
                $q = $questions->firstWhere('id', $item['id']);
                if ($q && empty($q->audit_status)) {
                    $q->update([
                        'audit_status' => 'passed',
                        'audit_error' => null,
                    ]);
                    $passedCount++;
                }
            }
        } else {
            // No API key or no questions to audit factually: mark all structurally sound questions as passed
            foreach ($questionsToAuditFactual as $item) {
                $q = $questions->firstWhere('id', $item['id']);
                if ($q) {
                    $q->update([
                        'audit_status' => 'passed',
                        'audit_error' => null,
                    ]);
                    $passedCount++;
                }
            }
        }

        return redirect()->route('admin.questions.index')->with('success', "Audit complete. Passed: {$passedCount}, Structural Errors: {$structuralCount}, Factual Errors: {$factualCount}.");
    }

    /**
     * Bulk auto-fix all flagged integrity audit issues using AI.
     */
    public function bulkFixAudit()
    {
        $this->authorizeAdmin();

        set_time_limit(0);
        ini_set('max_execution_time', 0);

        $flaggedQuestions = Question::with(['category', 'options'])
            ->whereNotNull('audit_status')
            ->where('audit_status', '!=', 'passed')
            ->get();

        if ($flaggedQuestions->isEmpty()) {
            return redirect()->route('admin.questions.index')->with('success', 'No flagged questions found to fix.');
        }

        $apiKey = config('services.ai.key');
        if (empty($apiKey)) {
            return redirect()->route('admin.questions.index')->withErrors(['bulk_fix' => 'AI API Key is missing. Cannot perform bulk fix.']);
        }

        $fixedCount = 0;
        $chunks = $flaggedQuestions->chunk(5);

        foreach ($chunks as $chunk) {
            $questionsData = [];
            foreach ($chunk as $q) {
                $optionsText = [];
                foreach ($q->options as $idx => $opt) {
                    $optionsText[] = "[{$idx}] {$opt->option_text} (Correct: " . ($opt->is_correct ? 'true' : 'false') . ")";
                }

                $questionsData[] = [
                    'id' => $q->id,
                    'question_text' => $q->question_text,
                    'options' => $optionsText,
                    'explanation' => $q->explanation,
                    'audit_error' => $q->audit_error,
                ];
            }

            $fixedBatch = AIService::suggestFixBatch($questionsData);

            if (is_array($fixedBatch)) {
                foreach ($fixedBatch as $corrected) {
                    $qId = $corrected['id'] ?? null;
                    if ($qId) {
                        $originalQuestion = $chunk->firstWhere('id', $qId);
                        if ($originalQuestion) {
                            DB::transaction(function () use ($originalQuestion, $corrected, &$fixedCount) {
                                $originalQuestion->update([
                                    'question_text' => $corrected['question_text'],
                                    'explanation' => $corrected['explanation'] ?? null,
                                    'audit_status' => 'passed',
                                    'audit_error' => null,
                                    'problem_type_tag' => $corrected['problem_type_tag'] ?? $originalQuestion->problem_type_tag,
                                ]);

                                $originalQuestion->options()->delete();

                                foreach ($corrected['options'] as $idx => $optText) {
                                    QuestionOption::create([
                                        'question_id' => $originalQuestion->id,
                                        'option_text' => $optText,
                                        'is_correct' => $idx === (int)$corrected['correct_option_index'],
                                    ]);
                                }

                                $fixedCount++;
                            });
                        }
                    }
                }
            }
        }

        return redirect()->route('admin.questions.index')->with('success', "Bulk auto-fix complete. Successfully corrected {$fixedCount} question(s) using AI.");
    }

    /**
     * Call Gemini API to audit a batch of questions factually.
     */
    private function auditFactualBatch(array $questionsToAudit, string $apiKey): array
    {
        if (config('services.ai.provider') === 'deepseek') {
            return $this->auditFactualBatchDeepSeek($questionsToAudit, $apiKey);
        }

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

            $prompt = "You are an independent quality auditor for the Philippine Civil Service Exam (CSE).\n" .
                      "Verify the factual correctness of the following multiple-choice questions.\n" .
                      "For each question, check if the marked correct option is actually correct. If the question is correct, set `is_valid` to true.\n" .
                      "If the correct option is incorrect, or if the question is faulty/confusing, set `is_valid` to false and provide a short reason in `error_reason`.\n\n" .
                      "Questions:\n" .
                      json_encode($questionsToAudit, JSON_PRETTY_PRINT) . "\n\n" .
                      "Return a JSON array of objects with keys: 'id', 'is_valid', 'error_reason'.";

            $body = [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'ARRAY',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'id' => ['type' => 'INTEGER'],
                                'is_valid' => ['type' => 'BOOLEAN'],
                                'error_reason' => ['type' => 'STRING']
                            ],
                            'required' => ['id', 'is_valid']
                        ]
                    ]
                ]
            ];

            $response = Http::withoutVerifying()->timeout(120)->withHeaders(['Content-Type' => 'application/json'])->post($url, $body);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text) {
                    return json_decode($text, true) ?? [];
                }
            }

            if ($response->status() === 429) {
                \App\Models\Setting::set('ai_quota_exceeded_flag', '1');
                \App\Models\Setting::set('ai_last_error', '429 Quota Exceeded (Free Tier limit met or billing issue).');
            } else if ($response->failed()) {
                \App\Models\Setting::set('ai_last_error', 'Gemini API audit call failed with status ' . $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Factual audit batch call failed: ' . $e->getMessage());
            \App\Models\Setting::set('ai_last_error', 'Factual audit batch call failed: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Call DeepSeek API to audit a batch of questions factually.
     */
    private function auditFactualBatchDeepSeek(array $questionsToAudit, string $apiKey): array
    {
        try {
            $url = "https://api.deepseek.com/chat/completions";

            $prompt = "You are an independent quality auditor for the Philippine Civil Service Exam (CSE).\n" .
                      "Verify the factual correctness of the following multiple-choice questions.\n" .
                      "For each question, check if the marked correct option is actually correct. If the question is correct, set `is_valid` to true.\n" .
                      "If the correct option is incorrect, or if the question is faulty/confusing, set `is_valid` to false and provide a short reason in `error_reason`.\n\n" .
                      "Questions:\n" .
                      json_encode($questionsToAudit, JSON_PRETTY_PRINT) . "\n\n" .
                      "Return a JSON object containing a 'results' key which is a JSON array of objects with keys: 'id', 'is_valid', 'error_reason'.";

            $body = [
                'model' => 'deepseek-chat',
                'messages' => [['role' => 'user', 'content' => $prompt]],
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
                        if (isset($decoded['results']) && is_array($decoded['results'])) {
                            return $decoded['results'];
                        }
                        if (isset($decoded['questions']) && is_array($decoded['questions'])) {
                            return $decoded['questions'];
                        }
                    }
                }
            }

            if ($response->status() === 429) {
                \App\Models\Setting::set('ai_quota_exceeded_flag', '1');
                \App\Models\Setting::set('ai_last_error', '429 Quota Exceeded on DeepSeek API.');
            } else if ($response->failed()) {
                \App\Models\Setting::set('ai_last_error', 'DeepSeek API audit call failed with status ' . $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Factual audit batch call failed: ' . $e->getMessage());
            \App\Models\Setting::set('ai_last_error', 'Factual audit batch call failed: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Reset the AI usage generated questions count, clear quota flags, and wipe last error.
     */
    public function resetAIUsage()
    {
        $this->authorizeAdmin();

        \App\Models\Setting::set('ai_generated_count', 0);
        \App\Models\Setting::set('ai_quota_exceeded_flag', 0);
        \App\Models\Setting::set('ai_last_error', null);

        return redirect()->route('admin.questions.index')->with('success', 'AI usage metrics and quota block flags have been successfully reset.');
    }

    /**
     * Delete multiple questions in a single request.
     */
    public function bulkDestroy(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:questions,id',
        ]);

        $deletedCount = Question::whereIn('id', $request->ids)->delete();

        return redirect()->route('admin.questions.index')->with('success', "Successfully deleted {$deletedCount} selected question(s).");
    }

    /**
     * Get AI suggested fix for a flagged question.
     */
    public function suggestFix(Question $question)
    {
        $this->authorizeAdmin();

        $question->load('options');
        $suggested = AIService::suggestFix($question);

        if (!$suggested) {
            return response()->json([
                'error' => 'AI was unable to generate corrections. Please check key/logs.'
            ], 500);
        }

        return response()->json($suggested);
    }
}
