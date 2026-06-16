import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEnvelopeOpenText, faPaperPlane, faSignOutAlt } from '@fortawesome/free-solid-svg-icons';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Email Verification" />

            <div className="mb-6 text-center">
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-705/30 dark:text-slate-300 mx-auto mb-4">
                    <FontAwesomeIcon icon={faEnvelopeOpenText} className="w-5 h-5" />
                </div>
                <h2 className="text-xl font-bold text-slate-850 dark:text-slate-100">
                    Verify Your Email
                </h2>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed max-w-sm mx-auto">
                    Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive it, we will gladly send another.
                </p>
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 p-3 rounded-xl border border-emerald-200 dark:border-emerald-800/40">
                    A new verification link has been sent to the email address you provided during registration.
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <div className="pt-2">
                    <button
                        type="submit"
                        className="w-full flex items-center justify-center gap-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold py-3 text-sm transition-all duration-150 shadow-sm hover:shadow dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 cursor-pointer disabled:opacity-50"
                        disabled={processing}
                    >
                        {processing ? 'Sending...' : 'Resend Verification Email'}
                        <FontAwesomeIcon icon={faPaperPlane} className="w-3.5 h-3.5" />
                    </button>
                </div>

                <div className="flex items-center justify-center text-xs pt-3 border-t border-slate-100 dark:border-slate-700/50 mt-4">
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-slate-550 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 transition font-semibold flex items-center gap-1.5 cursor-pointer underline"
                    >
                        <FontAwesomeIcon icon={faSignOutAlt} className="w-3 h-3" />
                        Log Out
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}

