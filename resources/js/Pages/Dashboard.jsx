import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faGraduationCap,
    faTrophy,
    faChartLine,
    faClipboardCheck,
    faArrowRight,
    faBookOpen,
    faHistory,
    faCheckCircle,
    faHourglassHalf,
    faFileAlt
} from '@fortawesome/free-solid-svg-icons';

export default function Dashboard({ categories, recentAttempts, analytics }) {
    const { totalExams, averageScore, readinessScore, categoryPerformance } = analytics;

    // Helper to format date
    const formatDate = (dateString) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-bold tracking-tight text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <FontAwesomeIcon icon={faGraduationCap} className="text-slate-605" />
                        Self-Assessment Dashboard
                    </h2>
                    <Link
                        href={route('exams.prepare')}
                        className="mt-2 inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 sm:mt-0 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 transition"
                    >
                        Start Review / Practice Exam
                        <FontAwesomeIcon icon={faArrowRight} className="ml-2 w-3 h-3" />
                    </Link>
                </div>
            }
        >
            <Head title="Self-Assessment Dashboard" />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    
                    {/* Analytics Overview Grid */}
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-8">
                        {/* Readiness Score Card (Large SVG Progress Indicator) */}
                        <div className="flex flex-col items-center justify-center p-6 bg-white border border-slate-200 rounded-xl dark:bg-slate-800 dark:border-slate-700">
                            <h3 className="text-sm font-bold text-slate-500 dark:text-slate-400 mb-4 flex items-center gap-1.5">
                                <FontAwesomeIcon icon={faChartLine} />
                                Overall Exam Readiness
                            </h3>
                            <div className="relative flex items-center justify-center w-32 h-32">
                                {/* SVG Circular Progress */}
                                <svg className="w-full h-full transform -rotate-90">
                                    <circle
                                        cx="64"
                                        cy="64"
                                        r="52"
                                        className="text-slate-100 dark:text-slate-700"
                                        strokeWidth="8"
                                        stroke="currentColor"
                                        fill="transparent"
                                    />
                                    <circle
                                        cx="64"
                                        cy="64"
                                        r="52"
                                        className="text-slate-900 dark:text-slate-100 transition-all duration-500"
                                        strokeWidth="8"
                                        strokeDasharray={2 * Math.PI * 52}
                                        strokeDashoffset={2 * Math.PI * 52 * (1 - readinessScore / 100)}
                                        strokeLinecap="round"
                                        stroke="currentColor"
                                        fill="transparent"
                                    />
                                </svg>
                                <span className="absolute text-2xl font-bold text-slate-800 dark:text-slate-100">
                                    {readinessScore}%
                                </span>
                            </div>
                            <p className="mt-4 text-xs text-slate-500 dark:text-slate-400 text-center">
                                Based on completed self-assessments
                            </p>
                        </div>

                        {/* Average Score Card */}
                        <div className="flex flex-col justify-between p-6 bg-white border border-slate-200 rounded-xl dark:bg-slate-800 dark:border-slate-700">
                            <div>
                                <h3 className="text-sm font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                    <FontAwesomeIcon icon={faTrophy} />
                                    Average Score
                                </h3>
                                <p className="mt-2 text-4xl font-extrabold text-slate-800 dark:text-slate-100">
                                    {averageScore}%
                                </p>
                            </div>
                            <div className="mt-4">
                                <div className="w-full bg-slate-100 rounded-full h-2 dark:bg-slate-700">
                                    <div 
                                        className="bg-slate-700 h-2 rounded-full dark:bg-slate-300" 
                                        style={{ width: `${averageScore}%` }}
                                    ></div>
                                </div>
                                <span className="text-xs text-slate-500 dark:text-slate-400 mt-2 block">
                                    Target readiness is 80%+
                                </span>
                            </div>
                        </div>

                        {/* Total Exams Card */}
                        <div className="flex flex-col justify-between p-6 bg-white border border-slate-200 rounded-xl dark:bg-slate-800 dark:border-slate-700">
                            <div>
                                <h3 className="text-sm font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                    <FontAwesomeIcon icon={faClipboardCheck} />
                                    Completed Attempts
                                </h3>
                                <p className="mt-2 text-4xl font-extrabold text-slate-800 dark:text-slate-100">
                                    {totalExams}
                                </p>
                            </div>
                            <div className="mt-4 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span>Recent: {recentAttempts.length > 0 ? formatDate(recentAttempts[0].created_at) : 'None'}</span>
                            </div>
                        </div>
                    </div>

                    {/* Category-by-Category Breakdown */}
                    <div className="mb-8">
                        <h3 className="text-lg font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2">
                            <FontAwesomeIcon icon={faBookOpen} className="text-slate-500" />
                            Readiness by Exam Category
                        </h3>
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            {categoryPerformance.map((category) => (
                                <div 
                                    key={category.category_id}
                                    className="p-5 bg-white border border-slate-200 rounded-xl dark:bg-slate-800 dark:border-slate-700"
                                >
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <h4 className="font-bold text-slate-800 dark:text-slate-100 text-sm">
                                                {category.name}
                                            </h4>
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xxs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 uppercase mt-1">
                                                {category.level === 'both' ? 'Prof & Sub-Prof' : category.level}
                                            </span>
                                        </div>
                                        <span className="text-base font-bold text-slate-800 dark:text-slate-100 bg-slate-50 px-2 py-1 rounded dark:bg-slate-900">
                                            {category.percentage}%
                                        </span>
                                    </div>
                                    
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-2 line-clamp-2 leading-relaxed">
                                        {category.description}
                                    </p>

                                    <div className="mt-4">
                                        <div className="flex justify-between items-center text-xs text-slate-500 dark:text-slate-400 mb-1">
                                            <span>Category Progress</span>
                                            <span>{category.correct}/{category.answered} correct responses</span>
                                        </div>
                                        <div className="w-full bg-slate-100 rounded-full h-1.5 dark:bg-slate-700">
                                            <div 
                                                className="bg-slate-805 h-1.5 rounded-full dark:bg-slate-200" 
                                                style={{ width: `${category.percentage}%` }}
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Recent Exam Attempts History */}
                    <div>
                        <h3 className="text-lg font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2">
                            <FontAwesomeIcon icon={faHistory} className="text-slate-500" />
                            Recent Attempts History
                        </h3>
                        <div className="overflow-x-auto bg-white border border-slate-200 rounded-xl dark:bg-slate-800 dark:border-slate-700">
                            {recentAttempts.length === 0 ? (
                                <div className="p-8 text-center text-slate-500 dark:text-slate-400">
                                    No exam attempts found. Click "Start Review / Practice Exam" above to begin your self-assessment.
                                </div>
                            ) : (
                                <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                    <thead className="bg-slate-50 dark:bg-slate-800/50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                Exam Date
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                Exam Level
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                Status
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                Score
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                Percentage
                                            </th>
                                            <th className="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-800">
                                        {recentAttempts.map((attempt) => {
                                            const pct = attempt.total_questions > 0 
                                                ? Math.round((attempt.score / attempt.total_questions) * 100) 
                                                : 0;
                                            
                                            return (
                                                <tr key={attempt.id}>
                                                    <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-800 dark:text-slate-205">
                                                        {formatDate(attempt.created_at)}
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-800 dark:text-slate-205 capitalize">
                                                        {attempt.level.replace('_', '-')}
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-sm">
                                                        {attempt.status === 'completed' ? (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-800 dark:bg-slate-700 dark:text-slate-300">
                                                                <FontAwesomeIcon icon={faCheckCircle} className="text-slate-600 dark:text-slate-400" />
                                                                Completed
                                                            </span>
                                                        ) : (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 animate-pulse">
                                                                <FontAwesomeIcon icon={faHourglassHalf} />
                                                                In Progress
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-800 dark:text-slate-205">
                                                        {attempt.status === 'completed' 
                                                            ? `${attempt.score} / ${attempt.total_questions}` 
                                                            : '--'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-800 dark:text-slate-205">
                                                        {attempt.status === 'completed' ? `${pct}%` : '--'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-right text-sm font-semibold">
                                                        {attempt.status === 'completed' ? (
                                                            <Link
                                                                href={route('exams.results', attempt.id)}
                                                                className="text-slate-800 hover:text-slate-900 hover:underline dark:text-slate-300 dark:hover:text-slate-100 flex items-center justify-end gap-1"
                                                            >
                                                                <FontAwesomeIcon icon={faFileAlt} className="w-3 h-3" />
                                                                Review Answers
                                                            </Link>
                                                        ) : (
                                                            <Link
                                                                href={route('exams.show', attempt.id)}
                                                                className="text-slate-850 hover:text-slate-950 hover:underline dark:text-slate-300 dark:hover:text-slate-100 font-bold flex items-center justify-end gap-1.5"
                                                            >
                                                                <FontAwesomeIcon icon={faArrowRight} className="w-3 h-3" />
                                                                Resume Exam
                                                            </Link>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
