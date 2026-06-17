import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faClipboardList,
    faUserGraduate,
    faUser,
    faBook,
    faClock,
    faBookOpen,
    faPlay
} from '@fortawesome/free-solid-svg-icons';

export default function Prepare({ categories }) {
    const { data, setData, post, processing, errors } = useForm({
        level: 'professional',
        category_id: '',
        mode: 'timed',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('exams.start'));
    };

    // Automatically clear category if it's Analytical and level changes to Sub-Professional
    useEffect(() => {
        if (data.level === 'sub_professional') {
            const selectedCat = categories.find(c => c.id === parseInt(data.category_id));
            if (selectedCat && selectedCat.level === 'professional') {
                setData('category_id', '');
            }
        }
    }, [data.level]);

    return (
        <AuthenticatedLayout
            header={
                <h1 className="text-xl font-bold tracking-tight text-slate-800 dark:text-slate-101 flex items-center gap-2">
                    <FontAwesomeIcon icon={faClipboardList} className="text-slate-700 dark:text-slate-400" />
                    Prepare Practice Session
                </h1>
            }
        >
            <Head>
                <title>Prepare Civil Service Practice Exam | Zepo</title>
                <meta name="description" content="Configure and customize your Civil Service Exam (CSE) practice session. Select between Professional and Sub-Professional levels, choose a focus category, and set your timer mode." />
            </Head>

            <div className="py-8">
                <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white border border-slate-200 rounded-xl p-6 dark:bg-slate-800 dark:border-slate-700">
                        <div className="mb-6">
                            <h3 className="text-lg font-semibold text-slate-800 dark:text-slate-100">
                                Exam Parameters
                            </h3>
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Configure your Civil Service Exam (CSE) practice settings below to check your readiness.
                            </p>
                        </div>

                        {errors.error && (
                            <div className="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                {errors.error}
                            </div>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Exam Level */}
                            <div>
                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                    Exam Level
                                </label>
                                <div className="grid grid-cols-2 gap-4">
                                    <label className={`flex flex-col p-4 border rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition ${data.level === 'professional' ? 'border-slate-900 bg-slate-50 dark:border-slate-100 dark:bg-slate-700/40' : 'border-slate-200 dark:border-slate-700'}`}>
                                        <input
                                            type="radio"
                                            name="level"
                                            value="professional"
                                            checked={data.level === 'professional'}
                                            onChange={(e) => setData('level', e.target.value)}
                                            className="sr-only"
                                        />
                                        <span className="font-bold text-slate-805 dark:text-slate-100 flex items-center gap-1.5">
                                            <FontAwesomeIcon icon={faUserGraduate} className="text-slate-700 dark:text-slate-305" />
                                            Professional
                                        </span>
                                        <span className="text-xs text-slate-505 dark:text-slate-400 mt-1 leading-normal">
                                            Includes Numerical, Verbal, General Information, & Analytical Ability.
                                        </span>
                                    </label>

                                    <label className={`flex flex-col p-4 border rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition ${data.level === 'sub_professional' ? 'border-slate-900 bg-slate-50 dark:border-slate-105 dark:bg-slate-700/40' : 'border-slate-200 dark:border-slate-700'}`}>
                                        <input
                                            type="radio"
                                            name="level"
                                            value="sub_professional"
                                            checked={data.level === 'sub_professional'}
                                            onChange={(e) => setData('level', e.target.value)}
                                            className="sr-only"
                                        />
                                        <span className="font-bold text-slate-805 dark:text-slate-100 flex items-center gap-1.5">
                                            <FontAwesomeIcon icon={faUser} className="text-slate-700 dark:text-slate-305" />
                                            Sub-Professional
                                        </span>
                                        <span className="text-xs text-slate-505 dark:text-slate-400 mt-1 leading-normal">
                                            Includes Numerical, Verbal, & General Information. (No Analytical logic).
                                        </span>
                                    </label>
                                </div>
                                {errors.level && <p className="text-sm text-red-600 mt-1">{errors.level}</p>}
                            </div>

                            {/* Subject Category */}
                            <div>
                                <label htmlFor="category_id" className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2 flex items-center gap-1.5">
                                    <FontAwesomeIcon icon={faBook} className="text-slate-500" />
                                    Subject / Focus Category
                                </label>
                                <select
                                    id="category_id"
                                    value={data.category_id}
                                    onChange={(e) => setData('category_id', e.target.value)}
                                    className="block w-full rounded-lg border-slate-200 bg-white px-4 py-2.5 text-slate-800 shadow-sm focus:border-slate-500 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                >
                                    <option value="">Full Mock Exam - Test Proper (150 Questions)</option>
                                    {categories
                                        .filter(c => c.level === 'both' || c.level === data.level)
                                        .map((category) => (
                                            <option key={category.id} value={category.id}>
                                                {category.name}
                                            </option>
                                        ))}
                                </select>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1.5">
                                    Leave as "Full Mock Exam" to get a balanced set of random questions from all matching categories.
                                </p>
                                {errors.category_id && <p className="text-sm text-red-600 mt-1">{errors.category_id}</p>}
                            </div>

                            {/* Session Mode */}
                            <div>
                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                    Review Mode
                                </label>
                                <div className="grid grid-cols-2 gap-4">
                                    <label className={`flex flex-col p-4 border rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition ${data.mode === 'timed' ? 'border-slate-900 bg-slate-50 dark:border-slate-105 dark:bg-slate-700/40' : 'border-slate-200 dark:border-slate-700'}`}>
                                        <input
                                            type="radio"
                                            name="mode"
                                            value="timed"
                                            checked={data.mode === 'timed'}
                                            onChange={(e) => setData('mode', e.target.value)}
                                            className="sr-only"
                                        />
                                        <span className="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                                            <FontAwesomeIcon icon={faClock} className="text-slate-705 dark:text-slate-300" />
                                            Timed Exam
                                        </span>
                                        <span className="text-xs text-slate-505 dark:text-slate-400 mt-1 leading-normal">
                                            Mimic the real exam experience with a countdown timer to check time-readiness.
                                        </span>
                                    </label>

                                    <label className={`flex flex-col p-4 border rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition ${data.mode === 'review' ? 'border-slate-900 bg-slate-50 dark:border-slate-105 dark:bg-slate-700/40' : 'border-slate-200 dark:border-slate-700'}`}>
                                        <input
                                            type="radio"
                                            name="mode"
                                            value="review"
                                            checked={data.mode === 'review'}
                                            onChange={(e) => setData('mode', e.target.value)}
                                            className="sr-only"
                                        />
                                        <span className="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                                            <FontAwesomeIcon icon={faBookOpen} className="text-slate-705 dark:text-slate-300" />
                                            Self-Guided Review
                                        </span>
                                        <span className="text-xs text-slate-505 dark:text-slate-400 mt-1 leading-normal">
                                            No timer constraints. Perfect for relaxed study and category focus.
                                        </span>
                                    </label>
                                </div>
                                {errors.mode && <p className="text-sm text-red-600 mt-1">{errors.mode}</p>}
                            </div>

                            {/* Submit Button */}
                            <div className="pt-4 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full sm:w-auto inline-flex items-center justify-center rounded-lg bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 transition"
                                >
                                    <FontAwesomeIcon icon={faPlay} className="mr-2 w-3 h-3" />
                                    {processing ? 'Loading Questions...' : 'Start Assessment'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
