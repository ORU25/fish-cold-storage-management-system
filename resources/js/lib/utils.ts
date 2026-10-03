import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/** "2026-10-03" or an ISO timestamp -> "03/10/2026" (PRD 6: DD/MM/YYYY, WIB). */
export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }
    const date = /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00+07:00`) : new Date(value);
    return date.toLocaleDateString('id-ID', { timeZone: 'Asia/Jakarta', day: '2-digit', month: '2-digit', year: 'numeric' });
}

/** ISO timestamp -> "03/10/2026 14.05" in WIB. */
export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }
    return new Date(value).toLocaleString('id-ID', {
        timeZone: 'Asia/Jakarta',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
