import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faAward,
    faCheck,
    faTimes,
    faExclamationTriangle,
    faCheckCircle,
    faTimesCircle,
    faQuestionCircle,
    faArrowLeft,
    faRedo,
    faLightbulb,
    faChartBar,
    faInfoCircle
} from '@fortawesome/free-solid-svg-icons';

export default function Results({ attempt }) {
    const { score, total_questions, level, completed_at, answers } = attempt;

    const percentage = total_questions > 0 ? Math.round((score / total_questions) * 100) : 0;
    const isPassed = percentage >= 80;

    const incorrectCount = answers.filter(a => a.selected_option_id && !a.is_correct).length;
    const skippedCount = answers.filter(a => !a.selected_option_id).length;

    // Helper to format date
    const formatDate = (dateString) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: 'long',
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
                    <div>
                        <h2 className="text-xl font-bold tracking-tight text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <FontAwesomeIcon icon={faAward} className="text-slate-700 dark:text-slate-305" />
                            Assessment Report
                        </h2>
                        <span className="text-xs text-slate-500 dark:text-slate-400 mt-1 block">
                            Exam completed on {formatDate(completed_at)}
                        </span>
                    </div>
                    <div className="mt-4 flex gap-3 sm:mt-0">
                        <Link
                            href={route('dashboard')}
                            className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition"
                        >
                            <FontAwesomeIcon icon={faArrowLeft} className="mr-1.5 w-3 h-3" />
                            Dashboard
                        </Link>
                        <Link
                            href={route('exams.prepare')}
                            className="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 transition"
                        >
                            <FontAwesomeIcon icon={faRedo} className="mr-1.5 w-3 h-3" />
                            Take Another Exam
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Exam Results Report" />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    
                    {/* Score summary panel */}
                    <div className="bg-white border border-slate-200 rounded-xl p-6 mb-8 dark:bg-slate-800 dark:border-slate-700 flex flex-col md:flex-row items-center gap-6 justify-between">
                        
                        {/* Circle Score chart */}
                        <div className="flex items-center gap-5">
                            <div className="relative flex items-center justify-center w-24 h-24 shrink-0">
                                <svg className="w-full h-full transform -rotate-90">
                                    <circle
                                        cx="48"
                                        cy="48"
                                        r="40"
                                        className="text-slate-100 dark:text-slate-700"
                                        strokeWidth="6"
                                        stroke="currentColor"
                                        fill="transparent"
                                    />
                                    <circle
                                        cx="48"
                                        cy="48"
                                        r="40"
                                        className={`${isPassed ? 'text-slate-850 dark:text-white' : 'text-slate-650 dark:text-slate-300'}`}
                                        strokeWidth="6"
                                        strokeDasharray={2 * Math.PI * 40}
                                        strokeDashoffset={2 * Math.PI * 40 * (1 - percentage / 100)}
                                        strokeLinecap="round"
                                        stroke="currentColor"
                                        fill="transparent"
                                    />
                                </svg>
                                <span className="absolute text-xl font-extrabold text-slate-800 dark:text-slate-100">
                                    {percentage}%
                                </span>
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h3 className="text-2xl font-bold text-slate-800 dark:text-slate-100">
                                        {score} / {total_questions}
                                    </h3>
                                    {isPassed ? (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-800 dark:bg-slate-700 dark:text-slate-350">
                                            <FontAwesomeIcon icon={faCheckCircle} className="w-3.5 h-3.5" />
                                            Passed
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                            <FontAwesomeIcon icon={faExclamationTriangle} className="w-3.5 h-3.5" />
                                            Needs Review
                                        </span>
                                    )}
                                </div>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 uppercase tracking-wider font-semibold">
                                    Level: {level.replace('_', '-')} • Passing threshold is 80%
                                </p>
                            </div>
                        </div>

                        {/* Breakdown Metrics */}
                        <div className="grid grid-cols-3 gap-6 md:border-l md:border-slate-150 md:pl-8 dark:border-slate-700 w-full md:w-auto">
                            <div className="text-center md:text-left">
                                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block flex items-center justify-center md:justify-start gap-1">
                                    <FontAwesomeIcon icon={faCheck} className="text-slate-700 dark:text-slate-400 w-3 h-3" />
                                    Correct
                                </span>
                                <span className="text-xl font-bold text-slate-800 dark:text-slate-100">{score}</span>
                            </div>
                            <div className="text-center md:text-left">
                                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block flex items-center justify-center md:justify-start gap-1">
                                    <FontAwesomeIcon icon={faTimes} className="text-red-500 w-3 h-3" />
                                    Incorrect
                                </span>
                                <span className="text-xl font-bold text-slate-800 dark:text-slate-100">{incorrectCount}</span>
                            </div>
                            <div className="text-center md:text-left">
                                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block flex items-center justify-center md:justify-start gap-1">
                                    <FontAwesomeIcon icon={faQuestionCircle} className="text-slate-400 w-3 h-3" />
                                    Skipped
                                </span>
                                <span className="text-xl font-bold text-slate-800 dark:text-slate-100">{skippedCount}</span>
                            </div>
                        </div>

                    </div>

                    {/* Question by question detail review list */}
                    <div className="space-y-6">
                        <h3 className="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <FontAwesomeIcon icon={faChartBar} className="text-slate-500 w-4 h-4" />
                            Question-by-Question Review
                        </h3>

                        {answers.map((answer, index) => {
                            const question = answer.question;
                            const isCorrect = answer.is_correct;
                            const isSkipped = !answer.selected_option_id;

                            return (
                                <div 
                                    key={answer.id}
                                    className="bg-white border border-slate-200 rounded-xl p-6 dark:bg-slate-800 dark:border-slate-700"
                                >
                                    {/* Question header status */}
                                    <div className="flex justify-between items-start border-b border-slate-100 pb-3 mb-4 dark:border-slate-700">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-xs font-bold text-slate-750 dark:text-slate-350">
                                                Question {index + 1}
                                            </span>
                                            <span className="text-slate-300 dark:text-slate-650">•</span>
                                            <span className="text-xs text-slate-500 dark:text-slate-450 font-semibold">
                                                {question.category.name}
                                            </span>
                                        </div>

                                        <div>
                                            {isSkipped ? (
                                                <span className="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/20 dark:text-amber-455">
                                                    <FontAwesomeIcon icon={faQuestionCircle} className="w-3 h-3" />
                                                    Skipped
                                                </span>
                                            ) : isCorrect ? (
                                                <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-800 dark:bg-slate-700 dark:text-slate-355">
                                                    <FontAwesomeIcon icon={faCheckCircle} className="w-3 h-3" />
                                                    Correct
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1 rounded-md bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-950/20 dark:text-red-410">
                                                    <FontAwesomeIcon icon={faTimesCircle} className="w-3 h-3" />
                                                    Incorrect
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    {/* Question text */}
                                    <p className="text-slate-850 text-sm font-semibold leading-relaxed mb-6 dark:text-slate-205 select-none whitespace-pre-line">
                                        {question.question_text}
                                    </p>

                                    {/* Option List status */}
                                    <div className="space-y-2 mb-4">
                                        {question.options.map((option, optIdx) => {
                                            const label = String.fromCharCode(65 + optIdx);
                                            const isSelected = answer.selected_option_id === option.id;
                                            const isOptionCorrect = option.is_correct;

                                            let optionClass = "w-full flex items-start text-left p-3 text-xs border rounded-lg transition-colors ";
                                            let labelClass = "inline-flex items-center justify-center w-5 h-5 rounded text-xxs font-bold mr-2 border shrink-0 ";

                                            if (isOptionCorrect) {
                                                // Correct option
                                                optionClass += "border-slate-800 bg-slate-50 text-slate-800 dark:border-slate-105 dark:bg-slate-700/50 dark:text-slate-100 font-semibold";
                                                labelClass += "bg-slate-900 text-white border-slate-900 dark:bg-slate-100 dark:text-slate-900 dark:border-white";
                                            } else if (isSelected && !isCorrect) {
                                                // User selected incorrect option
                                                optionClass += "border-red-300 bg-red-50/50 text-red-850 dark:border-red-900/50 dark:bg-red-950/20 dark:text-red-400";
                                                labelClass += "bg-red-650 text-white border-red-650 dark:bg-red-800 dark:border-red-800";
                                            } else {
                                                // Default option style
                                                optionClass += "border-slate-200 dark:border-slate-700 dark:text-slate-400";
                                                labelClass += "bg-slate-50 text-slate-650 border-slate-200 dark:bg-slate-800 dark:border-slate-700";
                                            }

                                            return (
                                                <div key={option.id} className={optionClass}>
                                                    <span className={labelClass}>
                                                        {label}
                                                    </span>
                                                    <span className="pt-0.5 leading-normal">{option.option_text}</span>
                                                    {isOptionCorrect && (
                                                        <span className="ml-auto text-xxs font-bold text-slate-650 dark:text-slate-350 uppercase select-none flex items-center gap-1">
                                                            <FontAwesomeIcon icon={faCheck} />
                                                            Correct Answer
                                                        </span>
                                                    )}
                                                    {isSelected && !isCorrect && (
                                                        <span className="ml-auto text-xxs font-bold text-red-650 dark:text-red-450 uppercase select-none flex items-center gap-1">
                                                            <FontAwesomeIcon icon={faTimes} />
                                                            Your Choice
                                                        </span>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>

                                    {/* Explanation banner */}
                                    {question.explanation && (
                                        <div className="bg-slate-50 border border-slate-200 rounded-lg p-4 mt-4 dark:bg-slate-900/50 dark:border-slate-700/80">
                                            <h4 className="text-xs font-bold text-slate-800 dark:text-slate-355 uppercase tracking-wider mb-1.5 flex items-center gap-1">
                                                <FontAwesomeIcon icon={faLightbulb} className="text-slate-600 dark:text-slate-450" />
                                                Review Explanation
                                            </h4>
                                            <p className="text-xs text-slate-600 leading-relaxed dark:text-slate-400">
                                                {question.explanation}
                                            </p>
                                        </div>
                                    )}

                                </div>
                            );
                        })}
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
