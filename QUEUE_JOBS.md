# Queue Jobs Implementation

This document describes the queue jobs implementation for the CSC_Reviewer Laravel application, which enables background processing of AI operations to prevent HTTP timeouts and improve user experience.

## Overview

The queue jobs system provides:
- **Background Processing**: Long-running AI operations (generation, verification, audit, cleanup) run in the background
- **Real-time Progress Tracking**: Users can monitor job progress via a dashboard
- **Job Management**: Retry failed jobs, cancel running jobs, view history
- **Broadcast Notifications**: Real-time updates via Laravel Echo and Pusher

## Architecture

### Components

#### 1. Queue Jobs
Located in `app/Jobs/`:

- **`GenerateQuestionsJob`**: Bulk question generation with AI
  - Distributes questions across categories
  - Validates and prevents duplicates
  - Tracks progress per category
  - Queue: `ai-generation`

- **`VerifyQuestionsJob`**: Bulk question verification with AI
  - Processes in chunks of 10
  - Structural and factual verification
  - Tracks verified/failed/unchanged counts
  - Queue: `ai-verification`

- **`CleanDuplicatesJob`**: Database duplicate cleanup
  - Hash-based duplicate detection
  - Keeps oldest, deletes rest
  - Category filtering support
  - Queue: `maintenance`

- **`RunAuditJob`**: Structural and factual audit
  - Processes in chunks of 15
  - Structural audit (4 options, 1 correct)
  - Factual audit via AI API (Gemini/Groq)
  - Queue: `ai-audit`

#### 2. Job Status Tracking

- **`JobStatus` Model** (`app/Models/JobStatus.php`)
  - Stores job metadata, progress, status
  - Statuses: pending, running, completed, failed, cancelled
  - Tracks completed/failed counts
  - Stores metadata (results, error messages)

- **`TracksJobStatus` Trait** (`app/Traits/TracksJobStatus.php`)
  - Provides methods for job status management
  - `createJobStatus()`: Create tracking record
  - `updateJobProgress()`: Update progress percentage
  - `markJobCompleted()`: Mark as successful
  - `markJobFailed()`: Mark as failed with error
  - `getJobStatus()` / `getJobStatusId()`: Access status

- **Migration**: `database/migrations/2026_06_25_000002_create_job_statuses_table.php`

#### 3. Events

- **`JobStatusUpdated` Event** (`app/Events/JobStatusUpdated.php`)
  - Broadcast event for real-time updates
  - Channels: `user.{id}`, `admin.jobs`
  - Broadcast name: `job.status.updated`
  - Data: job_id, status, progress, metadata, message

#### 4. Controller

- **`JobStatusController`** (`app/Http/Controllers/JobStatusController.php`)
  - RESTful endpoints for job management
  - Dashboard rendering
  - Job retry/cancel functionality

#### 5. Routes
Added to `routes/web.php`:
```php
Route::get('/admin/jobs/dashboard', [JobStatusController::class, 'dashboard'])->name('admin.jobs.dashboard');
Route::get('/admin/jobs', [JobStatusController::class, 'index'])->name('admin.jobs.index');
Route::get('/admin/jobs/recent', [JobStatusController::class, 'recent'])->name('admin.jobs.recent');
Route::get('/admin/jobs/{jobId}', [JobStatusController::class, 'show'])->name('admin.jobs.show');
Route::post('/admin/jobs/{jobId}/cancel', [JobStatusController::class, 'cancel'])->name('admin.jobs.cancel');
Route::post('/admin/jobs/{jobId}/retry', [JobStatusController::class, 'retry'])->name('admin.jobs.retry');
Route::get('/admin/jobs/stats', [JobStatusController::class, 'stats'])->name('admin.jobs.stats');
```

#### 6. Frontend

- **`JobDashboard.jsx`** (`resources/js/Pages/Admin/JobDashboard.jsx`)
  - Inertia.js page component
  - Real-time polling for job updates
  - Progress bars and status badges
  - Filter by status (all, pending, running, completed, failed)
  - Retry and cancel actions
  - Statistics overview

## Configuration

### Queue Configuration

