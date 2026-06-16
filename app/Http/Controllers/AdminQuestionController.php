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
        $categories = ExamCategory::all();

        return Inertia::render('Admin/Questions', [
            'questions' => $questions,
            'categories' => $categories,
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

        // Call Gemini Service for bulk generation
        $questionsList = GeminiService::generateQuestions($category->name, $request->level, $count);

        DB::transaction(function () use ($questionsList, $category) {
            foreach ($questionsList as $generated) {
                $question = Question::create([
                    'exam_category_id' => $category->id,
                    'question_text' => $generated['question_text'],
                    'explanation' => $generated['explanation'] ?? null,
                ]);

                foreach ($generated['options'] as $idx => $optText) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'option_text' => $optText,
                        'is_correct' => $idx === (int)$generated['correct_option_index'],
                    ]);
                }
            }
        });

        return redirect()->route('admin.questions.index')->with('success', "{$count} questions generated and saved successfully using AI.");
    }
}
