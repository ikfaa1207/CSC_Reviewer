<?php

namespace App\Traits;

use App\Events\JobStatusUpdated;
use App\Models\JobStatus;

/**
 * Trait for tracking job status.
 * 
 * This trait provides methods for creating and updating job status records.
 */
trait TracksJobStatus
{
    /**
     * The job status record.
     */
    protected ?JobStatus $jobStatus = null;

    /**
     * Create a job status record at the start of the job.
     */
    protected function createJobStatus(array $metadata = []): JobStatus
    {
        $jobClass = get_class($this);
        $userId = $this->userId ?? null;
        
        $metadata = array_merge([
            'count' => $this->count ?? 0,
            'category_id' => $this->categoryId ?? null,
            'level' => $this->level ?? null,
        ], $metadata);

        $this->jobStatus = JobStatus::createForJob($jobClass, $userId, $metadata);
        
        // Broadcast status update
        JobStatusUpdated::dispatch($this->jobStatus, 'Job started');
        
        return $this->jobStatus;
    }

    /**
     * Update job progress.
     */
    protected function updateJobProgress(int $progress, int $completed = null, int $failed = null): void
    {
        if (!$this->jobStatus) {
            return;
        }

        $this->jobStatus->updateProgress($progress, $completed, $failed);
        
        // Broadcast status update
        JobStatusUpdated::dispatch($this->jobStatus);
    }

    /**
     * Mark job as completed.
     */
    protected function markJobCompleted(array $additionalMetadata = []): void
    {
        if (!$this->jobStatus) {
            return;
        }

        $this->jobStatus->markCompleted($additionalMetadata);
        
        // Broadcast final status
        JobStatusUpdated::dispatch($this->jobStatus, 'Job completed successfully!');
    }

    /**
     * Mark job as failed.
     */
    protected function markJobFailed(\Throwable $exception, array $additionalMetadata = []): void
    {
        if (!$this->jobStatus) {
            return;
        }

        $errorMessage = $exception->getMessage();
        $this->jobStatus->markFailed($errorMessage, $additionalMetadata);
        
        // Broadcast failure
        JobStatusUpdated::dispatch($this->jobStatus, 'Job failed: ' . $errorMessage);
    }

    /**
     * Get the job status.
     */
    public function getJobStatus(): ?JobStatus
    {
        return $this->jobStatus;
    }

    /**
     * Get the job status ID.
     */
    public function getJobStatusId(): ?int
    {
        return $this->jobStatus?->id;
    }
}
