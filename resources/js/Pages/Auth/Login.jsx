import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEnvelope, faLock, faSignInAlt } from '@fortawesome/free-solid-svg-icons';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <div className="mb-6 text-center">
                <h2 className="text-xl font-bold text-slate-850 dark:text-slate-100">
                    Welcome Back
                </h2>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Sign in to continue your self-assessment
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
                            autoComplete="username"
                            isFocused={true}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                    </div>

                    <InputError message={errors.email} className="mt-2 text-xs" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Password" className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider" />

                    <div className="relative mt-1.5">
                        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                            <FontAwesomeIcon icon={faLock} className="text-slate-400 dark:text-slate-500 w-4 h-4" />
                        </div>
                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="block w-full pl-10 bg-slate-50 border-slate-200 focus:border-slate-500 focus:ring-slate-500 dark:bg-slate-900/50 dark:border-slate-700/60 dark:text-slate-100 rounded-xl py-2.5 text-sm transition"
                            autoComplete="current-password"
                            onChange={(e) => setData('password', e.target.value)}
                            required
                        />
                    </div>

                    <InputError message={errors.password} className="mt-2 text-xs" />
                </div>

                <div className="flex items-center justify-between pt-1">
                    <label className="flex items-center cursor-pointer select-none">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="rounded border-slate-300 text-slate-900 focus:ring-slate-550 dark:border-slate-700 dark:bg-slate-900 dark:focus:ring-offset-slate-800"
                        />
                        <span className="ms-2 text-sm text-slate-650 dark:text-slate-400 font-medium">
                            Remember me
                        </span>
                    </label>
                </div>

                <div className="pt-2">
                    <button
                        type="submit"
                        className="w-full flex items-center justify-center gap-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold py-3 text-sm transition-all duration-150 shadow-sm hover:shadow dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 cursor-pointer disabled:opacity-50"
                        disabled={processing}
                    >
                        {processing ? 'Logging in...' : 'Log in'}
                        <FontAwesomeIcon icon={faSignInAlt} className="w-3.5 h-3.5" />
                    </button>
                </div>

                <div className="flex items-center justify-between text-xs pt-3 border-t border-slate-100 dark:border-slate-700/50 mt-4">
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-slate-500 hover:text-slate-850 dark:text-slate-400 dark:hover:text-slate-200 transition underline decoration-dotted"
                        >
                            Forgot your password?
                        </Link>
                    )}

                    <Link
                        href={route('register')}
                        className="text-slate-500 hover:text-slate-850 dark:text-slate-400 dark:hover:text-slate-200 transition font-semibold underline"
                    >
                        Create account
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}

