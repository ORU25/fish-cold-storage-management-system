import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { ORDER_STATUS_LABELS } from '@/lib/labels';
import { formatDate, formatDateTime } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type OrderAction = 'orders.open' | 'orders.cancel' | 'orders.complete';

interface Order {
    id: string;
    order_number: string;
    destination: string;
    order_date: string;
    notes: string | null;
    status: string;
    close_reason: string | null;
    created_by: { id: string; name: string };
    items: { id: string; product_id: string; quantity_requested: number; quantity_scanned: number; product: { display_name: string } }[];
}

interface Scan {
    id: string;
    created_at: string;
    fefo_violation: boolean;
    fefo_reason: string | null;
    scanned_by: { name: string };
    box: { qr_code: string; expired_date: string; product: { display_name: string }; location: { name: string } | null };
}

export default function OrderShow({ order, available, scans }: { order: Order; available: Record<string, number>; scans: Scan[] }) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [closing, setClosing] = useState(false);
    const isFullyScanned = order.items.every((item) => item.quantity_scanned >= item.quantity_requested);
    const closeForm = useForm({ close_reason: '' });
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Order Keluar', href: '/orders' },
        { title: order.order_number, href: route('orders.show', order.id) },
    ];

    // Which status action is running, so its button shows a spinner and the others stay locked until the page reloads.
    const [pending, setPending] = useState<OrderAction | null>(null);
    const busy = pending !== null || closeForm.processing;

    const post = (name: OrderAction, question: string) => {
        if (confirm(question)) {
            router.post(route(name, order.id), {}, { preserveScroll: true, onStart: () => setPending(name), onFinish: () => setPending(null) });
        }
    };

    const label = (name: OrderAction, text: string) =>
        pending === name ? (
            <>
                <LoaderCircle className="size-4 animate-spin" /> Memproses…
            </>
        ) : (
            text
        );

    const close: FormEventHandler = (e) => {
        e.preventDefault();
        closeForm.post(route('orders.close', order.id), { preserveScroll: true, onSuccess: () => setClosing(false) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={order.order_number} />
            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="font-mono text-xl font-semibold">{order.order_number}</h1>
                            <Badge variant={order.status === 'open' ? 'default' : 'secondary'}>{ORDER_STATUS_LABELS[order.status]}</Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {order.destination} · {formatDate(order.order_date)} · dibuat oleh {order.created_by.name}
                        </p>
                        {order.notes && <p className="mt-1 text-sm">{order.notes}</p>}
                        {order.close_reason && <p className="mt-1 text-sm">Alasan ditutup: {order.close_reason}</p>}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {order.status === 'draft' && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={route('orders.edit', order.id)}>Ubah</Link>
                                </Button>
                                <Button variant="outline" disabled={busy} onClick={() => post('orders.cancel', 'Batalkan draft order ini?')}>
                                    {label('orders.cancel', 'Batalkan')}
                                </Button>
                                <Button
                                    disabled={busy}
                                    onClick={() => post('orders.open', 'Buka order? Stok akan dipesan dan order muncul di layar staf.')}
                                >
                                    {label('orders.open', 'Buka order')}
                                </Button>
                            </>
                        )}
                        {order.status === 'open' && isFullyScanned && (
                            <Button
                                disabled={busy}
                                onClick={() => post('orders.complete', 'Barang sudah dicek fisik dan sesuai? Order akan diselesaikan.')}
                            >
                                {label('orders.complete', 'Selesaikan order')}
                            </Button>
                        )}
                        {order.status === 'open' && (
                            <Button variant="destructive" disabled={busy} onClick={() => setClosing(true)}>
                                Tutup order
                            </Button>
                        )}
                    </div>
                </div>
                {order.status === 'open' && isFullyScanned && (
                    <div className="rounded-lg border-2 border-green-600 bg-green-50 p-4 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">
                        Semua item sudah discan. Cek fisik barang yang akan dikirim, lalu tekan <strong>Selesaikan order</strong>. Jika ada yang
                        salah, order masih open sehingga scan bisa dikoreksi.
                    </div>
                )}
                <InputError message={errors.order} />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3">Produk</th>
                                <th className="p-3 text-right">Diminta</th>
                                <th className="p-3 text-right">Keluar</th>
                                <th className="p-3 text-right">Sisa</th>
                                {order.status === 'draft' && <th className="p-3 text-right">Stok tersedia</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {order.items.map((item) => (
                                <tr key={item.id} className="border-t">
                                    <td className="p-3">{item.product.display_name}</td>
                                    <td className="p-3 text-right tabular-nums">{item.quantity_requested}</td>
                                    <td className="p-3 text-right tabular-nums">{item.quantity_scanned}</td>
                                    <td className="p-3 text-right tabular-nums">{item.quantity_requested - item.quantity_scanned}</td>
                                    {order.status === 'draft' && (
                                        <td
                                            className={`p-3 text-right tabular-nums ${(available[item.product_id] ?? 0) < item.quantity_requested ? 'font-semibold text-red-600' : ''}`}
                                        >
                                            {available[item.product_id] ?? 0}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <section>
                    <h2 className="mb-3 font-semibold">Dus keluar ({scans.length})</h2>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3">Waktu</th>
                                    <th className="p-3">Kode</th>
                                    <th className="p-3">Produk</th>
                                    <th className="p-3">Expired</th>
                                    <th className="p-3">Staf</th>
                                    <th className="p-3">FEFO</th>
                                </tr>
                            </thead>
                            <tbody>
                                {scans.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-muted-foreground p-6 text-center">
                                            Belum ada dus keluar.
                                        </td>
                                    </tr>
                                )}
                                {scans.map((scan) => (
                                    <tr key={scan.id} className="border-t align-top">
                                        <td className="p-3 whitespace-nowrap">{formatDateTime(scan.created_at)}</td>
                                        <td className="p-3 font-mono text-xs">{scan.box.qr_code}</td>
                                        <td className="p-3">{scan.box.product.display_name}</td>
                                        <td className="p-3">{formatDate(scan.box.expired_date)}</td>
                                        <td className="p-3">{scan.scanned_by.name}</td>
                                        <td className="p-3">
                                            {scan.fefo_violation ? (
                                                <div>
                                                    <Badge variant="destructive">Melanggar</Badge>
                                                    <div className="mt-1 text-xs">{scan.fefo_reason}</div>
                                                </div>
                                            ) : (
                                                '-'
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <Dialog open={closing} onOpenChange={setClosing}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tutup order {order.order_number}?</DialogTitle>
                        <DialogDescription>Sisa item tidak akan dikeluarkan dan stok yang dipesan dilepas.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={close} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="close_reason">Alasan</Label>
                            <Input
                                id="close_reason"
                                value={closeForm.data.close_reason}
                                onChange={(e) => closeForm.setData('close_reason', e.target.value)}
                                required
                            />
                            <InputError message={closeForm.errors.close_reason} />
                        </div>
                        <DialogFooter>
                            <Button type="submit" variant="destructive" disabled={closeForm.processing}>
                                {closeForm.processing ? (
                                    <>
                                        <LoaderCircle className="size-4 animate-spin" /> Memproses…
                                    </>
                                ) : (
                                    'Tutup order'
                                )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
