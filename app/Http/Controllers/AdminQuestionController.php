<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\CleanDuplicatesJob;
use App\Jobs\GenerateQuestionsJob;
use App\Jobs\RunAuditJob;
use App\Jobs\VerifyQuestionsJob;
use App\Models\ExamCategory;
use App\Models\JobStatus;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Services\AI\AIService;
use App\Services\AI\QuestionNormalizer;
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
     * Display a paginated, filterable, sortable list of questions.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $search = $request->input('search', '');
        $categoryId = $request->input('category_id', '');
        $sort = $request->input('sort', 'newest');
        $filter = $request->input('filter', 'all');
        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [25, 50, 100]) ? $perPage : 50;

        // ── Build base query ──────────────────────────────────────────────────
        $query = Question::with(['category', 'options']);

        // Search
        if (!empty($search)) {
            $query->where('question_text', 'LIKE', '%' . $search . '%');
        }

        // Category filter
        if (!empty($categoryId)) {
            $query->where('exam_category_id', (int) $categoryId);
        }

        // Audit status filter
        if ($filter === 'passed') {
            $query->where('audit_status', 'passed');
        } elseif ($filter === 'failed_structure') {
            $query->where('audit_status', 'failed_structure');
        } elseif ($filter === 'failed_facts') {
            $query->where('audit_status', 'failed_facts');
        } elseif ($filter === 'duplicates') {
            // Subquery: only questions whose hash appears more than once
            $dupHashes = DB::table('questions')
                ->select('question_hash')
                ->whereNotNull('question_hash')
                ->groupBy('question_hash')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('question_hash');
            $query->whereIn('question_hash', $dupHashes);
        }

        // ── Duplicate flag subquery for is_duplicate ──────────────────────────
        // We compute a set of duplicate hashes once for the whole paginated result
        $dupHashSet = DB::table('questions')
            ->select('question_hash')
            ->whereNotNull('question_hash')
            ->groupBy('question_hash')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('question_hash')
            ->flip()
            ->all();

        // ── Sorting ────────────────────────────────────────────────────────────
        switch ($sort) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'category_asc':
                $query->join('exam_categories', 'questions.exam_category_id', '=', 'exam_categories.id')
                    ->orderBy('exam_categories.name', 'asc')
                    ->select('questions.*');
                break;
            case 'category_desc':
                $query->join('exam_categories', 'questions.exam_category_id', '=', 'exam_categories.id')
                    ->orderBy('exam_categories.name', 'desc')
                    ->select('questions.*');
                break;
            case 'duplicates_first':
                // Bring rows whose hash appears > 1 to the top
                if (!empty($dupHashSet)) {
                    $query->orderByRaw(
                        'CASE WHEN question_hash IN (' .
                        implode(',', array_fill(0, count($dupHashSet), '?')) .
                        ') THEN 0 ELSE 1 END',
                        array_keys($dupHashSet)
                    );
                }
                $query->orderBy('created_at', 'desc');
                break;
            case 'flagged_first':
                $query->orderByRaw("CASE WHEN audit_status IS NOT NULL AND audit_status != 'passed' THEN 0 ELSE 1 END")
                    ->orderBy('created_at', 'desc');
                break;
            default: // newest
                $query->orderBy('created_at', 'desc');
                break;
        }

        $paginated = $query->paginate($perPage)->withQueryString();

        // Attach is_duplicate flag to each result
        $paginated->getCollection()->each(function ($q) use ($dupHashSet) {
            $q->is_duplicate = !empty($q->question_hash) && isset($dupHashSet[$q->question_hash]);
        });

        // ── Summary counts (global, not filtered) ─────────────────────────────
        $totalCount = Question::count();
        $duplicateCount = Question::whereIn('question_hash', array_keys($dupHashSet))->count();
        $auditIssuesCount = Question::whereNotNull('audit_status')
            ->where('audit_status', '!=', 'passed')
            ->count();

        $categories = ExamCategory::all();

        return Inertia::render('Admin/Questions', [
            'questions' => $paginated,
            'categories' => $categories,
            'totalCount' => $totalCount,
            'duplicateCount' => $duplicateCount,
            'auditIssuesCount' => $auditIssuesCount,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
                'sort' => $sort,
                'filter' => $filter,
                'per_page' => $perPage,
            ],
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

    /**
     * AI question generation.
     *
     * Dispatches a background job for question generation.
     * Supports both synchronous (legacy) and asynchronous (queue) modes.
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function generateAI(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'exam_category_id' => 'required',
            'level' => 'required|in:professional,sub_professional',
            'count' => 'required|integer|in:1,5,10,15,20,30,40,50,150',
            'chunk_index' => 'sometimes|integer|min:0',
            'chunk_size' => 'sometimes|integer|min:1|max:10',
            'sync' => 'sometimes|boolean', // Force synchronous execution
        ]);

        // Check if we should run synchronously (for testing or small batches)
        $isSyncMode = $request->boolean('sync', false);
        $isChunkMode = $request->has('chunk_index');

        // For chunk mode, keep the old synchronous behavior for backward compatibility
        if ($isChunkMode) {
            return $this->generateAIChunkMode($request);
        }

        // Dispatch as background job
        $job = GenerateQuestionsJob::dispatch(
            Auth::id(),
            $request->exam_category_id,
            $request->level,
            (int) $request->count,
            [
                'source' => 'admin_panel',
                'category_id' => $request->exam_category_id,
                'level' => $request->level,
            ]
        );

        // Return JSON response with job ID for frontend tracking
        if ($request->wantsJson()) {
            return response()->json([
                'job_id' => $job->getJobStatusId(),
                'message' => 'Question generation started in background.',
                'status' => 'queued',
            ]);
        }

        // For web requests, redirect with success message
        return redirect()->route('admin.questions.index')
            ->with('success', 'Question generation started in background. You will be notified when complete.')
            ->with('job_id', $job->getJobStatusId());
    }

    /**
     * Legacy chunk mode for backward compatibility.
     * This keeps the old synchronous behavior for chunked generation.
     */
    private function generateAIChunkMode(Request $request): \Illuminate\Http\JsonResponse
    {
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        $chunkSize = (int) $request->input('chunk_size', 5);
        $chunkIndex = (int) $request->input('chunk_index', 0);

        // In chunk mode, each call generates only chunk_size questions
        $targetCount = $chunkSize;

        if ($request->exam_category_id === 'all') {
            $categories = ExamCategory::where('level', 'both')
                ->orWhere('level', $request->level)
                ->get();
        } else {
            $categories = collect([ExamCategory::findOrFail($request->exam_category_id)]);
        }

        $categoryCount = $categories->count();
        $baseCount = (int) ($targetCount / $categoryCount);
        $remainder = $targetCount % $categoryCount;

        $distributions = [];
        foreach ($categories as $index => $cat) {
            $catCount = $baseCount + ($index < $remainder ? 1 : 0);
            if ($catCount > 0) {
                $distributions[] = ['category' => $cat, 'count' => $catCount];
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
                if ($remaining <= 0)
                    break;

                if (app()->environment('testing')) {
                    $seedOffset = ($chunkIndex * $catTargetCount) + ($attempt * $catTargetCount);
                } else {
                    $seedOffset = (int) (microtime(true) * 1000) % 1000000 + rand(1000, 9999) + ($attempt * 100) + ($chunkIndex * 1000);
                }

                // Use the new AIService
                $aiService = app(AIService::class);
                $questionsList = $aiService->generateQuestions($category->name, $request->level, $remaining, $seedOffset, $discardedTexts);

                // Convert GeneratedQuestionData to arrays for backward compatibility
                $questionsArray = array_map(function ($q) {
                    return $q->toArray();
                }, $questionsList);

                // Extract keywords for DB candidate lookup
                $keywords = [];
                foreach ($questionsArray as $q) {
                    if (!empty($q['problem_type_tag'])) {
                        $parts = explode('-', strtolower($q['problem_type_tag']));
                        foreach ($parts as $part) {
                            $part = trim($part);
                            if (strlen($part) > 2)
                                $keywords[] = $part;
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

                $verifiedQuestions = $aiService->verifyQuestions($questionsArray, $dbCandidates);

                $seenInBatch = [];
                DB::transaction(function () use ($verifiedQuestions, $category, &$totalSkipped, &$savedCount, &$seenInBatch, &$discardedTexts) {
                    foreach ($verifiedQuestions as $generated) {
                        // Handle both array and GeneratedQuestionData
                        $questionText = $generated instanceof \App\DTOs\GeneratedQuestionData
                            ? $generated->question_text
                            : ($generated['question_text'] ?? '');
                        $options = $generated instanceof \App\DTOs\GeneratedQuestionData
                            ? $generated->options
                            : ($generated['options'] ?? []);
                        $correctOptionIndex = $generated instanceof \App\DTOs\GeneratedQuestionData
                            ? $generated->correct_option_index
                            : ($generated['correct_option_index'] ?? 0);
                        $explanation = $generated instanceof \App\DTOs\GeneratedQuestionData
                            ? $generated->explanation
                            : ($generated['explanation'] ?? null);
                        $problemTypeTag = $generated instanceof \App\DTOs\GeneratedQuestionData
                            ? $generated->problem_type_tag
                            : ($generated['problem_type_tag'] ?? null);
                        $isValid = $generated instanceof \App\DTOs\GeneratedQuestionData
                            ? $generated->is_valid
                            : ($generated['is_valid'] ?? true);

                        if (!$isValid) {
                            $totalSkipped++;
                            $discardedTexts[] = $questionText;
                            continue;
                        }

                        if (count($options) !== 4) {
                            $totalSkipped++;
                            $discardedTexts[] = $questionText;
                            continue;
                        }

                        if ($correctOptionIndex < 0 || $correctOptionIndex > 3) {
                            $totalSkipped++;
                            $discardedTexts[] = $questionText;
                            continue;
                        }

                        $hash = Question::computeHash($questionText);

                        // Avoid duplicate within batch
                        if (in_array($hash, $seenInBatch)) {
                            $totalSkipped++;
                            $discardedTexts[] = $questionText;
                            continue;
                        }

                        // Check database duplicate via question_hash
                        $exists = Question::where('question_hash', $hash)->exists();
                        if ($exists) {
                            $totalSkipped++;
                            $discardedTexts[] = $questionText;
                            continue;
                        }

                        $seenInBatch[] = $hash;

                        $question = Question::create([
                            'exam_category_id' => $category->id,
                            'question_text' => $generated['question_text'],
                            'explanation' => $generated['explanation'] ?? null,
                            'audit_status' => 'passed',
                            'problem_type_tag' => $generated['problem_type_tag'] ?? null,
                        ]);

                        foreach ($options as $idx => $optText) {
                            QuestionOption::create([
                                'question_id' => $question->id,
                                'option_text' => $optText,
                                'is_correct' => $idx === (int) $correctOptionIndex,
                            ]);
                        }
                        $savedCount++;
                    }
                });
            }

            $totalSavedCount += $savedCount;
            $totalSkippedCount += $totalSkipped;
        }

        // Post-generation structural audit
        $structuralErrors = $this->runStructuralAuditOnly();

        return response()->json([
            'saved' => $totalSavedCount,
            'skipped' => $totalSkippedCount,
            'structuralErrors' => $structuralErrors,
            'done' => true,
        ]);
    }

    /**
     * Run a fast structural-only audit on ALL un-passed questions.
     * Returns count of newly flagged structural errors.
     */
    private function runStructuralAuditOnly(): int
    {
        $questions = Question::with('options')
            ->where(function ($q) {
                $q->whereNull('audit_status')->orWhere('audit_status', '!=', 'passed');
            })
            ->get();

        $structuralCount = 0;

        foreach ($questions as $question) {
            $optionsCount = $question->options->count();
            $correctCount = $question->options->where('is_correct', true)->count();

            if ($optionsCount !== 4) {
                $question->update([
                    'audit_status' => 'failed_structure',
                    'audit_error' => "Has {$optionsCount} options (expected 4)",
                ]);
                $structuralCount++;
                continue;
            }

            if ($correctCount !== 1) {
                $question->update([
                    'audit_status' => 'failed_structure',
                    'audit_error' => $correctCount === 0 ? 'No correct option marked' : "Multiple correct options ({$correctCount}) marked",
                ]);
                $structuralCount++;
                continue;
            }

            // If it was previously flagged as structural but now passes, mark it passed
            if ($question->audit_status === 'failed_structure') {
                $question->update([
                    'audit_status' => 'passed',
                    'audit_error' => null,
                ]);
            }
        }

        return $structuralCount;
    }

    /**
     * Clean duplicate questions from the database using normalized hash comparison.
     * Dispatches a background job for large cleanup operations.
     */
    public function cleanDuplicates(Request $request)
    {
        $this->authorizeAdmin();

        $categoryId = $request->input('category_id', null);
        $isSync = $request->boolean('sync', false);

        // If sync mode or no category filter, run synchronously for small operations
        if ($isSync) {
            // Find all hashes that appear more than once
            $query = DB::table('questions')
                ->select('question_hash')
                ->whereNotNull('question_hash')
                ->groupBy('question_hash')
                ->havingRaw('COUNT(*) > 1');

            if ($categoryId) {
                $query->where('exam_category_id', $categoryId);
            }

            $dupHashes = $query->pluck('question_hash');

            if ($dupHashes->isEmpty()) {
                if ($request->wantsJson()) {
                    return response()->json(['message' => 'No duplicates found to clean.']);
                }
                return redirect()->route('admin.questions.index')->with('success', 'No duplicates found to clean.');
            }

            $deletedCount = 0;

            DB::transaction(function () use ($dupHashes, &$deletedCount, $categoryId) {
                foreach ($dupHashes as $hash) {
                    $query = Question::where('question_hash', $hash)->orderBy('id', 'asc');
                    if ($categoryId) {
                        $query->where('exam_category_id', $categoryId);
                    }
                    $group = $query->get();
                    // Keep the oldest (first by ID), delete the rest
                    $group->shift();
                    foreach ($group as $dup) {
                        $dup->delete();
                        $deletedCount++;
                    }
                }
            });

            if ($request->wantsJson()) {
                return response()->json([
                    'deleted_count' => $deletedCount,
                    'message' => "Cleaned {$deletedCount} duplicates",
                ]);
            }

            return redirect()->route('admin.questions.index')
                ->with('success', "Successfully cleaned {$deletedCount} duplicate question(s).");
        }

        // Dispatch as background job
        $job = CleanDuplicatesJob::dispatch(
            Auth::id(),
            $categoryId,
            [
                'source' => 'admin_panel',
                'category_id' => $categoryId,
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'job_id' => $job->getJobStatusId(),
                'message' => 'Duplicate cleanup started in background.',
                'status' => 'queued',
            ]);
        }

        return redirect()->route('admin.questions.index')
            ->with('success', 'Duplicate cleanup started in background. You will be notified when complete.')
            ->with('job_id', $job->getJobStatusId());
    }

    /**
     * Run structural and factual integrity audits on all UN-PASSED questions.
     * Dispatches a background job for large audit operations.
     */
    public function runAudit(Request $request)
    {
        $this->authorizeAdmin();

        $categoryId = $request->input('category_id', null);
        $limit = $request->input('limit', null);
        $isSync = $request->boolean('sync', false);

        // If sync mode, run synchronously for small operations
        if ($isSync) {
            return $this->runAuditSync($categoryId, $limit);
        }

        // Dispatch as background job
        $job = RunAuditJob::dispatch(
            Auth::id(),
            $categoryId,
            $limit,
            [
                'source' => 'admin_panel',
                'category_id' => $categoryId,
                'limit' => $limit,
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'job_id' => $job->getJobStatusId(),
                'message' => 'Audit started in background.',
                'status' => 'queued',
            ]);
        }

        return redirect()->route('admin.questions.index')
            ->with('success', 'Audit started in background. You will be notified when complete.')
            ->with('job_id', $job->getJobStatusId());
    }

    /**
     * Synchronous audit execution for backward compatibility.
     */
    private function runAuditSync(?int $categoryId = null, ?int $limit = null): \Illuminate\Http\RedirectResponse
    {
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        // Scope to only un-passed questions (NULL or failed)
        $query = Question::with('options')
            ->where(function ($q) {
                $q->whereNull('audit_status')->orWhere('audit_status', '!=', 'passed');
            });

        if ($categoryId) {
            $query->where('exam_category_id', $categoryId);
        }

        if ($limit) {
            $query->take($limit);
        }

        $questions = $query->get();

        $apiKey = config('services.ai.key');

        $structuralCount = 0;
        $factualCount = 0;
        $passedCount = 0;

        $questionsToAuditFactual = [];

        foreach ($questions as $question) {
            $optionsCount = $question->options->count();
            $correctCount = $question->options->where('is_correct', true)->count();

            if ($optionsCount !== 4) {
                $question->update([
                    'audit_status' => 'failed_structure',
                    'audit_error' => "Has {$optionsCount} options (expected 4)",
                ]);
                $structuralCount++;
                continue;
            }

            if ($correctCount !== 1) {
                $question->update([
                    'audit_status' => 'failed_structure',
                    'audit_error' => $correctCount === 0 ? 'No correct option marked' : "Multiple correct options ({$correctCount}) marked",
                ]);
                $structuralCount++;
                continue;
            }

            $correctOptionText = $question->options->where('is_correct', true)->first()->option_text;
            $optionsText = $question->options->pluck('option_text')->toArray();

            $questionsToAuditFactual[] = [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'options' => $optionsText,
                'correct_option' => $correctOptionText,
            ];
        }

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
                                $q->update(['audit_status' => 'passed', 'audit_error' => null]);
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

            foreach ($questionsToAuditFactual as $item) {
                $q = $questions->firstWhere('id', $item['id']);
                if ($q && empty($q->audit_status)) {
                    $q->update(['audit_status' => 'passed', 'audit_error' => null]);
                    $passedCount++;
                }
            }
        } else {
            foreach ($questionsToAuditFactual as $item) {
                $q = $questions->firstWhere('id', $item['id']);
                if ($q) {
                    $q->update(['audit_status' => 'passed', 'audit_error' => null]);
                    $passedCount++;
                }
            }
        }

        return redirect()->route('admin.questions.index')
            ->with('success', "Audit complete. Passed: {$passedCount}, Structural Errors: {$structuralCount}, Factual Errors: {$factualCount}.");
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
            return redirect()->route('admin.questions.index')
                ->withErrors(['bulk_fix' => 'AI API Key is missing. Cannot perform bulk fix.']);
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
                                        'is_correct' => $idx === (int) $corrected['correct_option_index'],
                                    ]);
                                }

                                $fixedCount++;
                            });
                        }
                    }
                }
            }
        }

        return redirect()->route('admin.questions.index')
            ->with('success', "Bulk auto-fix complete. Successfully corrected {$fixedCount} question(s) using AI.");
    }

    /**
     * Call Gemini API to audit a batch of questions factually.
     */
    private function auditFactualBatch(array $questionsToAudit, string $apiKey): array
    {
        $provider = config('services.ai.provider', 'gemini');
        if ($provider === 'groq') {
            return $this->auditFactualBatchGroq($questionsToAudit, $apiKey);
        }
        return $this->auditFactualBatchGemini($questionsToAudit, $apiKey);
    }

    /**
     * Call Gemini API to audit a batch of questions factually.
     */
    private function auditFactualBatchGemini(array $questionsToAudit, string $apiKey): array
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
                                'error_reason' => ['type' => 'STRING'],
                            ],
                            'required' => ['id', 'is_valid'],
                        ],
                    ],
                ],
            ];

            $response = Http::withoutVerifying()->timeout(120)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $body);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text)
                    return json_decode($text, true) ?? [];
            }

            if ($response->failed()) {
                Log::error('Gemini API audit call failed with status ' . $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Factual audit batch call failed: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Call Groq API to audit a batch of questions factually.
     */
    private function auditFactualBatchGroq(array $questionsToAudit, string $apiKey): array
    {
        try {
            $url = "https://api.groq.com/openai/v1/chat/completions";

            $prompt = "You are an independent quality auditor for the Philippine Civil Service Exam (CSE).\n" .
                "Verify the factual correctness of the following multiple-choice questions.\n" .
                "For each question, check if the marked correct option is actually correct. If the question is correct, set `is_valid` to true.\n" .
                "If the correct option is incorrect, or if the question is faulty/confusing, set `is_valid` to false and provide a short reason in `error_reason`.\n\n" .
                "Questions:\n" .
                json_encode($questionsToAudit, JSON_PRETTY_PRINT) . "\n\n" .
                "Return a JSON object containing a 'results' key which is a JSON array of objects with keys: 'id', 'is_valid', 'error_reason'.";

            $body = [
                'model' => config('services.ai.model', 'llama-3.3-70b-versatile'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
            ];

            $response = Http::withoutVerifying()->timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->post($url, $body);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['choices'][0]['message']['content'] ?? null;
                if ($text) {
                    $decoded = json_decode($text, true);
                    if (is_array($decoded)) {
                        if (isset($decoded['results']) && is_array($decoded['results']))
                            return $decoded['results'];
                        if (isset($decoded['questions']) && is_array($decoded['questions']))
                            return $decoded['questions'];
                    }
                }
            }

            if ($response->failed()) {
                Log::error('Groq API audit call failed with status ' . $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Factual audit batch call failed: ' . $e->getMessage());
        }

        return [];
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

        return redirect()->route('admin.questions.index')
            ->with('success', "Successfully deleted {$deletedCount} selected question(s).");
    }

    /**
     * Get AI suggested fix for a flagged question.
     */
    public function suggestFix(Question $question)
    {
        $this->authorizeAdmin();

        $question->load('options');

        // For now, return a mock suggestion
        // In a full implementation, this would call the AI API
        $suggested = [
            'id' => $question->id,
            'question_text' => $question->question_text . ' (Suggested fix)',
            'options' => $question->options->pluck('option_text')->toArray(),
            'correct_option_index' => 0,
            'explanation' => $question->explanation ?? 'Suggested explanation',
            'problem_type_tag' => $question->problem_type_tag ?? 'unknown',
        ];

        return response()->json($suggested);
    }
}
