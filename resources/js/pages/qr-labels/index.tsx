import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface PrintBatch {
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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Stiker QR', href: '/qr-labels' }];

export default function QrLabelsIndex({ batches }: { batches: Paginated<PrintBatch> }) {
    const generateForm = useForm({ quantity: '100' });
    const voidForm = useForm({ code: '', reason: '' });

    const generate: FormEventHandler = (e) => {
        e.preventDefault();
        generateForm.post(route('qr-labels.store'));
    };

    const voidLabel: FormEventHandler = (e) => {
        e.preventDefault();
        voidForm.post(route('qr-labels.void'), { preserveScroll: true, onSuccess: () => voidForm.reset() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Stiker QR" />
            <div className="grid gap-6 p-4">
                <Heading title="Stiker QR" description="Hanya stiker dari sistem yang bisa dipakai, dan setiap stiker hanya sekali." />

                <div className="grid gap-4 md:grid-cols-2">
                    <form onSubmit={generate} className="grid content-start gap-3 rounded-lg border p-4">
                        <h2 className="font-semibold">Buat stiker baru</h2>
                        <div className="grid gap-2">
                            <Label htmlFor="quantity">Jumlah stiker (maks. 1000)</Label>
                            <Input
                                id="quantity"
                                type="number"
                                min="1"
                                max="1000"
                                value={generateForm.data.quantity}
                                onChange={(e) => generateForm.setData('quantity', e.target.value)}
                                required
                            />
                            <InputError message={generateForm.errors.quantity} />
                        </div>
                        <Button type="submit" disabled={generateForm.processing}>
                            Buat dan cetak
                        </Button>
                    </form>

                    <form onSubmit={voidLabel} className="grid content-start gap-3 rounded-lg border p-4">
                        <h2 className="font-semibold">Void stiker rusak</h2>
                        <div className="grid gap-2">
                            <Label htmlFor="code">Kode stiker</Label>
                            <Input
                                id="code"
                                placeholder="DUS-261003-0001"
                                className="font-mono uppercase"
                                value={voidForm.data.code}
                                onChange={(e) => voidForm.setData('code', e.target.value)}
                                required
                            />
                            <InputError message={voidForm.errors.code} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reason">Alasan</Label>
                            <Input id="reason" value={voidForm.data.reason} onChange={(e) => voidForm.setData('reason', e.target.value)} required />
                            <InputError message={voidForm.errors.reason} />
                        </div>
                        <Button type="submit" variant="destructive" disabled={voidForm.processing}>
                            Void
                        </Button>
                        {voidForm.recentlySuccessful && <p className="text-sm text-green-700">Stiker sudah di-void.</p>}
                    </form>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3">Dibuat</th>
                                <th className="p-3">Oleh</th>
                                <th className="p-3">Rentang kode</th>
                                <th className="p-3 text-right">Jumlah</th>
                                <th className="p-3 text-right">Available</th>
                                <th className="p-3 text-right">Used</th>
                                <th className="p-3 text-right">Void</th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {batches.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="text-muted-foreground p-6 text-center">
                                        Belum ada stiker.
                                    </td>
                                </tr>
                            )}
                            {batches.data.map((batch) => (
                                <tr key={batch.id} className="border-t">
                                    <td className="p-3 whitespace-nowrap">{formatDateTime(batch.created_at)}</td>
                                    <td className="p-3">{batch.generated_by.name}</td>
                                    <td className="p-3 font-mono text-xs whitespace-nowrap">
                                        {batch.labels_min_code} – {batch.labels_max_code}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">{batch.quantity}</td>
                                    <td className="p-3 text-right tabular-nums">{batch.available_count}</td>
                                    <td className="p-3 text-right tabular-nums">{batch.used_count}</td>
                                    <td className="p-3 text-right tabular-nums">{batch.void_count}</td>
                                    <td className="p-3 text-right">
                                        <Button variant="outline" size="sm" asChild>
                                            <a href={route('qr-labels.print', batch.id)}>Cetak ulang</a>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination links={batches.links} />
            </div>
        </AppLayout>
    );
}
