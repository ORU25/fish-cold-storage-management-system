import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { ACTION_BADGE, ACTION_LABELS, ADJUSTMENT_TYPE_LABELS, BOX_STATUS_BADGE, BOX_STATUS_LABELS } from '@/lib/labels';
import { formatDate, formatDateTime } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeftRight, Pencil, Save, ShieldAlert } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Box {
    id: string;
    qr_code: string;
    product_id: string;
    location_id: string | null;
    production_date: string | null;
    expired_date: string;
    status: string;
    scanned_in_at: string;
    scanned_out_at: string | null;
    product: { id: string; display_name: string };
    location: { id: string; name: string } | null;
    inbound_batch: { id: string; supplier_name: string; delivery_note_number: string | null; started_at: string };
    outbound_order: { id: string; order_number: string } | null;
    scanned_in_by: { id: string; name: string };
}

interface HistoryRow {
    id: number;
    action: string;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    reason: string | null;
    created_at: string;
    user: { id: string; name: string } | null;
}

interface PendingAdjustment {
    id: string;
    type: string;
    reason: string;
    created_at: string;
    requested_by: { id: string; name: string };
}

interface Props {
    box: Box;
    history: HistoryRow[];
    pendingAdjustment: PendingAdjustment | null;
    names: Record<string, string>;
    products: { id: string; display_name: string; shelf_life_days: number | null }[];
    locations: { id: string; name: string }[];
}

const FIELD_LABELS: Record<string, string> = {
    product_id: 'Produk',
    location_id: 'Lokasi',
    production_date: 'Produksi',
    expired_date: 'Expired',
    status: 'Status',
    order_number: 'Order',
    qr_code: 'Kode',
    type: 'Jenis',
};
const HIDDEN_FIELDS = ['inbound_batch_id', 'scanned_in_by', 'scanned_in_at', 'fefo_violation'];
const selectClass = 'border-input bg-background h-10 w-full rounded-md border pl-3 pr-10 text-sm';

