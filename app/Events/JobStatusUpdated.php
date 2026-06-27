<?php

namespace App\Events;

use App\Models\JobStatus;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a job status is updated.
 * 
 * This event can be used to:
 * - Broadcast job progress to the frontend
 * - Send notifications
 * - Update dashboards
 */
class JobStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public JobStatus $jobStatus,
        public string $message = ''
    ) {
        $this->message = $message ?: $this->getDefaultMessage();
    }

    /**
     * Get the default message based on status.
     */
    private function getDefaultMessage(): string
    {
        return match ($this->jobStatus->status) {
            JobStatus::STATUS_PENDING => 'Job is waiting to start...',
            JobStatus::STATUS_RUNNING => 'Job is in progress...',
            JobStatus::STATUS_COMPLETED => 'Job completed successfully!',
            JobStatus::STATUS_FAILED => 'Job failed: ' . ($this->jobStatus->metadata['error'] ?? 'Unknown error'),
            JobStatus::STATUS_CANCELLED => 'Job was cancelled.',
            default => 'Job status updated',
        };
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to user-specific channel and admin channel
        return [
            new PrivateChannel('user.'.$this->jobStatus->user_id),
            new PrivateChannel('admin.jobs'),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->jobStatus->id,
            'job_class' => $this->jobStatus->job_class,
            'status' => $this->jobStatus->status,
            'progress' => $this->jobStatus->progress,
            'total' => $this->jobStatus->total,
            'completed' => $this->jobStatus->completed,
            'failed' => $this->jobStatus->failed,
            'percentage' => $this->jobStatus->getPercentageComplete(),
            'metadata' => $this->jobStatus->metadata,
            'message' => $this->message,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'job.status.updated';
    }
}
