<?php

namespace App\Jobs;

use App\Models\Question;
use App\Services\AI\QuestionNormalizer;
use App\Traits\TracksJobStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Queue job for cleaning duplicate questions from the database.
 * 
 * This job identifies and removes duplicate questions based on
 * normalized text hashes.
 */
class CleanDuplicatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TracksJobStatus;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds to wait before the job times out.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     *
     * @param int $userId The user who initiated the cleanup
     * @param int|null $categoryId Optional category filter
     * @param array $metadata Additional metadata for tracking
     */
    public function __construct(
        public int $userId,
        public ?int $categoryId = null,
        public array $metadata = []
    ) {
        $this->queue = 'maintenance';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting CleanDuplicatesJob', [
            'user_id' => $this->userId,
            'category_id' => $this->categoryId,
        ]);

        // Create job status tracking
        $this->createJobStatus($this->metadata);

        try {
            $query = Question::whereNotNull('question_hash');
            
            if ($this->categoryId) {
                $query->where('exam_category_id', $this->categoryId);
            }

            // Find all hashes that appear more than once
            $dupHashes = DB::table('questions')
                ->select('question_hash')
                ->whereNotNull('question_hash')
                ->when($this->categoryId, function ($q) {
                    $q->where('exam_category_id', $this->categoryId);
                })
                ->groupBy('question_hash')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('question_hash');

            if ($dupHashes->isEmpty()) {
                Log::info('No duplicates found to clean', [
                    'category_id' => $this->categoryId,
                ]);
                
                $this->markJobCompleted(['message' => 'No duplicates found to clean']);
                return;
            }

            $deletedCount = 0;
            $keptCount = 0;
            $totalHashes = $dupHashes->count();
            $processedHashes = 0;

            DB::transaction(function () use ($dupHashes, &$deletedCount, &$keptCount, &$processedHashes, $totalHashes) {
                foreach ($dupHashes as $hash) {
                    $query = Question::where('question_hash', $hash);
                    
                    if ($this->categoryId) {
                        $query->where('exam_category_id', $this->categoryId);
                    }
                    
                    $group = $query->orderBy('id', 'asc')->get();
                    
                    // Keep the oldest (first by ID), delete the rest
                    $first = $group->first();
                    $rest = $group->slice(1);
                    
                    if ($first) {
                        $keptCount++;
                    }
                    
                    foreach ($rest as $dup) {
                        $dup->delete();
                        $deletedCount++;
                    }
                    
                    $processedHashes++;
                    
                    // Update progress after each hash processed
                    $progress = (int) (($processedHashes / $totalHashes) * 100);
                    $this->updateJobProgress($progress, $deletedCount, 0);
                }
            });

            Log::info('CleanDuplicatesJob completed', [
                'user_id' => $this->userId,
                'deleted' => $deletedCount,
                'kept' => $keptCount,
                'category_id' => $this->categoryId,
            ]);

            // Mark job as completed with results
            $this->markJobCompleted([
                'deleted_count' => $deletedCount,
                'kept_count' => $keptCount,
                'message' => "Deleted {$deletedCount} duplicates, kept {$keptCount}",
            ]);

        } catch (\Exception $e) {
            Log::error('CleanDuplicatesJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->markJobFailed($e, ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get the display name for the job.
     */
    public function displayName(): string
    {
        $category = $this->categoryId ? " for category {$this->categoryId}" : '';
        return "Clean duplicate questions" . $category;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CleanDuplicatesJob failed permanently', [
            'job_id' => $this->job->getJobId(),
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        $tags = [
            'maintenance',
            'cleanup',
            'duplicates',
            'user:' . $this->userId,
        ];
        
        if ($this->categoryId) {
            $tags[] = 'category:' . $this->categoryId;
        }
        
        return $tags;
    }
}
