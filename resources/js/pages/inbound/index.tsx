import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronRight, ScanLine } from 'lucide-react';
import { FormEventHandler } from 'react';

interface BatchRow {
    id: string;
    supplier_name: string;
    delivery_note_number: string | null;
    started_at: string;
    finished_at: string | null;
    boxes_count: number;
    created_by: { id: string; name: string };
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Barang Masuk', href: '/inbound' }];

function BatchList({ batches, empty }: { batches: BatchRow[]; empty: string }) {
    if (batches.length === 0) {
        return <p className="text-muted-foreground rounded-lg border p-4 text-sm">{empty}</p>;
    }

    return (
        <ul className="divide-y rounded-lg border">
            {batches.map((batch) => (
                <li key={batch.id}>
                    <Link href={route('inbound.show', batch.id)} className="hover:bg-muted/50 flex items-center gap-3 p-4">
                        <div className="min-w-0 flex-1">
                            <div className="truncate font-medium">{batch.supplier_name}</div>
                            <div className="text-muted-foreground text-sm">
                                {batch.delivery_note_number ? `SJ ${batch.delivery_note_number} · ` : ''}
                                {formatDateTime(batch.started_at)} · {batch.created_by.name}
                            </div>
                        </div>
                        <div className="text-right">
                            <div className="text-xl font-semibold">{batch.boxes_count}</div>
                            <div className="text-muted-foreground text-xs">dus</div>
                        </div>
                        <ChevronRight className="text-muted-foreground size-5" />
                    </Link>
                </li>
            ))}
        </ul>
    );
}

export default function InboundIndex({ openBatches, finishedBatches }: { openBatches: BatchRow[]; finishedBatches: BatchRow[] }) {
    const form = useForm({ supplier_name: '', delivery_note_number: '', notes: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('inbound.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Barang Masuk" />
            <div className="mx-auto grid w-full max-w-3xl grid-cols-1 gap-8 p-4 lg:max-w-6xl lg:grid-cols-2 lg:items-start">
                <section>
                    <Heading title="Batch Masuk Baru" description="Isi data surat jalan sekali, lalu scan semua dus dari truk yang sama." />
                    <form onSubmit={submit} className="grid gap-4 rounded-lg border p-4">
                        <div className="grid gap-2">
                            <Label htmlFor="supplier_name">Nama supplier</Label>
                            <Input
                                id="supplier_name"
                                className="h-12 text-base"
                                value={form.data.supplier_name}
                                onChange={(e) => form.setData('supplier_name', e.target.value)}
                                required
                            />
                            <InputError message={form.errors.supplier_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="delivery_note_number">Nomor surat jalan (opsional)</Label>
                            <Input
                                id="delivery_note_number"
                                className="h-12 text-base"
                                value={form.data.delivery_note_number}
                                onChange={(e) => form.setData('delivery_note_number', e.target.value)}
                            />
                            <InputError message={form.errors.delivery_note_number} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="notes">Catatan (opsional)</Label>
                            <Input
                                id="notes"
                                className="h-12 text-base"
                                value={form.data.notes}
                                onChange={(e) => form.setData('notes', e.target.value)}
                            />
                            <InputError message={form.errors.notes} />
                        </div>
                        <Button type="submit" size="lg" className="h-14 text-lg" disabled={form.processing}>
                            <ScanLine /> Mulai Scan
                        </Button>
                    </form>
                </section>

                <div className="grid grid-cols-1 gap-8">
                    <section>
                        <h2 className="mb-3 text-lg font-semibold">Batch berjalan</h2>
                        <BatchList batches={openBatches} empty="Tidak ada batch yang sedang berjalan." />
                    </section>

                    <section>
                        <h2 className="mb-3 text-lg font-semibold">Batch selesai terakhir</h2>
                        <BatchList batches={finishedBatches} empty="Belum ada batch selesai." />
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
