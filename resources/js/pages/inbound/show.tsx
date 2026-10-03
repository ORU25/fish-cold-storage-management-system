import { CancelInboundButton } from '@/components/cancel-inbound-button';
import InputError from '@/components/input-error';
import { ScanInput, type ScanFeedback } from '@/components/scan-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatDateTime } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Batch {
    id: string;
    supplier_name: string;
    delivery_note_number: string | null;
    notes: string | null;
    started_at: string;
    finished_at: string | null;
    created_by: { id: string; name: string };
}

interface RecentBox {
    id: string;
    qr_code: string;
    production_date: string | null;
    expired_date: string;
    status: string;
    scanned_in_at: string;
    product: { id: string; display_name: string };
    location: { id: string; name: string } | null;
}

interface SummaryRow {
    product: string;
    production_date: string | null;
    expired_date: string;
    total: number;
}

interface Props {
    batch: Batch;
    boxCount: number;
    recentBoxes: RecentBox[];
    summary: SummaryRow[];
    products: { id: string; display_name: string; shelf_life_days: number | null }[];
    locations: { id: string; name: string }[];
}

const selectClass = 'border-input bg-background h-12 w-full rounded-md border px-3 text-base';

function addDays(date: string, days: number): string {
    const result = new Date(`${date}T00:00:00Z`);
    result.setUTCDate(result.getUTCDate() + days);
    return result.toISOString().slice(0, 10);
}

