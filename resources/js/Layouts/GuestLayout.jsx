import { Link } from '@inertiajs/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faGraduationCap } from '@fortawesome/free-solid-svg-icons';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 py-8 dark:bg-slate-900 transition-colors duration-150">
            <div className="w-full sm:max-w-md">
                <div className="flex justify-center mb-6">
                    <Link href="/" className="group flex flex-col items-center gap-2 text-center">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 shadow-md transition-all duration-200 group-hover:scale-105 group-hover:shadow-lg">
                            <FontAwesomeIcon icon={faGraduationCap} className="w-6 h-6" />
                        </div>
                        <span className="text-xl font-extrabold tracking-tight uppercase text-slate-900 dark:text-white mt-1">
                            CSE Reviewer
                        </span>
                        <span className="text-xs font-semibold text-slate-500 dark:text-slate-400">
                            Self-Assessment & Exam Preparation
                        </span>
                    </Link>
                </div>

                <div className="overflow-hidden bg-white px-8 py-8 border border-slate-200 rounded-2xl shadow-xl dark:bg-slate-800 dark:border-slate-700/60">
                    {children}
                </div>
            </div>
        </div>
    );
}

