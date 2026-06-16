import { useEffect, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheckCircle, faExclamationCircle, faTimes, faInfoCircle } from '@fortawesome/free-solid-svg-icons';

export default function Toast({ message, type = 'success', onClose }) {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (message) {
            setVisible(true);
            const timer = setTimeout(() => {
                handleClose();
            }, 4000); // Auto close after 4s

            return () => clearTimeout(timer);
        }
    }, [message]);

    const handleClose = () => {
        setVisible(false);
        setTimeout(() => {
            if (onClose) onClose();
        }, 300); // Wait for fade out animation
    };

    if (!message) return null;

    const typeConfig = {
        success: {
            bg: 'bg-white dark:bg-slate-800 border-slate-900 dark:border-slate-100',
            text: 'text-slate-800 dark:text-slate-100',
            icon: faCheckCircle,
            iconColor: 'text-slate-900 dark:text-slate-100',
        },
        error: {
            bg: 'bg-white dark:bg-slate-800 border-red-500 dark:border-red-500',
            text: 'text-red-800 dark:text-red-300',
            icon: faExclamationCircle,
            iconColor: 'text-red-500',
        },
        info: {
            bg: 'bg-white dark:bg-slate-800 border-slate-500',
            text: 'text-slate-800 dark:text-slate-200',
            icon: faInfoCircle,
            iconColor: 'text-slate-500',
        }
    };

    const config = typeConfig[type] || typeConfig.success;

    return (
        <div
            className={`fixed bottom-5 right-5 z-50 flex items-center w-full max-w-sm p-4 border rounded-xl shadow-lg transition-all duration-300 transform ${visible ? 'opacity-100 translate-y-0 scale-100' : 'opacity-0 translate-y-2 scale-95 pointer-events-none'} ${config.bg}`}
        >
            <div className={`inline-flex items-center justify-center shrink-0 w-8 h-8 rounded-lg ${config.iconColor}`}>
                <FontAwesomeIcon icon={config.icon} className="w-5 h-5" />
            </div>
            
            <div className={`ms-3 text-sm font-semibold pr-4 leading-normal ${config.text}`}>
                {message}
            </div>

            <button
                type="button"
                onClick={handleClose}
                className="ms-auto -mx-1.5 -my-1.5 bg-transparent text-slate-400 hover:text-slate-900 rounded-lg p-1.5 inline-flex items-center justify-center h-8 w-8 dark:text-slate-500 dark:hover:text-white"
                aria-label="Close"
            >
                <FontAwesomeIcon icon={faTimes} className="w-3 h-3" />
            </button>
        </div>
    );
}