export default function BoxShow({ box, history, pendingAdjustment, names, products, locations }: Props) {
    const { auth } = usePage<SharedData>().props;
    const canCorrect = auth.user.role !== 'staff' && (box.status === 'in_warehouse' || box.status === 'pending_adjustment');
    const [dialog, setDialog] = useState<'revise' | 'move' | 'adjust' | null>(null);
    const reviseForm = useForm({
        product_id: box.product_id,
        production_date: box.production_date ?? '',
        expired_date: box.expired_date,
        reason: '',
    });
    const moveForm = useForm({ location_id: '' });
    const adjustForm = useForm<{ type: string; reason: string; photo: File | null }>({ type: 'lost', reason: '', photo: null });
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Stok', href: '/stock' },
        { title: box.qr_code, href: route('boxes.show', box.id) },
    ];

    const show = (value: unknown, field: string): string => {
        if (value === null || value === undefined || value === '') {
            return '-';
        }
        if (typeof value === 'string' && names[value]) {
            return names[value];
        }
        if (field === 'type' && typeof value === 'string') {
            return ADJUSTMENT_TYPE_LABELS[value] ?? value;
        }
        if (field === 'status' && typeof value === 'string') {
            return BOX_STATUS_LABELS[value] ?? value;
        }
        if (typeof value === 'string' && field.endsWith('_date')) {
            return formatDate(value);
        }
        return String(value);
    };

    const changes = (row: HistoryRow): string[] => {
        const fields = [...new Set([...Object.keys(row.old_values ?? {}), ...Object.keys(row.new_values ?? {})])].filter(
            (field) => !HIDDEN_FIELDS.includes(field),
        );
        return fields.map((field) => {
            const label = FIELD_LABELS[field] ?? field;
            const before = row.old_values && field in row.old_values ? show(row.old_values[field], field) : null;
            const after = row.new_values && field in row.new_values ? show(row.new_values[field], field) : null;
            return before !== null && after !== null ? `${label}: ${before} → ${after}` : `${label}: ${after ?? before}`;
        });
    };

    const openRevise = () => {
        reviseForm.setData({ product_id: box.product_id, production_date: box.production_date ?? '', expired_date: box.expired_date, reason: '' });
        reviseForm.clearErrors();
        setDialog('revise');
    };

    const openMove = () => {
        moveForm.setData('location_id', '');
        moveForm.clearErrors();
        setDialog('move');
    };

    const openAdjust = () => {
        adjustForm.reset();
        adjustForm.clearErrors();
        setDialog('adjust');
    };

    const submitAdjust: FormEventHandler = (e) => {
        e.preventDefault();
        adjustForm.post(route('adjustments.store', box.id), { preserveScroll: true, onSuccess: () => setDialog(null) });
    };

    const submitRevise: FormEventHandler = (e) => {
        e.preventDefault();
        reviseForm.put(route('boxes.update', box.id), { preserveScroll: true, onSuccess: () => setDialog(null) });
    };

    const submitMove: FormEventHandler = (e) => {
        e.preventDefault();
        moveForm.post(route('boxes.move', box.id), { preserveScroll: true, onSuccess: () => setDialog(null) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={box.qr_code} />
            <div className="mx-auto grid w-full max-w-4xl grid-cols-1 gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="font-mono text-xl font-semibold">{box.qr_code}</h1>
                            <Badge variant={BOX_STATUS_BADGE[box.status] ?? 'neutral'}>{BOX_STATUS_LABELS[box.status] ?? box.status}</Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">{box.product.display_name}</p>
                    </div>
                    {canCorrect && (
                        <div className="flex flex-wrap gap-2">
                            {box.status === 'in_warehouse' && (
                                <Button variant="outline" onClick={openAdjust}>
                                    <ShieldAlert /> Ajukan hilang/rusak
                                </Button>
                            )}
                            <Button variant="outline" onClick={openMove}>
                                <ArrowLeftRight /> Pindah lokasi
                            </Button>
                            <Button onClick={openRevise}>
                                <Pencil /> Revisi data
                            </Button>
                        </div>
                    )}
                </div>

                {pendingAdjustment && (
                    <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                        <div className="font-semibold">
                            Diajukan {ADJUSTMENT_TYPE_LABELS[pendingAdjustment.type].toLowerCase()} oleh {pendingAdjustment.requested_by.name},{' '}
                            {formatDateTime(pendingAdjustment.created_at)}. Menunggu keputusan Owner.
                        </div>
                        <div>{pendingAdjustment.reason}</div>
                        <Link href={route('adjustments.index')} className="underline">
                            Lihat pengajuan
                        </Link>
                    </div>
                )}

                <dl className="grid gap-x-6 gap-y-3 rounded-lg border p-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground">Lokasi</dt>
                        <dd className="font-medium">{box.location?.name ?? '-'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Produksi / Expired</dt>
                        <dd className="font-medium">
                            {formatDate(box.production_date)} / {formatDate(box.expired_date)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Masuk</dt>
                        <dd className="font-medium">
                            {box.inbound_batch.supplier_name}
                            {box.inbound_batch.delivery_note_number ? ` · SJ ${box.inbound_batch.delivery_note_number}` : ''}
                            <div className="text-muted-foreground font-normal">
                                {formatDateTime(box.scanned_in_at)} oleh {box.scanned_in_by.name}
                            </div>
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Keluar</dt>
                        <dd className="font-medium">
                            {box.outbound_order ? (
                                <>
                                    <Link
                                        href={route('orders.show', box.outbound_order.id)}
                                        className="text-primary underline-offset-4 hover:underline"
                                    >
                                        {box.outbound_order.order_number}
                                    </Link>
                                    <div className="text-muted-foreground font-normal">{formatDateTime(box.scanned_out_at)}</div>
                                </>
                            ) : (
                                '-'
                            )}
                        </dd>
                    </div>
                </dl>

                <section>
                    <h2 className="mb-3 font-semibold">Riwayat ({history.length})</h2>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3">Waktu</th>
                                    <th className="p-3">User</th>
                                    <th className="p-3">Aksi</th>
                                    <th className="p-3">Perubahan</th>
                                    <th className="p-3">Alasan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.map((row) => (
                                    <tr key={row.id} className="border-t align-top">
                                        <td className="p-3 whitespace-nowrap">{formatDateTime(row.created_at)}</td>
                                        <td className="p-3">{row.user?.name ?? '-'}</td>
                                        <td className="p-3">
                                            <Badge variant={ACTION_BADGE[row.action] ?? 'neutral'} className="whitespace-nowrap">
                                                {ACTION_LABELS[row.action] ?? row.action}
                                            </Badge>
                                        </td>
                                        <td className="p-3">
                                            {changes(row).map((line) => (
                                                <div key={line}>{line}</div>
                                            ))}
                                        </td>
                                        <td className="p-3">{row.reason ?? '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <Dialog open={dialog === 'revise'} onOpenChange={(open) => !open && setDialog(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Revisi data {box.qr_code}</DialogTitle>
                        <DialogDescription>
                            Nilai lama dan baru tercatat di riwayat. Kosongkan expired untuk dihitung dari tanggal produksi.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitRevise} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="product_id">Produk</Label>
                            <select
                                id="product_id"
                                className={selectClass}
                                value={reviseForm.data.product_id}
                                onChange={(e) => reviseForm.setData('product_id', e.target.value)}
                            >
                                {!products.some((product) => product.id === box.product_id) && (
                                    <option value={box.product_id}>{box.product.display_name} (nonaktif)</option>
                                )}
                                {products.map((product) => (
                                    <option key={product.id} value={product.id}>
                                        {product.display_name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={reviseForm.errors.product_id} />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="production_date">Produksi</Label>
                                <Input
                                    id="production_date"
                                    type="date"
                                    value={reviseForm.data.production_date}
                                    onChange={(e) => reviseForm.setData('production_date', e.target.value)}
                                />
                                <InputError message={reviseForm.errors.production_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="expired_date">Expired</Label>
                                <Input
                                    id="expired_date"
                                    type="date"
                                    value={reviseForm.data.expired_date}
                                    onChange={(e) => reviseForm.setData('expired_date', e.target.value)}
                                />
                                <InputError message={reviseForm.errors.expired_date} />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reason">Alasan</Label>
                            <Input
                                id="reason"
                                value={reviseForm.data.reason}
                                onChange={(e) => reviseForm.setData('reason', e.target.value)}
                                placeholder="Contoh: salah input tanggal expired"
                                required
                            />
                            <InputError message={reviseForm.errors.reason} />
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={reviseForm.processing}>
                                <Save /> Simpan revisi
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={dialog === 'move'} onOpenChange={(open) => !open && setDialog(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Pindah lokasi {box.qr_code}</DialogTitle>
                        <DialogDescription>Sekarang di {box.location?.name ?? '-'}. Perpindahan tercatat di riwayat.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitMove} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="location_id">Lokasi tujuan</Label>
                            <select
                                id="location_id"
                                className={selectClass}
                                value={moveForm.data.location_id}
                                onChange={(e) => moveForm.setData('location_id', e.target.value)}
                                required
                            >
                                <option value="">Pilih lokasi</option>
                                {locations.map((location) => (
                                    <option key={location.id} value={location.id}>
                                        {location.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={moveForm.errors.location_id} />
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={moveForm.processing}>
                                <ArrowLeftRight /> Pindahkan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog open={dialog === 'adjust'} onOpenChange={(open) => !open && setDialog(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Ajukan hilang/rusak {box.qr_code}</DialogTitle>
                        <DialogDescription>
                            Dus tidak bisa dikeluarkan selama menunggu keputusan Owner. Stok baru berkurang setelah di-approve.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitAdjust} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="type">Jenis</Label>
                            <select
                                id="type"
                                className={selectClass}
                                value={adjustForm.data.type}
                                onChange={(e) => adjustForm.setData('type', e.target.value)}
                            >
                                {Object.entries(ADJUSTMENT_TYPE_LABELS).map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={adjustForm.errors.type} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="adjust_reason">Alasan</Label>
                            <Input
                                id="adjust_reason"
                                value={adjustForm.data.reason}
                                onChange={(e) => adjustForm.setData('reason', e.target.value)}
                                placeholder="Contoh: tidak ditemukan saat hitung fisik"
                                required
                            />
                            <InputError message={adjustForm.errors.reason} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="photo">Foto (opsional)</Label>
                            <Input
                                id="photo"
                                type="file"
                                accept="image/*"
                                capture="environment"
                                onChange={(e) => adjustForm.setData('photo', e.target.files?.[0] ?? null)}
                            />
                            <InputError message={adjustForm.errors.photo} />
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={adjustForm.processing}>
                                <ShieldAlert /> Ajukan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
