import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import ConfirmationModal from '@/Components/ConfirmationModal';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { renderQuestionContent } from '@/Components/AbstractReasoningDiagram';
import {
    faClock,
    faFlag,
    faChevronLeft,
    faChevronRight,
    faSpinner,
    faSignOutAlt,
    faCheckDouble
} from '@fortawesome/free-solid-svg-icons';

export default function Take({ attempt }) {
    const [answers, setAnswers] = useState(attempt.answers);
    const [currentIndex, setCurrentIndex] = useState(0);
    const [saving, setSaving] = useState(false);
    const [timeLeft, setTimeLeft] = useState(null);
    const [submitModalOpen, setSubmitModalOpen] = useState(false);
    const [submitMessage, setSubmitMessage] = useState('');

    const totalQuestions = attempt.total_questions;
    const currentAnswer = answers[currentIndex];
    const currentQuestion = currentAnswer.question;

    // Timer logic
    useEffect(() => {
        if (attempt.mode === 'review') return;

        // Calculate limit: 2 minutes per question
        const timeLimitSeconds = totalQuestions * 120;
        const startedAt = new Date(attempt.started_at).getTime();
        
        const updateTimer = () => {
            const now = new Date().getTime();
            const elapsedSeconds = Math.floor((now - startedAt) / 1000);
            const remaining = timeLimitSeconds - elapsedSeconds;
            
            if (remaining <= 0) {
                setTimeLeft(0);
                clearInterval(interval);
                handleAutoSubmit();
            } else {
                setTimeLeft(remaining);
            }
        };

        // Initialize immediately
        updateTimer();
        const interval = setInterval(updateTimer, 1000);

        return () => clearInterval(interval);
    }, [attempt]);

    const handleAutoSubmit = () => {
        // If time expires, force submit immediately without prompt
        submitExam();
    };

    const submitExam = () => {
        router.post(route('exams.submit', attempt.id));
    };

    // Format remaining seconds into MM:SS or HH:MM:SS
    const formatTime = (seconds) => {
        if (seconds === null) return '--:--';
        if (seconds <= 0) return '00:00';
        
        const hrs = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hrs > 0) {
            return `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    };

    // Save user choice in the background using Axios
    const handleSelectOption = async (optionId) => {
        if (currentAnswer.selected_option_id === optionId) return;

        setSaving(true);
        const updatedAnswers = [...answers];
        updatedAnswers[currentIndex] = {
            ...currentAnswer,
            selected_option_id: optionId
        };
        setAnswers(updatedAnswers);

        try {
            await axios.post(route('exams.saveAnswer', currentAnswer.id), {
                selected_option_id: optionId,
                is_flagged: currentAnswer.is_flagged ? 1 : 0
            });
        } catch (err) {
            console.error('Failed to auto-save answer:', err);
        } finally {
            setSaving(false);
        }
    };

    // Toggle Flag for Review in background
    const handleToggleFlag = async () => {
        setSaving(true);
        const updatedFlag = !currentAnswer.is_flagged;
        
        const updatedAnswers = [...answers];
        updatedAnswers[currentIndex] = {
            ...currentAnswer,
            is_flagged: updatedFlag
        };
        setAnswers(updatedAnswers);

        try {
            await axios.post(route('exams.saveAnswer', currentAnswer.id), {
                selected_option_id: currentAnswer.selected_option_id,
                is_flagged: updatedFlag ? 1 : 0
            });
        } catch (err) {
            console.error('Failed to toggle flag:', err);
        } finally {
            setSaving(false);
        }
    };

    const handleNext = () => {
        if (currentIndex < totalQuestions - 1) {
            setCurrentIndex(currentIndex + 1);
        }
    };

    const handlePrevious = () => {
        if (currentIndex > 0) {
            setCurrentIndex(currentIndex - 1);
        }
    };

    const confirmSubmit = () => {
        const unansweredCount = answers.filter(a => !a.selected_option_id).length;
        let msg = 'Are you sure you want to finish and submit your exam?';
        if (unansweredCount > 0) {
            msg = `You have left ${unansweredCount} question(s) unanswered. Are you sure you want to submit?`;
        }
        setSubmitMessage(msg);
        setSubmitModalOpen(true);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight text-slate-800 dark:text-slate-100 uppercase flex items-center gap-2">
                            {attempt.level.replace('_', ' ')} Exam
                        </h1>
                        <span className="text-xs text-slate-500 dark:text-slate-400 mt-1 block">
                            Self-Assessment Practice Session
                        </span>
                    </div>

                    <div className="flex items-center gap-4">
                        {/* Saving Spinner Indicator */}
                        {saving && (
                            <span className="inline-flex items-center text-xs text-slate-400 dark:text-slate-500 animate-pulse">
                                <FontAwesomeIcon icon={faSpinner} className="animate-spin mr-1.5" />
                                Saving...
                            </span>
                        )}

                        {/* Timer / Mode Indicator */}
                        {attempt.mode === 'review' ? (
                            <div className="flex items-center gap-2 bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-lg dark:bg-slate-800 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                Self-Guided Review
                            </div>
                        ) : (
                            <div className="flex items-center gap-2 bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-lg dark:bg-slate-800 dark:border-slate-700">
                                <FontAwesomeIcon icon={faClock} className="text-slate-500 dark:text-slate-400" />
                                <span className={`font-mono text-sm font-bold ${timeLeft !== null && timeLeft < 120 ? 'text-red-600 dark:text-red-400 animate-pulse' : 'text-slate-700 dark:text-slate-300'}`}>
                                    {formatTime(timeLeft)}
                                </span>
                            </div>
                        )}
                    </div>
                </div>
            }
        >
            <Head>
                <title>Taking Civil Service Practice Exam | Zepo</title>
                <meta name="description" content="Active Civil Service Exam practice session. Answer questions, flag them for review, and track your progress in real-time." />
            </Head>

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    
                    {/* Main Split Layout */}
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-4 items-start">
                        
                        {/* Question and Option Panel */}
                        <div className="lg:col-span-3 bg-white border border-slate-200 rounded-xl p-6 flex flex-col justify-between min-h-[450px] dark:bg-slate-800 dark:border-slate-700">
                            <div>
                                {/* Category Header */}
                                <div className="flex justify-between items-center border-b border-slate-100 pb-3 mb-5 dark:border-slate-700">
                                    <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Question {currentIndex + 1} of {totalQuestions} • {currentQuestion.category.name}
                                    </span>
                                    <button
                                        onClick={handleToggleFlag}
                                        className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold transition ${currentAnswer.is_flagged ? 'bg-amber-100 text-amber-800 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800' : 'bg-slate-55 text-slate-600 border border-slate-200 hover:bg-slate-100 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-650'}`}
                                    >
                                        <FontAwesomeIcon icon={faFlag} className={currentAnswer.is_flagged ? 'text-amber-600' : ''} />
                                        {currentAnswer.is_flagged ? 'Flagged for Review' : 'Flag for Review'}
                                    </button>
                                </div>

                                {/* Question Text */}
                                <div className="text-slate-800 text-base leading-relaxed font-medium mb-8 dark:text-slate-101 select-none whitespace-pre-line">
                                    {renderQuestionContent(currentQuestion.question_text.replace(/\s*\(Variation ID:\s*\d+\)/gi, ''))}
                                </div>

                                {/* Multiple Choice Options */}
                                <div className="space-y-3">
                                    {currentQuestion.options.map((option, idx) => {
                                        const label = String.fromCharCode(65 + idx); // A, B, C, D
                                        const isSelected = currentAnswer.selected_option_id === option.id;
                                        
                                        return (
                                            <button
                                                key={option.id}
                                                onClick={() => handleSelectOption(option.id)}
                                                className={`w-full flex items-center text-left p-4 border rounded-xl transition ${isSelected ? 'border-slate-800 bg-slate-50 text-slate-800 shadow-sm dark:border-slate-105 dark:bg-slate-700/50 dark:text-slate-100' : 'border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-750 dark:text-slate-300'}`}
                                            >
                                                <span className={`inline-flex items-center justify-center w-6 h-6 rounded-md text-xs font-bold mr-3 border shrink-0 ${isSelected ? 'bg-slate-900 text-white border-slate-900 dark:bg-slate-100 dark:text-slate-900 dark:border-white' : 'bg-slate-55 text-slate-650 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700'}`}>
                                                    {label}
                                                </span>
                                                <span className="text-sm pt-0.5 leading-normal flex-1">{renderQuestionContent(option.option_text)}</span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Bottom Navigation Buttons */}
                            <div className="flex justify-between items-center mt-8 pt-4 border-t border-slate-100 dark:border-slate-700">
                                <button
                                    onClick={handlePrevious}
                                    disabled={currentIndex === 0}
                                    className="px-4 py-2 border border-slate-350 text-sm font-semibold rounded-lg text-slate-700 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:hover:bg-white dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:disabled:hover:bg-slate-800 flex items-center gap-1.5 transition"
                                >
                                    <FontAwesomeIcon icon={faChevronLeft} />
                                    Previous
                                </button>
                                
                                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400">
                                    Progress: {answers.filter(a => a.selected_option_id).length} / {totalQuestions} Answered
                                </span>

                                <button
                                    onClick={handleNext}
                                    disabled={currentIndex === totalQuestions - 1}
                                    className="px-4 py-2 border border-slate-350 text-sm font-semibold rounded-lg text-slate-700 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:hover:bg-white dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:disabled:hover:bg-slate-800 flex items-center gap-1.5 transition"
                                >
                                    Next
                                    <FontAwesomeIcon icon={faChevronRight} />
                                </button>
                            </div>
                        </div>

                        {/* Navigation Grid Sidebar Panel */}
                        <div className="space-y-6">
                            
                            {/* Question Grid Map */}
                            <div className="bg-white border border-slate-200 rounded-xl p-5 dark:bg-slate-800 dark:border-slate-700">
                                <h3 className="text-sm font-bold text-slate-800 dark:text-slate-205 mb-4 uppercase tracking-wider flex items-center gap-1.5">
                                    <FontAwesomeIcon icon={faCheckDouble} className="text-slate-500" />
                                    Question Map
                                </h3>

                                <div className="max-h-[350px] overflow-y-auto pr-2 mb-6 scrollbar-thin">
                                    <div className="grid grid-cols-5 gap-2">
                                        {answers.map((answer, index) => {
                                            const isCurrent = index === currentIndex;
                                            const isAnswered = !!answer.selected_option_id;
                                            const isFlagged = answer.is_flagged;

                                            let btnClasses = "relative flex items-center justify-center h-10 w-full rounded-lg text-xs font-bold border transition ";
                                            
                                            if (isCurrent) {
                                                btnClasses += "border-slate-900 ring-2 ring-slate-800/20 dark:border-slate-100 dark:ring-slate-100/20 ";
                                            } else {
                                                btnClasses += "border-slate-200 dark:border-slate-700 ";
                                            }

                                            if (isAnswered) {
                                                btnClasses += "bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900 ";
                                            } else {
                                                btnClasses += "bg-transparent text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-700/50 ";
                                            }

                                            return (
                                                <button
                                                    key={answer.id}
                                                    onClick={() => setCurrentIndex(index)}
                                                    className={btnClasses}
                                                >
                                                    {index + 1}
                                                    {isFlagged && (
                                                        <span className="absolute top-0 right-0 w-2.5 h-2.5 bg-amber-500 rounded-full border border-white dark:border-slate-800" title="Flagged for review"></span>
                                                    )}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>

                                <div className="flex flex-col gap-2 pt-4 border-t border-slate-100 dark:border-slate-700 text-xxs font-semibold text-slate-500 dark:text-slate-400">
                                    <div className="flex items-center gap-2">
                                        <span className="w-3 h-3 bg-slate-800 border border-slate-800 rounded dark:bg-slate-200"></span>
                                        <span>Answered</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="w-3 h-3 bg-transparent border border-slate-200 rounded dark:border-slate-700"></span>
                                        <span>Unanswered</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="relative w-3 h-3 bg-transparent border border-slate-200 rounded dark:border-slate-700">
                                            <span className="absolute top-0 right-0 w-2 h-2 bg-amber-500 rounded-full"></span>
                                        </span>
                                        <span>Flagged for Review</span>
                                    </div>
                                </div>
                            </div>

                            {/* Submit Exam Block */}
                            <div className="bg-white border border-slate-200 rounded-xl p-5 dark:bg-slate-800 dark:border-slate-700 flex flex-col gap-3">
                                <button
                                    onClick={confirmSubmit}
                                    className="w-full inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 transition"
                                >
                                    Finish & Grade Exam
                                </button>
                                <Link
                                    href={route('dashboard')}
                                    className="w-full inline-flex items-center justify-center rounded-lg border border-slate-250 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-350 dark:hover:bg-slate-700 transition"
                                >
                                    <FontAwesomeIcon icon={faSignOutAlt} className="mr-1.5" />
                                    Exit (Save Progress)
                                </Link>
                            </div>

                        </div>

                    </div>

                </div>
            </div>

            {/* Confirmation Modal */}
            <ConfirmationModal
                isOpen={submitModalOpen}
                title="Submit Exam"
                message={submitMessage}
                confirmLabel="Submit & Grade"
                cancelLabel="Cancel"
                type="info"
                onConfirm={submitExam}
                onClose={() => setSubmitModalOpen(false)}
            />
        </AuthenticatedLayout>
    );
}
