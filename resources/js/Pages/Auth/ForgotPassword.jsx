import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEnvelope, faPaperPlane, faArrowLeft } from '@fortawesome/free-solid-svg-icons';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <div className="mb-6 text-center">
                <h2 className="text-xl font-bold text-slate-850 dark:text-slate-100">
                    Forgot Password
                </h2>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed max-w-sm mx-auto">
                    No problem. Enter your registered email address and we will send you a secure link to reset your password.
                </p>
            </div>

            {status && (
                <div className="mb-4 text-sm font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 p-3 rounded-xl border border-emerald-200 dark:border-emerald-800/40">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="email" value="Email Address" className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider" />

                    <div className="relative mt-1.5">
                        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                            <FontAwesomeIcon icon={faEnvelope} className="text-slate-400 dark:text-slate-500 w-4 h-4" />
                        </div>
                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="block w-full pl-10 bg-slate-50 border-slate-200 focus:border-slate-500 focus:ring-slate-500 dark:bg-slate-900/50 dark:border-slate-700/60 dark:text-slate-100 rounded-xl py-2.5 text-sm transition"
                            isFocused={true}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                    </div>

                    <InputError message={errors.email} className="mt-2 text-xs" />
                </div>

                <div className="pt-2">
                    <button
                        type="submit"
                        className="w-full flex items-center justify-center gap-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold py-3 text-sm transition-all duration-150 shadow-sm hover:shadow dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 cursor-pointer disabled:opacity-50"
                        disabled={processing}
                    >
                        {processing ? 'Sending Link...' : 'Email Reset Link'}
                        <FontAwesomeIcon icon={faPaperPlane} className="w-3.5 h-3.5" />
                    </button>
                </div>

                <div className="flex items-center justify-center text-xs pt-3 border-t border-slate-100 dark:border-slate-700/50 mt-4">
                    <Link
                        href={route('login')}
                        className="text-slate-500 hover:text-slate-850 dark:text-slate-400 dark:hover:text-slate-200 transition font-semibold flex items-center gap-1.5"
                    >
                        <FontAwesomeIcon icon={faArrowLeft} className="w-3 h-3" />
                        Back to Log in
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}

