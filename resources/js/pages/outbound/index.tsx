import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

interface OpenOrder {
    id: string;
    order_number: string;
    destination: string;
    order_date: string;
    items_sum_quantity_requested: number;
    items_sum_quantity_scanned: number;
    items: { id: string; quantity_requested: number; quantity_scanned: number; product: { display_name: string } }[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Barang Keluar', href: '/outbound' }];

export default function OutboundIndex({ orders }: { orders: OpenOrder[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Barang Keluar" />
            <div className="mx-auto grid w-full max-w-3xl grid-cols-1 gap-4 p-4 lg:max-w-6xl">
                <Heading
                    title="Barang Keluar"
                    description="Pilih order yang akan dikeluarkan. Hanya order yang sudah dibuka Admin yang tampil di sini."
                />

                {orders.length === 0 && (
                    <p className="text-muted-foreground rounded-lg border p-6 text-center">Tidak ada order yang perlu dikeluarkan.</p>
                )}

                <ul className="grid gap-3 lg:grid-cols-2">
                    {orders.map((order) => (
                        <li key={order.id}>
                            <Link href={route('outbound.show', order.id)} className="hover:bg-muted/50 flex items-center gap-4 rounded-xl border p-4">
                                <div className="min-w-0 flex-1">
                                    <div className="text-lg font-semibold">{order.destination}</div>
                                    <div className="text-muted-foreground text-sm">
                                        <span className="font-mono">{order.order_number}</span> · {formatDate(order.order_date)}
                                    </div>
                                    <div className="mt-1 text-sm">
                                        {order.items.map((item) => `${item.product.display_name} ${item.quantity_requested}`).join(' · ')}
                                    </div>
                                    {Number(order.items_sum_quantity_scanned) >= Number(order.items_sum_quantity_requested) && (
                                        <div className="mt-1 text-sm font-semibold text-green-700">Lengkap, menunggu pengecekan Admin</div>
                                    )}
                                </div>
                                <div className="text-right">
                                    <div className="text-2xl font-bold tabular-nums">
                                        {order.items_sum_quantity_scanned}/{order.items_sum_quantity_requested}
                                    </div>
                                    <div className="text-muted-foreground text-xs">dus</div>
                                </div>
                                <ChevronRight className="text-muted-foreground size-5" />
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </AppLayout>
    );
}
