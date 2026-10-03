import InputError from '@/components/input-error';
import { ScanInput, type ScanFeedback } from '@/components/scan-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { ORDER_STATUS_LABELS } from '@/lib/labels';
import { cn, formatDate, formatDateTime } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Order {
    id: string;
    order_number: string;
    destination: string;
    order_date: string;
    status: string;
    items: { id: string; quantity_requested: number; quantity_scanned: number; product: { display_name: string } }[];
}

interface PickItem {
    product: string;
    remaining: number;
    groups: { expired_date: string; location: string; codes: string[] }[];
}

interface RecentScan {
    id: string;
    created_at: string;
    fefo_violation: boolean;
    box: { qr_code: string; expired_date: string; product: { display_name: string } };
}

export default function OutboundShow({ order, pickList, recentScans }: { order: Order; pickList: PickItem[]; recentScans: RecentScan[] }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Barang Keluar', href: '/outbound' },
        { title: order.destination, href: route('outbound.show', order.id) },
    ];
    const [processing, setProcessing] = useState(false);
    const [feedback, setFeedback] = useState<ScanFeedback | null>(null);
    // A FEFO warning holds the scanned code until the staff gives a reason or gives up.
    const [fefo, setFefo] = useState<{ code: string; reason: string; error?: string } | null>(null);
    const isOpen = order.status === 'open';
    const isFullyScanned = order.items.every((item) => item.quantity_scanned >= item.quantity_requested);
    const requested = order.items.reduce((sum, item) => sum + item.quantity_requested, 0);
    const scanned = order.items.reduce((sum, item) => sum + item.quantity_scanned, 0);

    const send = (code: string, fefoReason?: string) => {
        setProcessing(true);
        router.post(
            route('outbound.scans.store', order.id),
            { code, fefo_reason: fefoReason ?? null },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setFefo(null);
                    setFeedback({
                        id: Date.now(),
                        type: fefoReason ? 'warning' : 'success',
                        message: fefoReason ? `${code.toUpperCase()} keluar (melanggar FEFO, alasan dicatat)` : `${code.toUpperCase()} keluar`,
                    });
                },
                onError: (errors) => {
                    if (errors.fefo || (fefoReason !== undefined && errors.fefo_reason)) {
                        setFefo({ code, reason: fefoReason ?? '', error: errors.fefo_reason });
                        setFeedback({ id: Date.now(), type: 'warning', message: errors.fefo ?? errors.fefo_reason });
                        return;
                    }
                    setFefo(null);
                    setFeedback({
                        id: Date.now(),
                        type: 'error',
                        message: errors.code ?? errors.fefo_reason ?? Object.values(errors)[0] ?? 'Scan gagal',
                    });
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const confirmFefo: FormEventHandler = (e) => {
        e.preventDefault();
        if (fefo && fefo.reason.trim()) {
            send(fefo.code, fefo.reason.trim());
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Keluar: ${order.destination}`} />
            <div className="mx-auto grid w-full max-w-3xl gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">{order.destination}</h1>
                        <p className="text-muted-foreground text-sm">
                            <span className="font-mono">{order.order_number}</span> · {formatDate(order.order_date)}
                            {!isOpen && ` · ${ORDER_STATUS_LABELS[order.status]}`}
                        </p>
                    </div>
                    <div className="text-right">
                        <div className="text-4xl font-bold tabular-nums">
                            {scanned}/{requested}
                        </div>
                        <div className="text-muted-foreground text-sm">dus keluar</div>
                    </div>
                </div>

                <ul className="grid gap-2">
                    {order.items.map((item) => {
                        const done = item.quantity_scanned >= item.quantity_requested;
                        return (
                            <li
                                key={item.id}
                                className={cn(
                                    'flex items-center justify-between rounded-lg border p-3',
                                    done && 'border-green-600 bg-green-50 dark:bg-green-950',
                                )}
                            >
                                <span className="font-medium">{item.product.display_name}</span>
                                <span className="tabular-nums">
                                    {item.quantity_scanned}/{item.quantity_requested} {done && '✓'}
                                </span>
                            </li>
                        );
                    })}
                </ul>

                {isOpen ? (
                    <>
                        {isFullyScanned && (
                            <div className="rounded-xl border-2 border-green-600 bg-green-50 p-4 font-semibold text-green-800 dark:bg-green-950 dark:text-green-200">
                                Semua item lengkap. Menunggu pengecekan dan penyelesaian oleh Admin.
                            </div>
                        )}
                        <ScanInput
                            onScan={(code) => send(code)}
                            processing={processing || fefo !== null}
                            feedback={feedback}
                            lockedMessage={isFullyScanned ? 'Order lengkap' : undefined}
                        />

                        {fefo && (
                            <form onSubmit={confirmFefo} className="grid gap-3 rounded-xl border-2 border-amber-500 p-4">
                                <Label htmlFor="fefo_reason">Alasan mengeluarkan {fefo.code.toUpperCase()} di luar urutan FEFO</Label>
                                <Input
                                    id="fefo_reason"
                                    className="h-12 text-base"
                                    value={fefo.reason}
                                    onChange={(e) => setFefo({ ...fefo, reason: e.target.value })}
                                    placeholder="Contoh: dus expired terdekat tertumpuk di bawah"
                                    autoFocus
                                    required
                                />
                                <InputError message={fefo.error} />
                                <div className="flex gap-2">
                                    <Button type="submit" size="lg" disabled={processing || !fefo.reason.trim()}>
                                        Tetap keluarkan
                                    </Button>
                                    <Button type="button" size="lg" variant="outline" onClick={() => setFefo(null)}>
                                        Batal
                                    </Button>
                                </div>
                            </form>
                        )}
                    </>
                ) : (
                    <div className="rounded-lg border p-4">
                        Order ini sudah {ORDER_STATUS_LABELS[order.status].toLowerCase()}.{' '}
                        <Link href={route('outbound.index')} className="underline">
                            Kembali ke daftar order
                        </Link>
                    </div>
                )}

                {pickList.length > 0 && (
                    <section className="print-area">
                        <div className="mb-3 flex items-center justify-between gap-2">
                            <h2 className="text-lg font-semibold">Daftar ambil (FEFO)</h2>
                            <Button variant="outline" size="sm" onClick={() => window.print()} className="print:hidden">
                                <Printer className="size-4" /> Cetak / simpan PDF
                            </Button>
                        </div>
                        <p className="text-muted-foreground mb-3 hidden text-sm print:block">
                            {order.order_number} · {order.destination} · dicetak {formatDateTime(new Date().toISOString())}
                        </p>
                        <div className="grid gap-4">
                            {pickList.map((item) => (
                                <div key={item.product} className="rounded-lg border">
                                    <div className="bg-muted/50 flex justify-between p-3 font-semibold">
                                        <span>{item.product}</span>
                                        <span>ambil {item.remaining} dus</span>
                                    </div>
                                    {item.groups.length === 0 && <p className="p-3 text-sm text-red-600">Tidak ada dus di gudang.</p>}
                                    <ul className="divide-y">
                                        {item.groups.map((group) => (
                                            <li key={`${group.expired_date}-${group.location}`} className="p-3 text-sm">
                                                <div className="flex justify-between font-medium">
                                                    <span>
                                                        Exp {formatDate(group.expired_date)} · {group.location}
                                                    </span>
                                                    <span>{group.codes.length} dus</span>
                                                </div>
                                                <div className="text-muted-foreground mt-1 font-mono text-xs break-words">
                                                    {group.codes.join(', ')}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                    {item.groups.reduce((sum, group) => sum + group.codes.length, 0) < item.remaining && item.groups.length > 0 && (
                                        <p className="p-3 text-sm text-red-600">Stok di gudang kurang dari yang diminta.</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                <section>
                    <h2 className="mb-3 text-lg font-semibold">Scan terakhir</h2>
                    {recentScans.length === 0 ? (
                        <p className="text-muted-foreground rounded-lg border p-4 text-sm">Belum ada dus keluar.</p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {recentScans.map((scan) => (
                                <li key={scan.id} className="flex items-center gap-3 p-3 text-sm">
                                    <span className="font-mono font-semibold">{scan.box.qr_code}</span>
                                    <span className="min-w-0 flex-1 truncate">{scan.box.product.display_name}</span>
                                    {scan.fefo_violation && <span className="text-xs font-semibold text-amber-600">FEFO</span>}
                                    <span className="text-muted-foreground whitespace-nowrap">Exp {formatDate(scan.box.expired_date)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
