<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ExamController extends Controller
{
    /**
     * Show the exam preparation configuration screen.
     */
    public function prepare()
    {
        $categories = ExamCategory::all();
        return Inertia::render('Exam/Prepare', [
            'categories' => $categories
        ]);
    }

    /**
     * Start a new exam attempt and assign questions.
     */
    public function start(Request $request)
    {
        $request->validate([
            'level' => 'required|in:professional,sub_professional',
            'category_id' => 'nullable|exists:exam_categories,id',
            'mode' => 'required|in:timed,review',
        ]);

        $user = Auth::user();

        // 1. Determine questions query
        $query = Question::query();

        // Filter by category if specified
        if ($request->category_id) {
            $query->where('exam_category_id', $request->category_id);
        } else {
            // For general levels, filter categories matching the level
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('level', 'both')
                  ->orWhere('level', $request->level);
            });
        }

        // Fetch up to 20 random questions
        $questions = $query->inRandomOrder()->take(20)->get();

        if ($questions->isEmpty()) {
            return back()->withErrors(['error' => 'No questions available for the selected level/category. Please contact an administrator to add questions.']);
        }

        // 2. Create the Exam Attempt
        $attempt = ExamAttempt::create([
            'user_id' => $user->id,
            'level' => $request->level,
            'status' => 'in_progress',
            'score' => 0,
            'total_questions' => $questions->count(),
            'started_at' => now(),
        ]);

        // 3. Create placeholder attempt answers
        foreach ($questions as $question) {
            ExamAttemptAnswer::create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'selected_option_id' => null,
                'is_correct' => false,
                'is_flagged' => false,
            ]);
        }

        return redirect()->route('exams.show', $attempt->id);
    }

    /**
     * Display the active exam session interface.
     */
    public function show(ExamAttempt $attempt)
    {
        // Security check
        if ($attempt->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this exam attempt.');
        }

        // Redirect to results if already completed
        if ($attempt->status === 'completed') {
            return redirect()->route('exams.results', $attempt->id);
        }

        // Load answers, questions, and options, but hide "is_correct" during the exam
        $attempt->load([
            'answers' => function ($query) {
                $query->orderBy('id', 'asc');
            },
            'answers.question.category',
            'answers.question.options' => function ($query) {
                // Select only non-sensitive columns
                $query->select('id', 'question_id', 'option_text');
            }
        ]);

        return Inertia::render('Exam/Take', [
            'attempt' => $attempt,
        ]);
    }

    /**
     * Save/update a question answer in real-time.
     */
    public function saveAnswer(Request $request, ExamAttemptAnswer $answer)
    {
        $attempt = $answer->attempt;

        // Security checks
        if ($attempt->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json(['message' => 'Exam is already submitted'], 400);
        }

        $request->validate([
            'selected_option_id' => 'nullable|exists:question_options,id',
            'is_flagged' => 'required|boolean',
        ]);

        $isCorrect = false;

        // Check if correct
        if ($request->selected_option_id) {
            $option = QuestionOption::where('id', $request->selected_option_id)
                ->where('question_id', $answer->question_id)
                ->first();

            if (!$option) {
                return response()->json(['message' => 'Invalid option for this question'], 400);
            }

            $isCorrect = $option->is_correct;
        }

        $answer->update([
            'selected_option_id' => $request->selected_option_id,
            'is_correct' => $isCorrect,
            'is_flagged' => $request->is_flagged,
        ]);

        return response()->json([
            'success' => true,
            'answer' => $answer
        ]);
    }

    /**
     * Submit and grade the exam attempt.
     */
    public function submit(ExamAttempt $attempt)
    {
        // Security check
        if ($attempt->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this exam attempt.');
        }

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('exams.results', $attempt->id);
        }

        // Calculate final score
        $totalCorrect = $attempt->answers()->where('is_correct', true)->count();

        $attempt->update([
            'status' => 'completed',
            'score' => $totalCorrect,
            'completed_at' => now(),
        ]);

        return redirect()->route('exams.results', $attempt->id);
    }

    /**
     * Display the post-exam detailed results analysis.
     */
    public function results(ExamAttempt $attempt)
    {
        // Security check
        if ($attempt->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this exam attempt.');
        }

        if ($attempt->status !== 'completed') {
            return redirect()->route('exams.show', $attempt->id);
        }

        // Eager load everything, including the is_correct value for each option
        $attempt->load([
            'answers' => function ($query) {
                $query->orderBy('id', 'asc');
            },
            'answers.question.category',
            'answers.question.options',
            'answers.selectedOption'
        ]);

        return Inertia::render('Exam/Results', [
            'attempt' => $attempt,
        ]);
    }
}
