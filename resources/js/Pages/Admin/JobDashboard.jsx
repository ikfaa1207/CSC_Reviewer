import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faSpinner,
    faCheckCircle,
    faExclamationCircle,
    faClock,
    faTimesCircle,
    faBan,
    faSyncAlt,
    faPlay,
    faTrash,
    faChartBar,
    faTasks,
    faRobot,
    faCopy,
    faSearch,
    faFilter,
} from '@fortawesome/free-solid-svg-icons';

// ─── Status Configuration ──────────────────────────────────────────────────
const STATUS_CONFIG = {
    pending: {
        label: 'Pending',
        icon: faClock,
        color: 'text-amber-600',
        bg: 'bg-amber-50',
        darkBg: 'dark:bg-amber-950/30',
        darkText: 'dark:text-amber-300',
    },
    running: {
        label: 'Running',
        icon: faSpinner,
        color: 'text-blue-600',
        bg: 'bg-blue-50',
        darkBg: 'dark:bg-blue-950/30',
        darkText: 'dark:text-blue-300',
        spin: true,
    },
    completed: {
        label: 'Completed',
        icon: faCheckCircle,
        color: 'text-emerald-600',
        bg: 'bg-emerald-50',
        darkBg: 'dark:bg-emerald-950/30',
        darkText: 'dark:text-emerald-300',
    },
    failed: {
        label: 'Failed',
        icon: faExclamationCircle,
        color: 'text-red-600',
        bg: 'bg-red-50',
        darkBg: 'dark:bg-red-950/30',
        darkText: 'dark:text-red-300',
    },
    cancelled: {
        label: 'Cancelled',
        icon: faTimesCircle,
        color: 'text-slate-600',
        bg: 'bg-slate-50',
        darkBg: 'dark:bg-slate-950/30',
        darkText: 'dark:text-slate-300',
    },
};

// ─── Job Type Labels ───────────────────────────────────────────────────────
const JOB_TYPE_LABELS = {
    'App\\Jobs\\GenerateQuestionsJob': 'Question Generation',
    'App\\Jobs\\VerifyQuestionsJob': 'Question Verification',
    'App\\Jobs\\CleanDuplicatesJob': 'Duplicate Cleanup',
    'App\\Jobs\\RunAuditJob': 'Audit',
};

