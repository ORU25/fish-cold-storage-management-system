import { AdjustmentDecision } from '@/components/adjustment-decision';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { ADJUSTMENT_STATUS_LABELS, ADJUSTMENT_TYPE_LABELS } from '@/lib/labels';
import { formatDateTime } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

interface AdjustmentRow {
    id: string;
    type: string;
    reason: string;
    photo_path: string | null;
    status: string;
    created_at: string;
    decided_at: string | null;
    decision_note: string | null;
    box: { id: string; qr_code: string; product: { display_name: string }; location: { name: string } | null };
    requested_by: { id: string; name: string };
    decided_by: { id: string; name: string } | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Adjustment', href: '/adjustments' }];

export default function AdjustmentsIndex({
    adjustments,
    filters,
    statuses,
}: {
    adjustments: Paginated<AdjustmentRow>;
    filters: { status: string };
    statuses: string[];
}) {
    const { auth } = usePage<SharedData>().props;
    const isOwner = auth.user.role === 'owner';
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Adjustment" />
            <div className="grid grid-cols-1 gap-4 p-4">
                <Heading
                    title="Adjustment"
                    description="Pengajuan dus hilang atau rusak. Diajukan dari detail dus, diputuskan Owner. Stok baru berkurang setelah di-approve."
                />

                <nav className="flex flex-wrap gap-2">
                    {statuses.map((status) => (
                        <Button key={status} asChild size="sm" variant={filters.status === status ? 'neutral' : 'outline'}>
                            <Link href={route('adjustments.index', { status })}>{ADJUSTMENT_STATUS_LABELS[status]}</Link>
                        </Button>
                    ))}
                </nav>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3">Diajukan</th>
                                <th className="p-3">Dus</th>
                                <th className="p-3">Jenis</th>
                                <th className="p-3">Alasan</th>
                                <th className="p-3">Keputusan</th>
                                {isOwner && filters.status === 'pending' && <th className="p-3" />}
                            </tr>
                        </thead>
                        <tbody>
                            {adjustments.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground p-6 text-center">
                                        Tidak ada pengajuan.
                                    </td>
                                </tr>
                            )}
                            {adjustments.data.map((adjustment) => (
                                <tr key={adjustment.id} className="border-t align-top">
                                    <td className="p-3 whitespace-nowrap">
                                        {formatDateTime(adjustment.created_at)}
                                        <div className="text-muted-foreground">{adjustment.requested_by.name}</div>
                                    </td>
                                    <td className="p-3">
                                        <Link
                                            href={route('boxes.show', adjustment.box.id)}
                                            className="text-primary font-mono text-xs underline-offset-4 hover:underline"
                                        >
                                            {adjustment.box.qr_code}
                                        </Link>
                                        <div>{adjustment.box.product.display_name}</div>
                                        <div className="text-muted-foreground">{adjustment.box.location?.name ?? '-'}</div>
                                    </td>
                                    <td className="p-3">{ADJUSTMENT_TYPE_LABELS[adjustment.type]}</td>
                                    <td className="p-3">
                                        {adjustment.reason}
                                        {adjustment.photo_path && (
                                            <div>
                                                <a
                                                    href={route('adjustments.photo', adjustment.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="underline"
                                                >
                                                    Lihat foto
                                                </a>
                                            </div>
                                        )}
                                    </td>
                                    <td className="p-3">
                                        {adjustment.decided_by ? (
                                            <>
                                                {adjustment.decided_by.name}, {formatDateTime(adjustment.decided_at)}
                                                {adjustment.decision_note && <div className="text-muted-foreground">{adjustment.decision_note}</div>}
                                            </>
                                        ) : (
                                            '-'
                                        )}
                                    </td>
                                    {isOwner && filters.status === 'pending' && (
                                        <td className="p-3">
                                            <AdjustmentDecision adjustment={adjustment} />
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination links={adjustments.links} />
            </div>
        </AppLayout>
    );
}
