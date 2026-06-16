import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEnvelope, faLock, faUser, faUserPlus } from '@fortawesome/free-solid-svg-icons';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Register" />

            <div className="mb-6 text-center">
                <h2 className="text-xl font-bold text-slate-850 dark:text-slate-100">
                    Create Account
                </h2>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Get started with your civil service exam preparation
                </p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="name" value="Full Name" className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider" />

                    <div className="relative mt-1.5">
                        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                            <FontAwesomeIcon icon={faUser} className="text-slate-400 dark:text-slate-500 w-4 h-4" />
                        </div>
                        <TextInput
                            id="name"
                            name="name"
                            value={data.name}
                            className="block w-full pl-10 bg-slate-50 border-slate-200 focus:border-slate-500 focus:ring-slate-500 dark:bg-slate-900/50 dark:border-slate-700/60 dark:text-slate-100 rounded-xl py-2.5 text-sm transition"
                            autoComplete="name"
                            isFocused={true}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    </div>

                    <InputError message={errors.name} className="mt-2 text-xs" />
                </div>

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
                            autoComplete="new-password"
                            onChange={(e) => setData('password', e.target.value)}
                            required
                        />
                    </div>

                    <InputError message={errors.password} className="mt-2 text-xs" />
                </div>

                <div>
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Confirm Password"
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
                        {processing ? 'Registering...' : 'Register'}
                        <FontAwesomeIcon icon={faUserPlus} className="w-3.5 h-3.5" />
                    </button>
                </div>

                <div className="flex items-center justify-center text-xs pt-3 border-t border-slate-100 dark:border-slate-700/50 mt-4">
                    <span className="text-slate-500 dark:text-slate-400 mr-1">
                        Already have an account?
                    </span>
                    <Link
                        href={route('login')}
                        className="text-slate-900 hover:text-slate-800 dark:text-slate-100 dark:hover:text-slate-300 font-semibold underline transition"
                    >
                        Log in
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}

