import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEnvelope, faLock, faRedo } from '@fortawesome/free-solid-svg-icons';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Reset Password" />

            <div className="mb-6 text-center">
                <h2 className="text-xl font-bold text-slate-850 dark:text-slate-100">
                    Reset Password
                </h2>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Set a new secure password for your account
                </p>
            </div>

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
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                    </div>

                    <InputError message={errors.email} className="mt-2 text-xs" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="New Password" className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider" />

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
                            autoComplete="new-password"
                            isFocused={true}
                            onChange={(e) => setData('password', e.target.value)}
                            required
                        />
                    </div>

                    <InputError message={errors.password} className="mt-2 text-xs" />
                </div>

                <div>
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Confirm New Password"
                        className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider"
                    />

                    <div className="relative mt-1.5">
                        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                            <FontAwesomeIcon icon={faLock} className="text-slate-400 dark:text-slate-500 w-4 h-4" />
                        </div>
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            className="block w-full pl-10 bg-slate-50 border-slate-200 focus:border-slate-500 focus:ring-slate-500 dark:bg-slate-900/50 dark:border-slate-700/60 dark:text-slate-100 rounded-xl py-2.5 text-sm transition"
                            autoComplete="new-password"
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                        />
                    </div>

                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2 text-xs"
                    />
                </div>

                <div className="pt-2">
                    <button
                        type="submit"
                        className="w-full flex items-center justify-center gap-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold py-3 text-sm transition-all duration-150 shadow-sm hover:shadow dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 cursor-pointer disabled:opacity-50"
                        disabled={processing}
                    >
                        {processing ? 'Resetting Password...' : 'Reset Password'}
                        <FontAwesomeIcon icon={faRedo} className="w-3.5 h-3.5" />
                    </button>
                </div>
            </form>
        </GuestLayout>
    );
}