// ─── Format Date ───────────────────────────────────────────────────────────
function formatDate(isoString) {
    if (!isoString) return '—';
    return new Date(isoString).toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

// ─── Format Duration ───────────────────────────────────────────────────────
function formatDuration(startedAt, completedAt) {
    if (!startedAt) return '—';
    
    const start = new Date(startedAt);
    const end = completedAt ? new Date(completedAt) : new Date();
    const diff = (end - start) / 1000; // in seconds
    
    if (diff < 60) return `${Math.round(diff)}s`;
    if (diff < 3600) return `${Math.round(diff / 60)}m`;
    return `${Math.round(diff / 3600)}h`;
}

// ─── Status Badge ─────────────────────────────────────────────────────────
function StatusBadge({ status }) {
    const config = STATUS_CONFIG[status] || STATUS_CONFIG.pending;
    const Icon = config.icon;
    
    return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${config.color} ${config.bg} ${config.darkBg} ${config.darkText}`}>
            <FontAwesomeIcon icon={Icon} className={`w-3 h-3 ${config.spin ? 'animate-spin' : ''}`} />
            {config.label}
        </span>
    );
}

// ─── Progress Bar ──────────────────────────────────────────────────────────
function ProgressBar({ progress, total }) {
    const percentage = total > 0 ? Math.round((progress / total) * 100) : 0;
    
    return (
        <div className="w-full bg-slate-100 rounded-full h-2 dark:bg-slate-700">
            <div
                className="bg-blue-600 h-2 rounded-full transition-all duration-300 ease-out"
                style={{ width: `${Math.min(percentage, 100)}%` }}
            />
            <div className="flex justify-between text-xs text-slate-500 dark:text-slate-400 mt-1">
                <span>{progress} / {total}</span>
                <span>{percentage}%</span>
            </div>
        </div>
    );
}

// ─── Job Card ──────────────────────────────────────────────────────────────
function JobCard({ job, onRetry, onCancel }) {
    const [isPolling, setIsPolling] = useState(false);
    const [jobData, setJobData] = useState(job);
    
    // Poll for updates if job is still running
    useEffect(() => {
        if (!jobData.is_finished && jobData.status !== 'failed') {
            const interval = setInterval(() => {
                axios.get(`/admin/jobs/${jobData.id}`)
                    .then(response => {
                        setJobData(response.data.job);
                    })
                    .catch(error => {
                        console.error('Error polling job status:', error);
                    });
            }, 3000);
            
            return () => clearInterval(interval);
        }
    }, [jobData.id, jobData.is_finished, jobData.status]);

    const getJobTypeLabel = (jobClass) => {
        return JOB_TYPE_LABELS[jobClass] || jobClass.split('\\').pop() || jobClass;
    };

    const getMetadataSummary = (metadata) => {
        if (!metadata) return null;
        
        const parts = [];
        if (metadata.saved_count !== undefined) {
            parts.push(`${metadata.saved_count} saved`);
        }
        if (metadata.skipped_count !== undefined) {
            parts.push(`${metadata.skipped_count} skipped`);
        }
        if (metadata.verified_count !== undefined) {
            parts.push(`${metadata.verified_count} verified`);
        }
        if (metadata.failed_count !== undefined) {
            parts.push(`${metadata.failed_count} failed`);
        }
        if (metadata.deleted_count !== undefined) {
            parts.push(`${metadata.deleted_count} deleted`);
        }
        if (metadata.audited_count !== undefined) {
            parts.push(`${metadata.audited_count} audited`);
        }
        
        return parts.length > 0 ? parts.join(', ') : null;
    };

    const metadataSummary = getMetadataSummary(jobData.metadata);
    
    return (
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden transition-all duration-200 hover:shadow-md">
            <div className="p-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                            <FontAwesomeIcon icon={faRobot} className="w-4 h-4 text-slate-500 dark:text-slate-400" />
                            <h3 className="font-semibold text-slate-800 dark:text-slate-200 truncate">
                                {getJobTypeLabel(jobData.job_class)}
                            </h3>
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Job ID: {jobData.id}
                        </p>
                    </div>
                    <div className="flex-shrink-0">
                        <StatusBadge status={jobData.status} />
                    </div>
                </div>

                {metadataSummary && (
                    <p className="text-sm text-slate-600 dark:text-slate-300 mt-2">
                        {metadataSummary}
                    </p>
                )}

                {jobData.total > 0 && (
                    <div className="mt-3">
                        <ProgressBar progress={jobData.completed} total={jobData.total} />
                    </div>
                )}

                <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 mt-3">
                    <div className="flex items-center gap-3">
                        <span>Started: {formatDate(jobData.started_at)}</span>
                        {jobData.completed_at && (
                            <span>Completed: {formatDate(jobData.completed_at)}</span>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-slate-400">{formatDuration(jobData.started_at, jobData.completed_at)}</span>
                        
                        {/* Action buttons */}
                        {jobData.status === 'failed' && (
                            <button
                                onClick={() => onRetry(jobData.id)}
                                className="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition flex items-center gap-1"
                            >
                                <FontAwesomeIcon icon={faSyncAlt} className="w-3 h-3" />
                                Retry
                            </button>
                        )}
                        
                        {['pending', 'running'].includes(jobData.status) && (
                            <button
                                onClick={() => onCancel(jobData.id)}
                                className="text-xs text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition flex items-center gap-1"
                            >
                                <FontAwesomeIcon icon={faBan} className="w-3 h-3" />
                                Cancel
                            </button>
                        )}
                    </div>
                </div>

                {jobData.error && (
                    <div className="mt-3 p-2 bg-red-50 text-red-700 text-xs rounded-lg dark:bg-red-950/30 dark:text-red-300">
                        <FontAwesomeIcon icon={faExclamationCircle} className="w-3 h-3 mr-1" />
                        {jobData.error}
                    </div>
                )}
            </div>
        </div>
    );
}

// ─── Stats Card ────────────────────────────────────────────────────────────
function StatsCard({ icon, label, value, color }) {
    return (
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 transition-all duration-200 hover:shadow-md">
            <div className="flex items-center gap-3">
                <div className={`p-2 rounded-lg ${color}`}>
                    <FontAwesomeIcon icon={icon} className="w-5 h-5" />
                </div>
                <div>
                    <p className="text-2xl font-bold text-slate-800 dark:text-slate-200">{value}</p>
                    <p className="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider">{label}</p>
                </div>
            </div>
        </div>
    );
}

// ─── Main Component ─────────────────────────────────────────────────────────
export default function JobDashboard({ recentJobs: initialJobs, statusCounts }) {
    const [jobs, setJobs] = useState(initialJobs);
    const [loading, setLoading] = useState(false);
    const [filter, setFilter] = useState('all');
    
    const handleRetry = (jobId) => {
        if (window.confirm('Are you sure you want to retry this job?')) {
            setLoading(true);
            router.post(`/admin/jobs/${jobId}/retry`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    setLoading(false);
                    // Refresh the page to get updated data
                    router.reload();
                },
                onError: () => {
                    setLoading(false);
                },
            });
        }
    };

    const handleCancel = (jobId) => {
        if (window.confirm('Are you sure you want to cancel this job?')) {
            setLoading(true);
            router.post(`/admin/jobs/${jobId}/cancel`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    setLoading(false);
                    router.reload();
                },
                onError: () => {
                    setLoading(false);
                },
            });
        }
    };

    const filteredJobs = filter === 'all' 
        ? jobs 
        : jobs.filter(job => job.status === filter);

    // Calculate stats from statusCounts
    const totalJobs = Object.values(statusCounts).reduce((sum, count) => sum + count, 0);
    const runningJobs = statusCounts.running || 0;
    const pendingJobs = statusCounts.pending || 0;
    const completedJobs = statusCounts.completed || 0;
    const failedJobs = statusCounts.failed || 0;

    return (
        <AuthenticatedLayout>
            <Head title="Job Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-800 dark:text-slate-200">
                            Background Jobs
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Monitor and manage your background processing tasks
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-slate-500 dark:text-slate-400">
                            Auto-refresh every 3s
                        </span>
                    </div>
                </div>

                {/* Stats Overview */}
                <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    <StatsCard
                        icon={faTasks}
                        label="Total"
                        value={totalJobs}
                        color="bg-slate-100 dark:bg-slate-700"
                    />
                    <StatsCard
                        icon={faClock}
                        label="Pending"
                        value={pendingJobs}
                        color="bg-amber-100 dark:bg-amber-950/30"
                    />
                    <StatsCard
                        icon={faSpinner}
                        label="Running"
                        value={runningJobs}
                        color="bg-blue-100 dark:bg-blue-950/30"
                    />
                    <StatsCard
                        icon={faCheckCircle}
                        label="Completed"
                        value={completedJobs}
                        color="bg-emerald-100 dark:bg-emerald-950/30"
                    />
                    <StatsCard
                        icon={faExclamationCircle}
                        label="Failed"
                        value={failedJobs}
                        color="bg-red-100 dark:bg-red-950/30"
                    />
                </div>

                {/* Filter Controls */}
                <div className="flex items-center gap-2 overflow-x-auto pb-2">
                    <button
                        onClick={() => setFilter('all')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-medium transition ${filter === 'all' 
                            ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-800' 
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600'}`}
                    >
                        All ({totalJobs})
                    </button>
                    <button
                        onClick={() => setFilter('pending')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-medium transition ${filter === 'pending' 
                            ? 'bg-amber-600 text-white dark:bg-amber-500' 
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600'}`}
                    >
                        Pending ({pendingJobs})
                    </button>
                    <button
                        onClick={() => setFilter('running')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-medium transition ${filter === 'running' 
                            ? 'bg-blue-600 text-white dark:bg-blue-500' 
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600'}`}
                    >
                        Running ({runningJobs})
                    </button>
                    <button
                        onClick={() => setFilter('completed')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-medium transition ${filter === 'completed' 
                            ? 'bg-emerald-600 text-white dark:bg-emerald-500' 
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600'}`}
                    >
                        Completed ({completedJobs})
                    </button>
                    <button
                        onClick={() => setFilter('failed')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-medium transition ${filter === 'failed' 
                            ? 'bg-red-600 text-white dark:bg-red-500' 
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600'}`}
                    >
                        Failed ({failedJobs})
                    </button>
                </div>

                {/* Jobs List */}
                <div className="space-y-4">
                    {filteredJobs.length === 0 ? (
                        <div className="text-center py-12 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700">
                            <FontAwesomeIcon icon={faTasks} className="w-12 h-12 text-slate-400 mx-auto mb-3" />
                            <p className="text-slate-500 dark:text-slate-400">
                                {filter === 'all' 
                                    ? 'No background jobs found' 
                                    : `No ${filter} jobs found`}
                            </p>
                        </div>
                    ) : (
                        filteredJobs.map(job => (
                            <JobCard
                                key={job.id}
                                job={job}
                                onRetry={handleRetry}
                                onCancel={handleCancel}
                            />
                        ))
                    )}
                </div>

                {/* Loading Overlay */}
                {loading && (
                    <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50">
                        <div className="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-xl">
                            <div className="flex items-center gap-3">
                                <FontAwesomeIcon icon={faSpinner} className="w-6 h-6 text-blue-600 animate-spin" />
                                <span className="text-slate-800 dark:text-slate-200">Processing...</span>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
