<?php

namespace App\Http\Controllers;

use App\Jobs\CleanDuplicatesJob;
use App\Jobs\GenerateQuestionsJob;
use App\Jobs\RunAuditJob;
use App\Jobs\VerifyQuestionsJob;
use App\Models\JobStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Controller for managing and tracking job statuses.
 * Provides endpoints for checking job progress and managing background tasks.
 */
class JobStatusController extends Controller
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
     * Get the status of a specific job.
     *
     * @param Request $request
     * @param int $jobId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, int $jobId)
    {
        $this->authorizeAdmin();

        $jobStatus = JobStatus::where('id', $jobId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'job' => [
                'id' => $jobStatus->id,
                'job_class' => $jobStatus->job_class,
                'status' => $jobStatus->status,
                'progress' => $jobStatus->progress,
                'total' => $jobStatus->total,
                'completed' => $jobStatus->completed,
                'failed' => $jobStatus->failed,
                'percentage' => $jobStatus->getPercentageComplete(),
                'metadata' => $jobStatus->metadata,
                'started_at' => $jobStatus->started_at?->toIso8601String(),
                'completed_at' => $jobStatus->completed_at?->toIso8601String(),
                'is_finished' => $jobStatus->isFinished(),
                'is_successful' => $jobStatus->isSuccessful(),
            ],
        ]);
    }

    /**
     * Get all jobs for the current user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $status = $request->input('status', null);
        $limit = $request->input('limit', 50);

        $query = JobStatus::forUser(Auth::id())
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $jobs = $query->paginate($limit);

        return response()->json([
            'jobs' => $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'job_class' => $job->job_class,
                    'status' => $job->status,
                    'progress' => $job->progress,
                    'total' => $job->total,
                    'completed' => $job->completed,
                    'failed' => $job->failed,
                    'percentage' => $job->getPercentageComplete(),
                    'metadata' => $job->metadata,
                    'started_at' => $job->started_at?->toIso8601String(),
                    'completed_at' => $job->completed_at?->toIso8601String(),
                    'is_finished' => $job->isFinished(),
                    'is_successful' => $job->isSuccessful(),
                ];
            }),
            'pagination' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
            ],
        ]);
    }

    /**
     * Get recent jobs for the current user (for dashboard).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recent(Request $request)
    {
        $this->authorizeAdmin();

        $limit = $request->input('limit', 10);

        $jobs = JobStatus::forUser(Auth::id())
            ->recent()
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        return response()->json([
            'recent_jobs' => $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'job_class' => $job->job_class,
                    'status' => $job->status,
                    'progress' => $job->progress,
                    'total' => $job->total,
                    'completed' => $job->completed,
                    'failed' => $job->failed,
                    'percentage' => $job->getPercentageComplete(),
                    'metadata' => $job->metadata,
                    'started_at' => $job->started_at?->toIso8601String(),
                    'completed_at' => $job->completed_at?->toIso8601String(),
                    'is_finished' => $job->isFinished(),
                    'is_successful' => $job->isSuccessful(),
                ];
            }),
        ]);
    }

    /**
     * Cancel a running job.
     * Note: Laravel doesn't natively support job cancellation, but we can mark it as cancelled.
     *
     * @param Request $request
     * @param int $jobId
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel(Request $request, int $jobId)
    {
        $this->authorizeAdmin();

        $jobStatus = JobStatus::where('id', $jobId)
            ->where('user_id', Auth::id())
            ->whereIn('status', [JobStatus::STATUS_PENDING, JobStatus::STATUS_RUNNING])
            ->firstOrFail();

        $jobStatus->markFailed('Job cancelled by user');

        return response()->json([
            'message' => 'Job cancellation requested',
            'job' => [
                'id' => $jobStatus->id,
                'status' => $jobStatus->status,
            ],
        ]);
    }

    /**
     * Retry a failed job.
     *
     * @param Request $request
     * @param int $jobId
     * @return \Illuminate\Http\JsonResponse
     */
    public function retry(Request $request, int $jobId)
    {
        $this->authorizeAdmin();

        $jobStatus = JobStatus::where('id', $jobId)
            ->where('user_id', Auth::id())
            ->where('status', JobStatus::STATUS_FAILED)
            ->firstOrFail();

        // Extract metadata for retry
        $metadata = $jobStatus->metadata ?? [];
        $jobClass = $jobStatus->job_class;

        // Dispatch the appropriate job based on the class
        $newJob = null;
        switch ($jobClass) {
            case GenerateQuestionsJob::class:
                $newJob = GenerateQuestionsJob::dispatch(
                    Auth::id(),
                    $metadata['category_id'] ?? 'all',
                    $metadata['level'] ?? 'professional',
                    $metadata['count'] ?? 10,
                    $metadata
                );
                break;

            case VerifyQuestionsJob::class:
                $newJob = VerifyQuestionsJob::dispatch(
                    Auth::id(),
                    $metadata['question_ids'] ?? [],
                    $metadata
                );
                break;

            case CleanDuplicatesJob::class:
                $newJob = CleanDuplicatesJob::dispatch(
                    Auth::id(),
                    $metadata['category_id'] ?? null,
                    $metadata
                );
                break;

            case RunAuditJob::class:
                $newJob = RunAuditJob::dispatch(
                    Auth::id(),
                    $metadata['category_id'] ?? null,
                    $metadata['limit'] ?? null,
                    $metadata
                );
                break;

            default:
                return response()->json([
                    'error' => 'Cannot retry job of type: ' . $jobClass,
                ], 400);
        }

        return response()->json([
            'message' => 'Job retry started',
            'new_job_id' => $newJob->getJobStatusId(),
        ]);
    }

    /**
     * Get job statistics (for dashboard).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats(Request $request)
    {
        $this->authorizeAdmin();

        $stats = JobStatus::forUser(Auth::id())
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $allStatuses = JobStatus::statuses();
        $result = [];
        foreach ($allStatuses as $status) {
            $result[$status] = $stats[$status] ?? 0;
        }

        // Get recent completed jobs
        $recentCompleted = JobStatus::forUser(Auth::id())
            ->completed()
            ->orderBy('completed_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'job_class' => $job->job_class,
                    'completed_at' => $job->completed_at?->toIso8601String(),
                    'metadata' => $job->metadata,
                ];
            });

        return response()->json([
            'status_counts' => $result,
            'recent_completed' => $recentCompleted,
        ]);
    }

    /**
     * Render the job status dashboard page.
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function dashboard(Request $request)
    {
        $this->authorizeAdmin();

        $recentJobs = JobStatus::forUser(Auth::id())
            ->recent()
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'job_class' => $job->job_class,
                    'status' => $job->status,
                    'progress' => $job->progress,
                    'total' => $job->total,
                    'completed' => $job->completed,
                    'failed' => $job->failed,
                    'percentage' => $job->getPercentageComplete(),
                    'metadata' => $job->metadata,
                    'started_at' => $job->started_at?->toIso8601String(),
                    'completed_at' => $job->completed_at?->toIso8601String(),
                    'is_finished' => $job->isFinished(),
                    'is_successful' => $job->isSuccessful(),
                ];
            });

        // Get status counts
        $stats = JobStatus::forUser(Auth::id())
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $statusCounts = [];
        foreach (JobStatus::statuses() as $status) {
            $statusCounts[$status] = $stats[$status] ?? 0;
        }

        return Inertia::render('Admin/JobDashboard', [
            'recentJobs' => $recentJobs,
            'statusCounts' => $statusCounts,
        ]);
    }
}
