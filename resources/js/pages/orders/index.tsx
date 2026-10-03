import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { ORDER_STATUS_LABELS } from '@/lib/labels';
import { cn, formatDate } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface OrderRow {
    id: string;
    order_number: string;
    destination: string;
    order_date: string;
    status: string;
    items_sum_quantity_requested: number | null;
    items_sum_quantity_scanned: number | null;
    created_by: { id: string; name: string };
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Order Keluar', href: '/orders' }];

export default function OrdersIndex({
    orders,
    filters,
    statuses,
}: {
    orders: Paginated<OrderRow>;
    filters: { status?: string };
    statuses: string[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Order Keluar" />
            <div className="grid gap-4 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading title="Order Keluar" description="Dus hanya bisa keluar lewat order yang sudah dibuka (open)." />
                    <Button asChild>
                        <Link href={route('orders.create')}>Buat order</Link>
                    </Button>
                </div>

                <nav className="flex flex-wrap gap-2">
                    {['', ...statuses].map((status) => (
                        <Link
                            key={status}
                            href={route('orders.index', status ? { status } : {})}
                            className={cn(
                                'rounded-md border px-3 py-1.5 text-sm',
                                (filters.status ?? '') === status ? 'bg-primary text-primary-foreground' : 'hover:bg-muted',
                            )}
                        >
                            {status ? ORDER_STATUS_LABELS[status] : 'Semua'}
                        </Link>
                    ))}
                </nav>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3">Nomor</th>
                                <th className="p-3">Tanggal</th>
                                <th className="p-3">Tujuan</th>
                                <th className="p-3 text-right">Dus (keluar / diminta)</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Dibuat oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground p-6 text-center">
                                        Belum ada order.
                                    </td>
                                </tr>
                            )}
                            {orders.data.map((order) => (
                                <tr key={order.id} className="border-t">
                                    <td className="p-3 font-mono text-xs">
                                        <Link href={route('orders.show', order.id)} className="underline-offset-4 hover:underline">
                                            {order.order_number}
                                        </Link>
                                    </td>
                                    <td className="p-3">{formatDate(order.order_date)}</td>
                                    <td className="p-3">{order.destination}</td>
                                    <td className="p-3 text-right tabular-nums">
                                        {order.items_sum_quantity_scanned ?? 0} / {order.items_sum_quantity_requested ?? 0}
                                    </td>
                                    <td className="p-3">
                                        <Badge variant={order.status === 'open' ? 'default' : 'secondary'}>{ORDER_STATUS_LABELS[order.status]}</Badge>
                                        {order.status === 'open' &&
                                            Number(order.items_sum_quantity_scanned) >= Number(order.items_sum_quantity_requested) && (
                                                <div className="mt-1 text-xs font-semibold text-green-700">Menunggu pengecekan</div>
                                            )}
                                    </td>
                                    <td className="p-3">{order.created_by.name}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination links={orders.links} />
            </div>
        </AppLayout>
    );
}
