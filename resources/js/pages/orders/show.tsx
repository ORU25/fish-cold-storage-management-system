import { CancelScanButton } from '@/components/cancel-scan-button';
import { ConfirmDialog, type Confirmation } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { ORDER_STATUS_BADGE, ORDER_STATUS_LABELS } from '@/lib/labels';
import { formatDate, formatDateTime } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, LoaderCircle, Pencil, Send, XCircle } from 'lucide-react';
import { FormEventHandler, useState, type ReactNode } from 'react';

type OrderAction = 'orders.open' | 'orders.complete';

interface Order {
    id: string;
    order_number: string;
    destination: string;
    order_date: string;
    notes: string | null;
    status: string;
    cancel_reason: string | null;
    created_by: { id: string; name: string };
    items: { id: string; product_id: string; quantity_requested: number; quantity_scanned: number; product: { display_name: string } }[];
}

interface Scan {
    id: string;
    created_at: string;
    fefo_violation: boolean;
    fefo_reason: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    cancelled_by: { name: string } | null;
    scanned_by: { name: string };
    box: { qr_code: string; expired_date: string; product: { display_name: string }; location: { name: string } | null };
}

export default function OrderShow({ order, available, scans }: { order: Order; available: Record<string, number>; scans: Scan[] }) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [confirmation, setConfirmation] = useState<Confirmation | null>(null);
    const [cancelling, setCancelling] = useState(false);
    const isFullyScanned = order.items.every((item) => item.quantity_scanned >= item.quantity_requested);
    const cancelForm = useForm({ cancel_reason: '' });
    const isOpen = order.status === 'open';
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Order Keluar', href: '/orders' },
        { title: order.order_number, href: route('orders.show', order.id) },
    ];

    // Which status action is running, so its button shows a spinner and the others stay locked until the page reloads.
    const [pending, setPending] = useState<OrderAction | null>(null);
    const busy = pending !== null || cancelForm.processing;

    const post = (name: OrderAction, confirmation: Omit<Confirmation, 'onConfirm'>) =>
        setConfirmation({
            ...confirmation,
            onConfirm: () =>
                router.post(route(name, order.id), {}, { preserveScroll: true, onStart: () => setPending(name), onFinish: () => setPending(null) }),
        });

    const label = (name: OrderAction, text: string, icon: ReactNode) =>
        pending === name ? (
            <>
                <LoaderCircle className="size-4 animate-spin" /> Memproses…
            </>
        ) : (
            <>
                {icon} {text}
            </>
        );

    const cancel: FormEventHandler = (e) => {
        e.preventDefault();
        cancelForm.post(route('orders.cancel', order.id), { preserveScroll: true, onSuccess: () => setCancelling(false) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={order.order_number} />
            <div className="mx-auto grid w-full max-w-4xl grid-cols-1 gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="font-mono text-xl font-semibold">{order.order_number}</h1>
                            <Badge variant={ORDER_STATUS_BADGE[order.status]}>{ORDER_STATUS_LABELS[order.status]}</Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {order.destination} · {formatDate(order.order_date)} · dibuat oleh {order.created_by.name}
                        </p>
                        {order.notes && <p className="mt-1 text-sm">{order.notes}</p>}
                        {order.cancel_reason && <p className="mt-1 text-sm">Alasan dibatalkan: {order.cancel_reason}</p>}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {order.status === 'draft' && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={route('orders.edit', order.id)}>
                                        <Pencil /> Ubah
                                    </Link>
                                </Button>
                                <Button
                                    disabled={busy}
                                    onClick={() =>
                                        post('orders.open', {
                                            title: 'Buka order?',
                                            description: 'Stok akan dipesan dan order muncul di layar staf.',
                                            confirmLabel: 'Buka order',
                                        })
                                    }
                                >
                                    {label('orders.open', 'Buka order', <Send />)}
                                </Button>
                            </>
                        )}
                        {order.status === 'open' && isFullyScanned && (
                            <Button
                                variant="success"
                                disabled={busy}
                                onClick={() =>
                                    post('orders.complete', {
                                        title: 'Selesaikan order?',
                                        description: 'Pastikan barang sudah dicek fisik dan sesuai.',
                                        confirmLabel: 'Selesaikan order',
                                        variant: 'success',
                                    })
                                }
                            >
                                {label('orders.complete', 'Selesaikan order', <CheckCircle2 />)}
                            </Button>
                        )}
                        {(order.status === 'draft' || isOpen) && (
                            <Button variant="destructive" disabled={busy} onClick={() => setCancelling(true)}>
                                <XCircle /> Batalkan order
                            </Button>
                        )}
                    </div>
                </div>
                {order.status === 'open' && isFullyScanned && (
                    <div className="rounded-lg border-2 border-green-600 bg-green-50 p-4 text-sm text-green-800">
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
                    <h2 className="mb-3 font-semibold">Riwayat scan keluar ({scans.filter((scan) => !scan.cancelled_at).length} dus keluar)</h2>
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
                                    <th className="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {scans.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="text-muted-foreground p-6 text-center">
                                            Belum ada dus keluar.
                                        </td>
                                    </tr>
                                )}
                                {scans.map((scan) => (
                                    <tr key={scan.id} className={`border-t align-top ${scan.cancelled_at ? 'text-muted-foreground' : ''}`}>
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
                                        <td className="p-3">
                                            {scan.cancelled_at ? (
                                                <div>
                                                    <Badge variant="neutral">Dibatalkan</Badge>
                                                    <div className="mt-1 text-xs">
                                                        {scan.cancelled_by?.name}: {scan.cancel_reason ?? '-'}
                                                    </div>
                                                </div>
                                            ) : isOpen ? (
                                                <CancelScanButton
                                                    url={route('outbound-scans.cancel', scan.id)}
                                                    title={`Batalkan scan keluar ${scan.box.qr_code}?`}
                                                    description="Dus kembali ke gudang dan item order kembali butuh satu dus. Riwayat scan dan alasannya tetap tercatat."
                                                />
                                            ) : (
                                                'Keluar'
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <Dialog open={cancelling} onOpenChange={setCancelling}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Batalkan order {order.order_number}?</DialogTitle>
                        <DialogDescription>
                            {isOpen
                                ? 'Stok yang dipesan dilepas dan semua dus yang sudah discan kembali ke gudang. Jika pembeli hanya mengambil sebagian, buat order baru sesuai jumlahnya.'
                                : 'Order draft ini tidak akan diproses.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={cancel} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="cancel_reason">Alasan{isOpen ? '' : ' (opsional)'}</Label>
                            <Input
                                id="cancel_reason"
                                value={cancelForm.data.cancel_reason}
                                onChange={(e) => cancelForm.setData('cancel_reason', e.target.value)}
                                required={isOpen}
                            />
                            <InputError message={cancelForm.errors.cancel_reason} />
                        </div>
                        <DialogFooter>
                            <Button type="submit" variant="destructive" disabled={cancelForm.processing}>
                                {cancelForm.processing ? (
                                    <>
                                        <LoaderCircle className="size-4 animate-spin" /> Memproses…
                                    </>
                                ) : (
                                    'Batalkan order'
                                )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <ConfirmDialog confirmation={confirmation} onClose={() => setConfirmation(null)} />
        </AppLayout>
    );
}