In `.env`:
```env
QUEUE_CONNECTION=database

# For production, consider using redis or sqs
# QUEUE_CONNECTION=redis

# Queue worker settings
QUEUE_WORKER_SLEEP=3
QUEUE_WORKER_TIMEOUT=300
```

### Broadcasting Configuration

For real-time updates:
```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster
```

### Queue Worker Setup

Run the queue worker:
```bash
# Single worker
php artisan queue:work

# Multiple workers with specific queues
php artisan queue:work --queue=ai-generation,ai-verification,ai-audit,maintenance --sleep=3 --tries=3 --timeout=300

# Supervisor configuration (production)
# See: https://laravel.com/docs/queues#supervisor-configuration
```

## Usage

### Dispatching Jobs

#### From Controller
```php
// Generate questions
$job = GenerateQuestionsJob::dispatch(
    Auth::id(),
    $categoryId,
    $level,
    $count,
    ['metadata' => 'any_additional_data']
);

// Get job ID for tracking
$jobId = $job->getJobStatusId();

// Verify questions
$job = VerifyQuestionsJob::dispatch(
    Auth::id(),
    $questionIds,
    ['metadata' => 'data']
);

// Clean duplicates
$job = CleanDuplicatesJob::dispatch(
    Auth::id(),
    $categoryId,
    ['metadata' => 'data']
);

// Run audit
$job = RunAuditJob::dispatch(
    Auth::id(),
    $categoryId,
    $limit,
    ['metadata' => 'data']
);
```

#### From Frontend (Inertia)
```javascript
// Dispatch via POST request
router.post('/admin/questions/generate-ai', {
    exam_category_id: 'all',
    level: 'professional',
    count: 50,
}, {
    onSuccess: (response) => {
        // Job ID is in response.props.job_id
        const jobId = response.props.job_id;
        
        // Poll for updates or use broadcasting
        const interval = setInterval(() => {
            axios.get(`/admin/jobs/${jobId}`)
                .then(response => {
                    const job = response.data.job;
                    if (job.is_finished) {
                        clearInterval(interval);
                        // Job complete!
                    }
                });
        }, 3000);
    }
});
```

### Checking Job Status

```php
// Get job status
$jobStatus = JobStatus::find($jobId);

// Check status
if ($jobStatus->isFinished()) {
    if ($jobStatus->isSuccessful()) {
        // Job completed successfully
        $results = $jobStatus->metadata;
    } else {
        // Job failed
        $error = $jobStatus->metadata['error'] ?? 'Unknown error';
    }
}

// Get progress
$percentage = $jobStatus->getPercentageComplete();
```

### Real-time Updates with Broadcasting

Frontend (JavaScript):
```javascript
import Echo from 'laravel-echo';

const echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    forceTLS: true,
});

// Listen for job updates on user channel
echo.private(`user.${userId}`)
    .listen('.job.status.updated', (data) => {
        console.log('Job status updated:', data);
        // Update UI with new status
    });

// Listen for admin-wide job updates (admins only)
echo.private('admin.jobs')
    .listen('.job.status.updated', (data) => {
        console.log('Admin job update:', data);
    });
```

## Job Configuration

Each job has the following configuration:

| Job | Tries | Backoff | Timeout | Queue |
|-----|-------|---------|---------|-------|
| GenerateQuestionsJob | 3 | 60s | 300s | ai-generation |
| VerifyQuestionsJob | 3 | 60s | 300s | ai-verification |
| CleanDuplicatesJob | 1 | - | 600s | maintenance |
| RunAuditJob | 3 | 60s | 600s | ai-audit |

## Progress Tracking

All jobs with `TracksJobStatus` trait automatically:

1. **Create status record** when job starts
2. **Update progress** during execution
3. **Mark as completed** when finished successfully
4. **Mark as failed** when exceptions occur
5. **Broadcast updates** for real-time tracking

### Progress Calculation

Progress is calculated based on:
- **GenerateQuestionsJob**: Categories processed / Total categories
- **VerifyQuestionsJob**: Questions processed / Total questions
- **CleanDuplicatesJob**: Hashes processed / Total duplicate hashes
- **RunAuditJob**: Questions processed / Total questions to audit

## Error Handling

