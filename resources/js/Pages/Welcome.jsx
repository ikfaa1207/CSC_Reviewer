import { Head, Link } from '@inertiajs/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faGraduationCap,
    faSignInAlt,
    faUserPlus,
    faChartPie,
    faClipboardList,
    faLightbulb
} from '@fortawesome/free-solid-svg-icons';

export default function Welcome({ auth }) {
    return (
        <>
            <Head title="Welcome - Civil Service Exam Reviewer" />
            <div className="min-h-screen bg-slate-50 flex flex-col justify-between text-slate-800 dark:bg-slate-900 dark:text-slate-100 transition-colors">
                
                {/* Header Navbar */}
                <header className="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950/40">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <FontAwesomeIcon icon={faGraduationCap} className="text-slate-700 dark:text-slate-300 w-5 h-5" />
                            <span className="text-lg font-bold tracking-tight uppercase">CSE Reviewer</span>
                            <span className="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-xxs font-semibold text-slate-800 dark:bg-slate-800 dark:text-slate-200">
                                Self-Assessment
                            </span>
                        </div>
                        <nav className="flex gap-4">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="text-sm font-semibold text-slate-750 hover:text-slate-950 dark:text-slate-350 dark:hover:text-slate-100 flex items-center gap-1.5"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="text-sm font-semibold text-slate-750 hover:text-slate-950 dark:text-slate-350 dark:hover:text-slate-100 flex items-center gap-1.5"
                                    >
                                        <FontAwesomeIcon icon={faSignInAlt} className="w-3.5 h-3.5" />
                                        Log in
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="text-sm font-semibold text-slate-755 hover:text-slate-950 dark:text-slate-350 dark:hover:text-slate-100 flex items-center gap-1.5"
                                    >
                                        <FontAwesomeIcon icon={faUserPlus} className="w-3.5 h-3.5" />
                                        Register
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero Section */}
                <main className="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
                    <div className="max-w-2xl text-center space-y-6">
                        <h1 className="text-4xl sm:text-5xl font-extrabold tracking-tight text-slate-900 dark:text-white leading-none">
                            Philippine Civil Service Exam Reviewer
                        </h1>
                        <p className="text-base sm:text-lg text-slate-550 dark:text-slate-400 max-w-xl mx-auto leading-relaxed font-medium">
                            Evaluate your preparedness for both Professional and Sub-Professional levels. Track progress across subjects and review explanations on weak areas.
                        </p>

                        <div className="pt-4 flex flex-col sm:flex-row gap-3 justify-center">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="inline-flex items-center justify-center rounded-lg bg-slate-900 px-6 py-3.5 text-base font-semibold text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 transition"
                                >
                                    Enter Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('register')}
                                        className="inline-flex items-center justify-center rounded-lg bg-slate-900 px-6 py-3.5 text-base font-semibold text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 transition"
                                    >
                                        Create Free Account
                                    </Link>
                                    <Link
                                        href={route('login')}
                                        className="inline-flex items-center justify-center rounded-lg border border-slate-350 bg-white px-6 py-3.5 text-base font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none dark:border-slate-750 dark:bg-slate-805 dark:text-slate-300 dark:hover:bg-slate-700 transition"
                                    >
                                        Log In
                                    </Link>
                                </>
                            )}
                        </div>

                        {/* Features summary grid */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 pt-12 border-t border-slate-200 dark:border-slate-800 text-left">
                            <div className="p-4 bg-white border border-slate-200 rounded-xl dark:bg-slate-955/30 dark:border-slate-800">
                                <h3 className="font-bold text-sm text-slate-850 dark:text-slate-200 flex items-center gap-1.5">
                                    <FontAwesomeIcon icon={faChartPie} className="text-slate-600 dark:text-slate-400" />
                                    Category Analytics
                                </h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">Get precise feedback for Numerical, Verbal, Analytical, and General Information skills.</p>
                            </div>
                            <div className="p-4 bg-white border border-slate-200 rounded-xl dark:bg-slate-955/30 dark:border-slate-800">
                                <h3 className="font-bold text-sm text-slate-850 dark:text-slate-200 flex items-center gap-1.5">
                                    <FontAwesomeIcon icon={faClipboardList} className="text-slate-600 dark:text-slate-400" />
                                    Practice Mock Exams
                                </h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">Practice with a persistent exam grid, timers, and the ability to flag questions for review.</p>
                            </div>
                            <div className="p-4 bg-white border border-slate-200 rounded-xl dark:bg-slate-955/30 dark:border-slate-800">
                                <h3 className="font-bold text-sm text-slate-850 dark:text-slate-200 flex items-center gap-1.5">
                                    <FontAwesomeIcon icon={faLightbulb} className="text-slate-650 dark:text-slate-400" />
                                    Detailed Reviews
                                </h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">Understand the reasoning and logic behind the correct answers post-assessment.</p>
                            </div>
                        </div>
                    </div>
                </main>

                {/* Footer */}
                <footer className="border-t border-slate-200 dark:border-slate-800 py-6 text-center text-xs text-slate-500 dark:text-slate-500">
                    <p>© {new Date().getFullYear()} Civil Service Exam Reviewer and Self Assessment Tool. All rights reserved.</p>
                </footer>

            </div>
        </>
    );
}
