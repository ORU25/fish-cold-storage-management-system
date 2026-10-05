import { type SharedData } from '@/types';
import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { Toaster, toast } from 'sonner';

/**
 * Shows the server's `success` flash as a toast: top right on desktop, full width at the top on phones.
 * Listens to Inertia's `success` event so a page restored from browser history does not toast again.
 */
export function FlashToaster() {
    useEffect(
        () =>
            router.on('success', (event) => {
                const message = (event.detail.page.props as unknown as SharedData).flash?.success;

                if (message) {
                    toast.success(message);
                }
            }),
        [],
    );

    return <Toaster position="top-right" richColors closeButton />;
}