### Automatic Retries
- Jobs automatically retry on failure (configurable tries)
- Exponential backoff between retries (60s default)
- Failed jobs can be manually retried via API

### Error Tracking
- Errors are stored in `metadata['error']`
- Failed jobs have status `failed`
- Error messages are broadcast to frontend

### Fallback Behavior
- If AI API fails, jobs fall back to Mock generator
- Structural validation always runs (no AI required)
- Factual audit skips if AI is not configured

## API Endpoints

### GET /admin/jobs
List all jobs for current user
- Query params: `status`, `limit`
- Returns: Paginated list of jobs

### GET /admin/jobs/recent
Get recent jobs (default: 10)
- Query param: `limit`
- Returns: Array of recent jobs

### GET /admin/jobs/{jobId}
Get specific job details
- Returns: Job object with full details

### GET /admin/jobs/stats
Get job statistics
- Returns: Status counts and recent completed jobs

### POST /admin/jobs/{jobId}/cancel
Cancel a running/pending job
- Returns: Success message and updated job

### POST /admin/jobs/{jobId}/retry
Retry a failed job
- Returns: New job ID

### GET /admin/jobs/dashboard
Render job dashboard page
- Returns: Inertia page with recent jobs and stats

## Frontend Integration

### In Questions Page

Add job status display to the questions index:
```jsx
// In Questions.jsx
const [jobStatus, setJobStatus] = useState(null);

// Check for job_id in flash messages
useEffect(() => {
    if (page.props.flash?.job_id) {
        const interval = setInterval(() => {
            axios.get(`/admin/jobs/${page.props.flash.job_id}`)
                .then(response => {
                    setJobStatus(response.data.job);
                    if (response.data.job.is_finished) {
                        clearInterval(interval);
                    }
                });
        }, 3000);
        
        return () => clearInterval(interval);
    }
}, [page.props.flash]);

// Display job status
{jobStatus && (
    <div className="mb-4 p-3 bg-blue-50 rounded-lg">
        <p>Job Status: {jobStatus.status}</p>
        <progress value={jobStatus.percentage} max="100" />
    </div>
)}
```

### Job Status Badge Component

```jsx
function JobStatusBadge({ jobId }) {
    const [job, setJob] = useState(null);
    
    useEffect(() => {
        if (!jobId) return;
        
        axios.get(`/admin/jobs/${jobId}`)
            .then(response => setJob(response.data.job));
    }, [jobId]);
    
    if (!job) return null;
    
    const statusConfig = {
        pending: 'bg-amber-100 text-amber-800',
        running: 'bg-blue-100 text-blue-800',
        completed: 'bg-emerald-100 text-emerald-800',
        failed: 'bg-red-100 text-red-800',
        cancelled: 'bg-slate-100 text-slate-800',
    };
    
    return (
        <span className={`px-2 py-1 rounded text-xs ${statusConfig[job.status]}`}>
            {job.status} ({job.percentage}%)
        </span>
    );
}
```

## Testing

### Running Tests

```bash
# Run queue job tests
php artisan test --filter=Queue

# Run specific job test
php artisan test --filter=GenerateQuestionsJobTest
```

### Manual Testing

1. **Dispatch a job**:
   ```bash
   php artisan tinker
   App\Jobs\GenerateQuestionsJob::dispatch(1, 'all', 'professional', 10);
   ```

2. **Check job status**:
   ```bash
   App\Models\JobStatus::latest()->first();
   ```

3. **Process the queue**:
   ```bash
   php artisan queue:work --once
   ```

## Troubleshooting

### Common Issues

1. **Jobs not processing**:
   - Check queue worker is running: `ps aux | grep queue:work`
   - Check queue connection in `.env`
   - Check `jobs` table for pending jobs

2. **Broadcast not working**:
   - Check Pusher configuration
   - Check `BROADCAST_DRIVER` in `.env`
   - Check Laravel Echo configuration

3. **Jobs failing silently**:
   - Check Laravel logs: `tail -f storage/logs/laravel.log`
   - Check failed jobs table: `php artisan queue:failed`
   - Retry failed jobs: `php artisan queue:retry all`

4. **Progress not updating**:
   - Check `TracksJobStatus` trait is used in job
   - Check `createJobStatus()` is called in handle()
   - Check `updateJobProgress()` is called during processing

