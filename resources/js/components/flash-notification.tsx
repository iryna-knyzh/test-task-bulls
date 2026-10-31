import { usePage } from '@inertiajs/react';

const colorByType = {
    success: 'is-success',
    info: 'is-info',
    warning: 'is-warning',
    error: 'is-danger',
} as const;

export default function FlashNotification() {
    const toast = usePage().flash.toast;

    if (!toast) {
        return null;
    }

    return (
        <div className={`notification is-light ${colorByType[toast.type]}`}>
            {toast.message}
        </div>
    );
}
