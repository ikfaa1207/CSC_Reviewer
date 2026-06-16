<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ExamAttempt;
use App\Models\ExamCategory;
use App\Models\ExamAttemptAnswer;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $categories = ExamCategory::all();

        // Get completed exam attempts
        $attemptsQuery = ExamAttempt::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        $recentAttempts = (clone $attemptsQuery)
            ->take(5)
            ->get();

        $completedAttempts = (clone $attemptsQuery)
            ->where('status', 'completed')
            ->get();

        $totalExams = $completedAttempts->count();

        // Calculate average score percentage
        $averageScore = 0;
        if ($totalExams > 0) {
            $sumPercentages = $completedAttempts->reduce(function ($carry, $attempt) {
                $percentage = $attempt->total_questions > 0 
                    ? ($attempt->score / $attempt->total_questions) * 100 
                    : 0;
                return $carry + $percentage;
            }, 0);
            $averageScore = round($sumPercentages / $totalExams, 1);
        }

        // Calculate performance by category
        // Join attempt answers with questions and categories for the current user's completed attempts
        $completedAttemptIds = $completedAttempts->pluck('id');
        
        $categoryPerformance = [];
        foreach ($categories as $category) {
            $answers = ExamAttemptAnswer::whereIn('exam_attempt_id', $completedAttemptIds)
                ->whereHas('question', function ($query) use ($category) {
                    $query->where('exam_category_id', $category->id);
                })
                ->get();

            $totalAnswered = $answers->count();
            $totalCorrect = $answers->where('is_correct', true)->count();
            $percentage = $totalAnswered > 0 ? round(($totalCorrect / $totalAnswered) * 100, 1) : 0;

            $categoryPerformance[] = [
                'category_id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'level' => $category->level,
                'answered' => $totalAnswered,
                'correct' => $totalCorrect,
                'percentage' => $percentage
            ];
        }

        // Overall readiness is the average performance across all categories
        $readinessScore = 0;
        if ($categories->count() > 0) {
            $totalPercentage = array_sum(array_column($categoryPerformance, 'percentage'));
            $readinessScore = round($totalPercentage / $categories->count(), 1);
        }

        return Inertia::render('Dashboard', [
            'categories' => $categories,
            'recentAttempts' => $recentAttempts,
            'analytics' => [
                'totalExams' => $totalExams,
                'averageScore' => $averageScore,
                'readinessScore' => $readinessScore,
                'categoryPerformance' => $categoryPerformance
            ]
        ]);
    }
}