### Debug Commands

```bash
# List all pending jobs
php artisan queue:listen

# Process specific queue
php artisan queue:work --queue=ai-generation

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush

# Monitor queue
php artisan queue:monitor
```

## Performance Considerations

### Batch Sizes
- **GenerateQuestionsJob**: Processes by category, ~10-15 questions per AI call
- **VerifyQuestionsJob**: Chunks of 10 questions
- **RunAuditJob**: Chunks of 15 questions
- **CleanDuplicatesJob**: Processes all duplicates in one transaction

### Memory Usage
- Jobs process in chunks to avoid memory issues
- Database transactions are kept small
- AI API calls are batched to avoid token limits

### Rate Limiting
- Consider adding rate limiting for AI API calls
- Use exponential backoff for retries
- Monitor API quota usage

## Security

### Authorization
- All job endpoints require admin authentication
- Users can only access their own jobs
- Job actions (cancel, retry) validate ownership

### Data Protection
- Job metadata is stored in database
- Sensitive data is not logged
- Error messages are sanitized

## Future Enhancements

1. **Priority Queues**: Add priority levels for jobs
2. **Job Dependencies**: Chain jobs (e.g., generate -> verify -> audit)
3. **Notifications**: Email/Slack notifications on job completion
4. **Rate Limiting**: Per-user job rate limiting
5. **Job Timeout**: Configurable timeout per job type
6. **Progress Estimation**: Better progress estimation for variable-length jobs
7. **Job History**: Archive old jobs for analytics
8. **Export**: Export job results to CSV/Excel

## Migration Guide

### From Synchronous to Queue Jobs

The `AdminQuestionController` now supports both modes:

1. **Default (Queue Mode)**: Jobs are dispatched to background
   ```php
   // In generateAI, cleanDuplicates, runAudit
   // Jobs are automatically dispatched
   ```

2. **Legacy (Synchronous Mode)**: Add `sync=true` parameter
   ```php
   // Force synchronous execution
   POST /admin/questions/generate-ai?sync=true
   ```

3. **Chunk Mode**: Still supported for backward compatibility
   ```php
   // Chunk mode works as before
   POST /admin/questions/generate-ai?chunk_index=0&chunk_size=5
   ```

### Frontend Updates

1. **Add job status polling**:
   ```javascript
   // After dispatching a job, poll for updates
   const jobId = response.props.job_id;
   const interval = setInterval(() => {
       axios.get(`/admin/jobs/${jobId}`)
           .then(response => {
               if (response.data.job.is_finished) {
                   clearInterval(interval);
                   // Refresh page or show notification
               }
           });
   }, 3000);
   ```

2. **Add job dashboard link**:
   ```jsx
   <Link href="/admin/jobs/dashboard">
       View Background Jobs
   </Link>
   ```

3. **Handle job completion notifications**:
   ```javascript
   // Listen for broadcast updates
   echo.private(`user.${userId}`)
       .listen('.job.status.updated', (data) => {
           if (data.job.is_finished) {
               // Show notification
               toast.success(`Job ${data.job.id} completed!`);
           }
       });
   ```

## Files Modified/Created

### New Files
- `app/Jobs/GenerateQuestionsJob.php`
- `app/Jobs/VerifyQuestionsJob.php`
- `app/Jobs/CleanDuplicatesJob.php`
- `app/Jobs/RunAuditJob.php`
- `app/Models/JobStatus.php`
- `app/Traits/TracksJobStatus.php`
- `app/Events/JobStatusUpdated.php`
- `app/Http/Controllers/JobStatusController.php`
- `database/migrations/2026_06_25_000002_create_job_statuses_table.php`
- `resources/js/Pages/Admin/JobDashboard.jsx`

### Modified Files
- `app/Http/Controllers/AdminQuestionController.php`
- `routes/web.php`
- `app/Jobs/*.php` (added TracksJobStatus trait)

## Conclusion

The queue jobs implementation provides a robust, scalable solution for background processing of AI operations in the CSC_Reviewer application. It improves user experience by preventing HTTP timeouts, provides real-time progress tracking, and enables better error handling and retry mechanisms.
