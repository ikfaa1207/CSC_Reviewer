<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model for tracking queue job status.
 * 
 * This model stores information about long-running jobs so users can
 * check their progress and results.
 */
class JobStatus extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'job_id',
        'job_class',
        'user_id',
        'status',
        'progress',
        'total',
        'completed',
        'failed',
        'metadata',
        'started_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get all statuses.
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_RUNNING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Create a new job status record.
     */
    public static function createForJob(string $jobClass, int $userId, array $metadata = []): self
    {
        return self::create([
            'job_class' => $jobClass,
            'user_id' => $userId,
            'status' => self::STATUS_PENDING,
            'progress' => 0,
            'total' => $metadata['count'] ?? 0,
            'completed' => 0,
            'failed' => 0,
            'metadata' => $metadata,
            'started_at' => now(),
        ]);
    }

    /**
     * Update job progress.
     */
    public function updateProgress(int $progress, int $completed = null, int $failed = null): self
    {
        $this->update([
            'progress' => $progress,
            'completed' => $completed ?? $this->completed + $progress,
            'failed' => $failed ?? $this->failed,
            'status' => self::STATUS_RUNNING,
        ]);
        return $this;
    }

    /**
     * Mark job as completed.
     */
    public function markCompleted(array $additionalMetadata = []): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], $additionalMetadata),
        ]);
        return $this;
    }

    /**
     * Mark job as failed.
     */
    public function markFailed(string $error = null, array $additionalMetadata = []): self
    {
        $metadata = $this->metadata ?? [];
        if ($error) {
            $metadata['error'] = $error;
        }
        
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'metadata' => array_merge($metadata, $additionalMetadata),
        ]);
        return $this;
    }

    /**
     * Get the percentage complete.
     */
    public function getPercentageComplete(): float
    {
        if ($this->total <= 0) {
            return 0;
        }
        return min(100, ($this->completed / $this->total) * 100);
    }

    /**
     * Check if job is finished (completed or failed).
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }

    /**
     * Check if job is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Get the user who initiated the job.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to jobs for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to pending jobs.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to running jobs.
     */
    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    /**
     * Scope to completed jobs.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to failed jobs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to recent jobs (last 24 hours).
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subHours(24));
    }

    /**
     * Scope to jobs of a specific class.
     */
    public function scopeOfClass($query, string $jobClass)
    {
        return $query->where('job_class', $jobClass);
    }
}
