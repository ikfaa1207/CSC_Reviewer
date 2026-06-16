import { useEffect } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faExclamationTriangle, faQuestionCircle, faTimes } from '@fortawesome/free-solid-svg-icons';

export default function ConfirmationModal({
    isOpen,
    title = 'Confirm Action',
    message = 'Are you sure you want to proceed?',
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    type = 'danger', // danger, info
    onConfirm,
    onClose
}) {
    // Escape key listener
    useEffect(() => {
        const handleKeyDown = (e) => {
            if (e.key === 'Escape' && isOpen) {
                onClose();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    const iconConfig = {
        danger: {
            icon: faExclamationTriangle,
            iconClass: 'text-red-650 bg-red-50 dark:bg-red-950/30 dark:text-red-410',
            btnClass: 'bg-red-600 hover:bg-red-700 focus:ring-red-500 text-white',
        },
        info: {
            icon: faQuestionCircle,
            iconClass: 'text-slate-800 bg-slate-50 dark:bg-slate-700 dark:text-slate-200',
            btnClass: 'bg-slate-900 hover:bg-slate-800 focus:ring-slate-500 text-white dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200',
        }
    };

    const config = iconConfig[type] || iconConfig.danger;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-x-hidden overflow-y-auto">
            {/* Backdrop */}
            <div 
                className="fixed inset-0 bg-slate-950/40 backdrop-blur-xs transition-opacity"
                onClick={onClose}
            ></div>

            {/* Modal Dialog Box */}
            <div className="relative w-full max-w-md bg-white border border-slate-200 rounded-xl shadow-xl transform transition-all dark:bg-slate-800 dark:border-slate-750 p-6">
                
                {/* Close Button */}
                <button
                    onClick={onClose}
                    className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition"
                    aria-label="Close modal"
                >
                    <FontAwesomeIcon icon={faTimes} className="w-3.5 h-3.5" />
                </button>

                <div className="flex items-start gap-4">
                    {/* Severity Icon */}
                    <div className={`flex items-center justify-center w-10 h-10 rounded-full shrink-0 ${config.iconClass}`}>
                        <FontAwesomeIcon icon={config.icon} className="w-5 h-5" />
                    </div>

                    <div className="flex-1">
                        <h3 className="text-base font-bold text-slate-900 dark:text-slate-100 leading-6">
                            {title}
                        </h3>
                        <p className="mt-2 text-sm text-slate-500 dark:text-slate-400 leading-normal">
                            {message}
                        </p>
                    </div>
                </div>

                {/* Footer Buttons */}
                <div className="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <button
                        type="button"
                        onClick={onClose}
                        className="inline-flex items-center justify-center rounded-lg border border-slate-250 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-350 dark:hover:bg-slate-700"
                    >
                        {cancelLabel}
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            onConfirm();
                            onClose();
                        }}
                        className={`inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-offset-2 ${config.btnClass}`}
                    >
                        {confirmLabel}
                    </button>
                </div>

            </div>
        </div>
    );
}
