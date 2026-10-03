import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface ActivityLogRow {
    id: number;
    action: string;
    subject_type: string | null;
    subject_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    reason: string | null;
    ip_address: string | null;
    created_at: string;
    user: { id: number; name: string; username: string } | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Filters {
    user_id?: string;
    action?: string;
    date_from?: string;
    date_to?: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Log Aktivitas', href: '/activity-logs' }];

const selectClass = 'border-input bg-background h-9 rounded-md border px-3 text-sm';

const formatDateTime = (value: string) =>
    new Date(value).toLocaleString('id-ID', {
        timeZone: 'Asia/Jakarta',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });

const formatValues = (values: Record<string, unknown> | null) =>
    values
        ? Object.entries(values)
              .map(([key, value]) => `${key}: ${JSON.stringify(value)}`)
              .join(', ')
        : '';

export default function ActivityLogsIndex({
    logs,
    filters,
    users,
    actions,
}: {
    logs: Paginated<ActivityLogRow>;
    filters: Filters;
    users: { id: number; name: string }[];
    actions: string[];
}) {
    const submit: FormEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();
        const data = Object.fromEntries([...new FormData(e.currentTarget)].filter(([, value]) => value !== ''));
        router.get(route('activity-logs.index'), data, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Log Aktivitas" />
            <div className="p-4">
                <Heading title="Log Aktivitas" description="Catatan seluruh kejadian. Log tidak bisa diubah atau dihapus." />

                <form onSubmit={submit} className="mb-4 flex flex-wrap items-end gap-2">
                    <select name="user_id" defaultValue={filters.user_id ?? ''} className={selectClass} aria-label="Pengguna">
                        <option value="">Semua pengguna</option>
                        {users.map((user) => (
                            <option key={user.id} value={user.id}>
                                {user.name}
                            </option>
                        ))}
                    </select>
                    <select name="action" defaultValue={filters.action ?? ''} className={selectClass} aria-label="Aksi">
                        <option value="">Semua aksi</option>
                        {actions.map((action) => (
                            <option key={action} value={action}>
                                {action}
                            </option>
                        ))}
                    </select>
                    <Input type="date" name="date_from" defaultValue={filters.date_from ?? ''} className="w-auto" aria-label="Dari tanggal" />
                    <Input type="date" name="date_to" defaultValue={filters.date_to ?? ''} className="w-auto" aria-label="Sampai tanggal" />
                    <Button type="submit">Filter</Button>
                    <Button variant="outline" asChild>
                        <Link href={route('activity-logs.index')}>Reset</Link>
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 whitespace-nowrap">Waktu (WIB)</th>
                                <th className="p-3">Pengguna</th>
                                <th className="p-3">Aksi</th>
                                <th className="p-3">Objek</th>
                                <th className="p-3">Perubahan</th>
                                <th className="p-3">Alasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground p-6 text-center">
                                        Tidak ada log.
                                    </td>
                                </tr>
                            )}
                            {logs.data.map((log) => (
                                <tr key={log.id} className="border-t align-top">
                                    <td className="p-3 whitespace-nowrap">{formatDateTime(log.created_at)}</td>
                                    <td className="p-3">{log.user?.name ?? '-'}</td>
                                    <td className="p-3 font-mono text-xs">{log.action}</td>
                                    <td className="p-3 text-xs">
                                        {log.subject_type ? `${log.subject_type.split('\\').pop()} #${log.subject_id}` : '-'}
                                    </td>
                                    <td className="p-3 text-xs">
                                        {log.old_values && <div className="text-muted-foreground line-through">{formatValues(log.old_values)}</div>}
                                        {log.new_values && <div>{formatValues(log.new_values)}</div>}
                                    </td>
                                    <td className="p-3 text-xs">{log.reason ?? ''}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="mt-4 flex flex-wrap gap-1">
                    {logs.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                className={cn('rounded-md border px-3 py-1 text-sm', link.active && 'bg-primary text-primary-foreground')}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span key={index} className="text-muted-foreground px-3 py-1 text-sm" dangerouslySetInnerHTML={{ __html: link.label }} />
                        ),
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
