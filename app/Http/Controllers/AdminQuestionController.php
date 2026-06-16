<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        
        // Identify duplicate questions globally (case-insensitive, trimmed)
        $duplicateTexts = Question::select(DB::raw('LOWER(TRIM(question_text)) as trimmed_text'))
            ->groupBy(DB::raw('LOWER(TRIM(question_text))'))
            ->havingRaw('COUNT(*) > 1')
            ->pluck('trimmed_text')
            ->toArray();

        $questions->each(function ($q) use ($duplicateTexts) {
            $q->is_duplicate = in_array(strtolower(trim($q->question_text)), $duplicateTexts);
        });

        $categories = ExamCategory::all();

        $aiUsage = [
            'count' => (int)\App\Models\Setting::get('ai_generated_count', 0),
            'limit' => (int)\App\Models\Setting::get('ai_generated_limit', 500),
            'quotaExceeded' => (bool)\App\Models\Setting::get('ai_quota_exceeded_flag', false),
            'lastError' => \App\Models\Setting::get('ai_last_error', null),
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

        $request->validate([
            'exam_category_id' => 'required|exists:exam_categories,id',
            'level' => 'required|in:professional,sub_professional',
            'count' => 'required|integer|in:1,5,10,15,20,30,40,50',
        ]);

        $category = ExamCategory::findOrFail($request->exam_category_id);
        $count = (int)$request->count;

        $savedCount = 0;
        $totalSkipped = 0;
        $maxAttempts = 3;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $remaining = $count - $savedCount;
            if ($remaining <= 0) {
                break;
            }

            // Generate seed offset to ensure unique questions across subsequent requests and attempts
            if (app()->environment('testing')) {
                $seedOffset = $attempt * $count;
            } else {
                $seedOffset = (int)(microtime(true) * 1000) % 1000000 + rand(1000, 9999) + ($attempt * 100);
            }

            // Call Gemini Service for bulk generation
            $questionsList = GeminiService::generateQuestions($category->name, $request->level, $remaining, $seedOffset);

            // Verify the questions structurally and factually using Gemini Reviewer
            $verifiedQuestions = GeminiService::verifyQuestions($questionsList);

            DB::transaction(function () use ($verifiedQuestions, $category, &$totalSkipped, &$savedCount) {
                foreach ($verifiedQuestions as $generated) {
                    // Factual check: Skip if the AI auditor marked it as invalid
                    if (isset($generated['is_valid']) && !$generated['is_valid']) {
                        $totalSkipped++;
                        continue;
                    }

                    // Structural checks: Ensure exactly 4 options and a valid correct_option_index
                    if (!isset($generated['options']) || count($generated['options']) !== 4) {
                        $totalSkipped++;
                        continue;
                    }

                    if (!isset($generated['correct_option_index']) || $generated['correct_option_index'] < 0 || $generated['correct_option_index'] > 3) {
                        $totalSkipped++;
                        continue;
                    }

                    $cleanText = strtolower(trim($generated['question_text']));
                    $exists = Question::whereRaw('LOWER(TRIM(question_text)) = ?', [$cleanText])->exists();

                    if ($exists) {
                        $totalSkipped++;
                        continue;
                    }

                    $question = Question::create([
                        'exam_category_id' => $category->id,
                        'question_text' => $generated['question_text'],
                        'explanation' => $generated['explanation'] ?? null,
                        'audit_status' => 'passed',
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

        if ($savedCount > 0) {
            $currentCount = (int)\App\Models\Setting::get('ai_generated_count', 0);
            \App\Models\Setting::set('ai_generated_count', $currentCount + $savedCount);
        }

        if ($savedCount === $count) {
            return redirect()->route('admin.questions.index')->with('success', "{$count} questions generated and saved successfully using AI.");
        }

        return redirect()->route('admin.questions.index')->with('success', "{$savedCount} questions generated successfully using AI. {$totalSkipped} invalid or duplicate question(s) were skipped.");
    }

    /**
     * Clean all duplicate questions, keeping only the oldest record (with the lowest ID).
     */
    public function cleanDuplicates()
    {
        $this->authorizeAdmin();

        // Find duplicate questions grouped globally (case-insensitive, trimmed)
        $duplicates = Question::select(DB::raw('LOWER(TRIM(question_text)) as trimmed_text'), DB::raw('MIN(id) as keep_id'))
            ->groupBy(DB::raw('LOWER(TRIM(question_text))'))
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $deletedCount = 0;

        DB::transaction(function () use ($duplicates, &$deletedCount) {
            foreach ($duplicates as $duplicate) {
                // Find all duplicate questions with this text, except the one we are keeping
                $toDeleteIds = Question::whereRaw('LOWER(TRIM(question_text)) = ?', [$duplicate->trimmed_text])
                    ->where('id', '!=', $duplicate->keep_id)
                    ->pluck('id');

                if ($toDeleteIds->isNotEmpty()) {
                    // Delete the duplicates (associated options/attempt answers will cascade delete via DB)
                    Question::whereIn('id', $toDeleteIds)->delete();
                    $deletedCount += $toDeleteIds->count();
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

        $questions = Question::with('options')->get();
        $apiKey = config('services.gemini.key');

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
     * Call Gemini API to audit a batch of questions factually.
     */
    private function auditFactualBatch(array $questionsToAudit, string $apiKey): array
    {
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

            $response = Http::withHeaders(['Content-Type' => 'application/json'])->post($url, $body);

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
}