export default function InboundShow({ batch, boxCount, recentBoxes, summary, products, locations }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Barang Masuk', href: '/inbound' },
        { title: batch.supplier_name, href: route('inbound.show', batch.id) },
    ];
    // Header values stay on the device and are sent with every scan, so changing them mid-batch only affects the next scans.
    const [header, setHeader] = useState({ product_id: '', location_id: '', production_date: '', expired_date: '' });
    const [processing, setProcessing] = useState(false);
    const [feedback, setFeedback] = useState<ScanFeedback | null>(null);
    const isFinished = batch.finished_at !== null;

    const updateHeader = (changes: Partial<typeof header>) => {
        const next = { ...header, ...changes };
        const shelfLife = products.find((product) => product.id === next.product_id)?.shelf_life_days;
        if (('production_date' in changes || 'product_id' in changes) && next.production_date && shelfLife) {
            next.expired_date = addDays(next.production_date, shelfLife);
        }
        setHeader(next);
    };

    const scan = (code: string) => {
        setProcessing(true);
        router.post(
            route('inbound.scans.store', batch.id),
            { ...header, code },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setFeedback({ id: Date.now(), type: 'success', message: `${code.toUpperCase()} masuk` }),
                onError: (scanErrors) =>
                    setFeedback({ id: Date.now(), type: 'error', message: scanErrors.code ?? Object.values(scanErrors)[0] ?? 'Scan gagal' }),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const finish = () => {
        if (confirm(`Selesaikan batch dengan ${boxCount} dus?`)) {
            router.post(route('inbound.finish', batch.id));
        }
    };

    const cancel = () => {
        if (confirm('Batalkan batch ini? Belum ada dus yang discan.')) {
            router.delete(route('inbound.destroy', batch.id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Masuk: ${batch.supplier_name}`} />
            <div className="mx-auto grid w-full max-w-3xl gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">{batch.supplier_name}</h1>
                        <p className="text-muted-foreground text-sm">
                            {batch.delivery_note_number ? `SJ ${batch.delivery_note_number} · ` : ''}
                            Mulai {formatDateTime(batch.started_at)} oleh {batch.created_by.name}
                            {isFinished && ` · Selesai ${formatDateTime(batch.finished_at)}`}
                        </p>
                    </div>
                    <div className="text-right">
                        <div className="text-4xl font-bold tabular-nums">{boxCount}</div>
                        <div className="text-muted-foreground text-sm">dus discan</div>
                    </div>
                </div>

                {!isFinished && (
                    <>
                        <div className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="product_id">Produk ikan</Label>
                                <select
                                    id="product_id"
                                    className={selectClass}
                                    value={header.product_id}
                                    onChange={(e) => updateHeader({ product_id: e.target.value })}
                                >
                                    <option value="">Pilih produk</option>
                                    {products.map((product) => (
                                        <option key={product.id} value={product.id}>
                                            {product.display_name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.product_id} />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="location_id">Lokasi</Label>
                                <select
                                    id="location_id"
                                    className={selectClass}
                                    value={header.location_id}
                                    onChange={(e) => updateHeader({ location_id: e.target.value })}
                                >
                                    <option value="">Pilih lokasi</option>
                                    {locations.map((location) => (
                                        <option key={location.id} value={location.id}>
                                            {location.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.location_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="production_date">Tanggal produksi</Label>
                                <Input
                                    id="production_date"
                                    type="date"
                                    className="h-12 text-base"
                                    value={header.production_date}
                                    onChange={(e) => updateHeader({ production_date: e.target.value })}
                                />
                                <InputError message={errors.production_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="expired_date">Tanggal expired</Label>
                                <Input
                                    id="expired_date"
                                    type="date"
                                    className="h-12 text-base"
                                    value={header.expired_date}
                                    onChange={(e) => updateHeader({ expired_date: e.target.value })}
                                />
                                <InputError message={errors.expired_date} />
                            </div>
                            <p className="text-muted-foreground text-sm sm:col-span-2">
                                Isi sesuai tanggal yang tercetak di dus. Jika tanggal produksi diisi dan produk punya masa simpan, expired terisi
                                otomatis.
                            </p>
                        </div>

                        <ScanInput onScan={scan} processing={processing} feedback={feedback} />
                    </>
                )}

                <section>
                    <h2 className="mb-3 text-lg font-semibold">Scan terakhir</h2>
                    {recentBoxes.length === 0 ? (
                        <p className="text-muted-foreground rounded-lg border p-4 text-sm">Belum ada dus discan.</p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {recentBoxes.map((box) => (
                                <li key={box.id} className="flex items-center gap-3 p-3 text-sm">
                                    <span className="font-mono font-semibold">{box.qr_code}</span>
                                    <span className="min-w-0 flex-1 truncate">
                                        {box.product.display_name} · {box.location?.name ?? '-'}
                                    </span>
                                    <span className="text-muted-foreground whitespace-nowrap">Exp {formatDate(box.expired_date)}</span>
                                    {!isFinished && box.status === 'in_warehouse' && <CancelInboundButton box={box} />}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section>
                    <h2 className="mb-3 text-lg font-semibold">Ringkasan per produk dan tanggal</h2>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3">Produk</th>
                                    <th className="p-3">Produksi</th>
                                    <th className="p-3">Expired</th>
                                    <th className="p-3 text-right">Dus</th>
                                </tr>
                            </thead>
                            <tbody>
                                {summary.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="text-muted-foreground p-4 text-center">
                                            Belum ada data.
                                        </td>
                                    </tr>
                                )}
                                {summary.map((row) => (
                                    <tr key={`${row.product}-${row.production_date}-${row.expired_date}`} className="border-t">
                                        <td className="p-3">{row.product}</td>
                                        <td className="p-3">{formatDate(row.production_date)}</td>
                                        <td className="p-3">{formatDate(row.expired_date)}</td>
                                        <td className="p-3 text-right font-semibold tabular-nums">{row.total}</td>
                                    </tr>
                                ))}
                            </tbody>
                            {summary.length > 0 && (
                                <tfoot className="border-t font-semibold">
                                    <tr>
                                        <td className="p-3" colSpan={3}>
                                            Total
                                        </td>
                                        <td className="p-3 text-right tabular-nums">{boxCount}</td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </section>

                {isFinished ? (
                    <Button asChild variant="outline" size="lg" className="h-14 text-lg">
                        <Link href={route('inbound.index')}>Kembali ke Barang Masuk</Link>
                    </Button>
                ) : boxCount === 0 ? (
                    <Button variant="outline" size="lg" className="h-14 text-lg" onClick={cancel}>
                        Batalkan Batch
                    </Button>
                ) : (
                    <Button size="lg" className="h-14 text-lg" onClick={finish}>
                        Selesai Batch
                    </Button>
                )}
                <InputError message={errors.batch} />
            </div>
        </AppLayout>
    );
}
