<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\AdminQuestionController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Exam Reviewer routes
    Route::get('/exams/prepare', [ExamController::class, 'prepare'])->name('exams.prepare');
    Route::post('/exams/start', [ExamController::class, 'start'])->name('exams.start');
    Route::get('/exams/{attempt}', [ExamController::class, 'show'])->name('exams.show');
    Route::post('/exams/answers/{answer}', [ExamController::class, 'saveAnswer'])->name('exams.saveAnswer');
    Route::post('/exams/{attempt}/submit', [ExamController::class, 'submit'])->name('exams.submit');
    Route::get('/exams/{attempt}/results', [ExamController::class, 'results'])->name('exams.results');

    // Admin panel routes (Authorization check is inside controller)
    Route::get('/admin/questions', [AdminQuestionController::class, 'index'])->name('admin.questions.index');
    Route::post('/admin/questions', [AdminQuestionController::class, 'store'])->name('admin.questions.store');
    Route::post('/admin/questions/generate-ai', [AdminQuestionController::class, 'generateAI'])->name('admin.questions.generateAI');
    Route::put('/admin/questions/{question}', [AdminQuestionController::class, 'update'])->name('admin.questions.update');
    Route::delete('/admin/questions/{question}', [AdminQuestionController::class, 'destroy'])->name('admin.questions.destroy');
});

require __DIR__.'/auth.php';
