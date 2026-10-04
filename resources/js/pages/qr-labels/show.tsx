import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { BOX_STATUS_LABELS, QR_STATUS_LABELS } from '@/lib/labels';
import { formatDate, formatDateTime } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

interface Batch {
    id: string;
    quantity: number;
    created_at: string;
    generated_by: { id: string; name: string };
    available_count: number;
    used_count: number;
    void_count: number;
    labels_min_code: string;
    labels_max_code: string;
}

interface LabelRow {
    id: string;
    code: string;
    status: string;
    used_at: string | null;
    box: {
        id: string;
        status: string;
        expired_date: string;
        product: { display_name: string };
        location: { name: string } | null;
    } | null;
}

interface Props {
    batch: Batch;
    labels: Paginated<LabelRow>;
    filters: { code?: string; status?: string };
    statuses: string[];
}

const selectClass = 'border-input bg-background h-9 rounded-md border px-3 text-sm';
const STATUS_BADGE: Record<string, 'default' | 'secondary' | 'destructive'> = { available: 'default', used: 'secondary', void: 'destructive' };

export default function QrLabelsShow({ batch, labels, filters, statuses }: Props) {
    // Selection is per page: only available stickers can be picked, and it resets whenever the rows change.
    const [selected, setSelected] = useState<string[]>([]);
    const [voiding, setVoiding] = useState(false);
    const voidForm = useForm<{ ids: string[]; reason: string }>({ ids: [], reason: '' });
    const selectable = labels.data.filter((label) => label.status === 'available').map((label) => label.id);
    const allSelected = selectable.length > 0 && selectable.every((id) => selected.includes(id));
    const selectedCodes = labels.data.filter((label) => selected.includes(label.id)).map((label) => label.code);

    useEffect(() => setSelected([]), [labels]);

    const toggle = (id: string, checked: boolean) => setSelected((current) => (checked ? [...current, id] : current.filter((value) => value !== id)));
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Stiker QR', href: '/qr-labels' },
        { title: `${batch.labels_min_code} – ${batch.labels_max_code}`, href: route('qr-labels.show', batch.id) },
    ];

    const filter: FormEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();
        const data = Object.fromEntries([...new FormData(e.currentTarget)].filter(([, value]) => value !== ''));
        router.get(route('qr-labels.show', batch.id), data, { preserveState: true, preserveScroll: true });
    };

    const openVoid = () => {
        voidForm.setData({ ids: selected, reason: '' });
        voidForm.clearErrors();
        setVoiding(true);
    };

    const submitVoid: FormEventHandler = (e) => {
        e.preventDefault();
        voidForm.post(route('qr-labels.void'), { preserveScroll: true, onSuccess: () => setVoiding(false) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Stiker ${batch.labels_min_code}`} />
            <div className="grid gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={`${batch.labels_min_code} – ${batch.labels_max_code}`}
                        description={`${batch.quantity} stiker · dibuat ${formatDateTime(batch.created_at)} oleh ${batch.generated_by.name}`}
                    />
                    <Button asChild>
                        <a href={route('qr-labels.print', batch.id)}>Cetak semua</a>
                    </Button>
                </div>

                <div className="grid grid-cols-3 gap-3 sm:max-w-md">
                    {(['available', 'used', 'void'] as const).map((status) => (
                        <div key={status} className="rounded-lg border p-3">
                            <div className="text-muted-foreground text-xs">{QR_STATUS_LABELS[status]}</div>
                            <div className="text-xl font-semibold tabular-nums">{batch[`${status}_count`]}</div>
                        </div>
                    ))}
                </div>

                <section>
                    <form onSubmit={filter} className="mb-3 flex flex-wrap items-end gap-2">
                        <Input
                            name="code"
                            defaultValue={filters.code ?? ''}
                            placeholder="Cari kode stiker"
                            className="w-48 font-mono"
                            aria-label="Kode stiker"
                        />
                        <select name="status" defaultValue={filters.status ?? ''} className={selectClass} aria-label="Status">
                            <option value="">Semua status</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {QR_STATUS_LABELS[status] ?? status}
                                </option>
                            ))}
                        </select>
                        <Button type="submit">Filter</Button>
                        <Button variant="outline" asChild>
                            <Link href={route('qr-labels.show', batch.id)}>Reset</Link>
                        </Button>
                        <Button type="button" variant="destructive" className="ml-auto" disabled={selected.length === 0} onClick={openVoid}>
                            Void terpilih ({selected.length})
                        </Button>
                    </form>

                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="w-10 p-3">
                                        <Checkbox
                                            checked={allSelected}
                                            disabled={selectable.length === 0}
                                            onCheckedChange={(checked) => setSelected(checked === true ? selectable : [])}
                                            aria-label="Pilih semua stiker available di halaman ini"
                                        />
                                    </th>
                                    <th className="p-3">Kode</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3">Dipakai</th>
                                    <th className="p-3">Dus</th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {labels.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-muted-foreground p-6 text-center">
                                            Tidak ada stiker yang cocok.
                                        </td>
                                    </tr>
                                )}
                                {labels.data.map((label) => (
                                    <tr key={label.id} className="border-t align-top">
                                        <td className="p-3">
                                            {label.status === 'available' && (
                                                <Checkbox
                                                    checked={selected.includes(label.id)}
                                                    onCheckedChange={(checked) => toggle(label.id, checked === true)}
                                                    aria-label={`Pilih ${label.code}`}
                                                />
                                            )}
                                        </td>
                                        <td className="p-3 font-mono text-xs whitespace-nowrap">{label.code}</td>
                                        <td className="p-3">
                                            <Badge variant={STATUS_BADGE[label.status] ?? 'secondary'}>
                                                {QR_STATUS_LABELS[label.status] ?? label.status}
                                            </Badge>
                                        </td>
                                        <td className="p-3 whitespace-nowrap">{label.used_at ? formatDateTime(label.used_at) : '-'}</td>
                                        <td className="p-3">
                                            {label.box ? (
                                                <Link href={route('boxes.show', label.box.id)} className="hover:underline">
                                                    {label.box.product.display_name} · {label.box.location?.name ?? '-'}
                                                    <div className="text-muted-foreground text-xs">
                                                        {BOX_STATUS_LABELS[label.box.status] ?? label.box.status} · Exp{' '}
                                                        {formatDate(label.box.expired_date)}
                                                    </div>
                                                </Link>
                                            ) : (
                                                '-'
                                            )}
                                        </td>
                                        <td className="p-3">
                                            <div className="flex justify-end gap-2">
                                                {label.status !== 'void' && (
                                                    <Button variant="outline" size="sm" asChild>
                                                        <a href={route('qr-labels.print-label', label.id)} target="_blank" rel="noreferrer">
                                                            Cetak
                                                        </a>
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={labels.links} />
                </section>
            </div>

            <Dialog open={voiding} onOpenChange={setVoiding}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Void {selectedCodes.length} stiker?</DialogTitle>
                        <DialogDescription>
                            Stiker void tidak bisa discan masuk dan tidak bisa dicetak lagi. Satu alasan untuk semua stiker terpilih, tercatat di log
                            per stiker.
                        </DialogDescription>
                    </DialogHeader>
                    <p className="max-h-32 overflow-y-auto font-mono text-xs">{selectedCodes.join(', ')}</p>
                    <form onSubmit={submitVoid} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="void-reason">Alasan</Label>
                            <Input
                                id="void-reason"
                                value={voidForm.data.reason}
                                onChange={(e) => voidForm.setData('reason', e.target.value)}
                                placeholder="Contoh: stiker sobek"
                                required
                            />
                            <InputError message={voidForm.errors.ids ?? voidForm.errors.reason} />
                        </div>
                        <DialogFooter>
                            <Button type="submit" variant="destructive" disabled={voidForm.processing}>
                                Void {selectedCodes.length} stiker
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
