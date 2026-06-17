import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { renderQuestionContent } from '@/Components/AbstractReasoningDiagram';
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
    faInfoCircle,
    faCheckDouble
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

    const scrollToQuestion = (index) => {
        const element = document.getElementById(`question-card-${index}`);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <FontAwesomeIcon icon={faAward} className="text-slate-700 dark:text-slate-305" />
                            Assessment Report
                        </h1>
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
            <Head>
                <title>Civil Service Exam Assessment Report | Zepo</title>
                <meta name="description" content="View your detailed Civil Service Exam practice session results, correct answers, explanation walkthroughs, and success rates." />
            </Head>

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    
                    {/* Score summary panel (Full-Width) */}
                    <div className="bg-white border border-slate-200 rounded-xl p-6 mb-6 dark:bg-slate-800 dark:border-slate-700 flex flex-col md:flex-row items-center gap-6 justify-between">
                        
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
                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">
                                            <FontAwesomeIcon icon={faCheckCircle} className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
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
                                    <FontAwesomeIcon icon={faCheck} className="text-emerald-600 dark:text-emerald-400 w-3 h-3" />
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

                    {/* Main Split Layout */}
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-4 items-start">
                        
                        {/* Summary and Details Column */}
                        <div className="lg:col-span-3 space-y-6 order-2 lg:order-1">

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
                                    id={`question-card-${index}`}
                                    className="bg-white border border-slate-200 rounded-xl p-6 dark:bg-slate-800 dark:border-slate-700 scroll-mt-6"
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
                                                <span className="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-400">
                                                    <FontAwesomeIcon icon={faCheckCircle} className="w-3 h-3 text-emerald-600 dark:text-emerald-400" />
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
                                    <p className="text-slate-855 text-sm font-semibold leading-relaxed mb-6 dark:text-slate-205 select-none whitespace-pre-line">
                                        {renderQuestionContent(question.question_text.replace(/\s*\(Variation ID:\s*\d+\)/gi, ''))}
                                    </p>

                                    {/* Option List status */}
                                    <div className="space-y-2 mb-4">
                                        {question.options.map((option, optIdx) => {
                                            const label = String.fromCharCode(65 + optIdx);
                                            const isSelected = answer.selected_option_id === option.id;
                                            const isOptionCorrect = option.is_correct;

                                            let optionClass = "w-full flex items-center text-left p-3 text-xs border rounded-lg transition-colors ";
                                            let labelClass = "inline-flex items-center justify-center w-5 h-5 rounded text-xxs font-bold mr-2 border shrink-0 ";

                                            if (isOptionCorrect) {
                                                // Correct option
                                                optionClass += "border-emerald-500 bg-emerald-50/30 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-950/20 dark:text-emerald-300 font-semibold";
                                                labelClass += "bg-emerald-600 text-white border-emerald-600 dark:bg-emerald-500 dark:text-white dark:border-emerald-500";
                                            } else if (isSelected && !isCorrect) {
                                                // User selected incorrect option
                                                optionClass += "border-red-300 bg-red-50/50 text-red-800 dark:border-red-900/50 dark:bg-red-950/20 dark:text-red-400";
                                                labelClass += "bg-red-600 text-white border-red-600 dark:bg-red-800 dark:border-red-800";
                                            } else {
                                                // Default option style
                                                optionClass += "border-slate-200 dark:border-slate-700 dark:text-slate-400";
                                                labelClass += "bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-800 dark:border-slate-700";
                                            }

                                            return (
                                                <div key={option.id} className={optionClass}>
                                                    <span className={labelClass}>
                                                        {label}
                                                    </span>
                                                    <span className="pt-0.5 leading-normal flex-1">{renderQuestionContent(option.option_text)}</span>
                                                    {isOptionCorrect && (
                                                        <span className="ml-auto text-xxs font-bold text-emerald-600 dark:text-emerald-400 uppercase select-none flex items-center gap-1">
                                                            <FontAwesomeIcon icon={faCheck} />
                                                            Correct Answer
                                                        </span>
                                                    )}
                                                    {isSelected && !isCorrect && (
                                                        <span className="ml-auto text-xxs font-bold text-red-600 dark:text-red-400 uppercase select-none flex items-center gap-1">
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

                    {/* Right Column: Answer Map Sidebar */}
                    <div className="space-y-6 lg:sticky lg:top-6 order-1 lg:order-2">
                        <div className="bg-white border border-slate-200 rounded-xl p-5 dark:bg-slate-800 dark:border-slate-700">
                            <h3 className="text-sm font-bold text-slate-800 dark:text-slate-205 mb-4 uppercase tracking-wider flex items-center gap-1.5">
                                <FontAwesomeIcon icon={faCheckDouble} className="text-slate-500" />
                                Answer Map
                            </h3>

                            <div className="max-h-[380px] overflow-y-auto pr-2 mb-6 scrollbar-thin">
                                <div className="grid grid-cols-5 gap-2">
                                    {answers.map((answer, index) => {
                                        const isCorrect = answer.is_correct;
                                        const isSkipped = !answer.selected_option_id;

                                        let btnClasses = "relative flex items-center justify-center h-10 w-full rounded-lg text-xs font-bold border transition ";

                                        if (isSkipped) {
                                            btnClasses += "bg-amber-500 border-amber-500 text-white hover:bg-amber-600 ";
                                        } else if (isCorrect) {
                                            btnClasses += "bg-emerald-600 border-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-500 dark:border-emerald-500 dark:hover:bg-emerald-600 ";
                                        } else {
                                            btnClasses += "bg-red-600 border-red-600 text-white hover:bg-red-700 dark:bg-red-500 dark:border-red-500 dark:hover:bg-red-600 ";
                                        }

                                        return (
                                            <button
                                                key={answer.id}
                                                onClick={() => scrollToQuestion(index)}
                                                className={btnClasses}
                                                title={`Question ${index + 1}: ${isSkipped ? 'Skipped' : isCorrect ? 'Correct' : 'Incorrect'}`}
                                            >
                                                {index + 1}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="flex flex-col gap-2 pt-4 border-t border-slate-100 dark:border-slate-700 text-xxs font-semibold text-slate-500 dark:text-slate-400">
                                <div className="flex items-center gap-2">
                                    <span className="w-3 h-3 bg-emerald-600 border border-emerald-600 rounded dark:bg-emerald-500"></span>
                                    <span>Correct</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="w-3 h-3 bg-red-600 border border-red-600 rounded dark:bg-red-500"></span>
                                    <span>Incorrect</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="w-3 h-3 bg-amber-500 border border-amber-500 rounded"></span>
                                    <span>Skipped</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
