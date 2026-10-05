import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, Printer } from 'lucide-react';
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

    const generate: FormEventHandler = (e) => {
        e.preventDefault();
        generateForm.post(route('qr-labels.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Stiker QR" />
            <div className="grid grid-cols-1 gap-6 p-4">
                <Heading title="Stiker QR" description="Hanya stiker dari sistem yang bisa dipakai, dan setiap stiker hanya sekali." />

                <div className="grid max-w-md gap-4">
                    <form onSubmit={generate} className="grid content-start gap-3 rounded-lg border p-4">
                        <h2 className="font-semibold">Buat stiker baru</h2>
                        <div className="grid gap-2">
                            <Label htmlFor="quantity">Jumlah stiker (maks. 100)</Label>
                            <Input
                                id="quantity"
                                type="number"
                                min="1"
                                max="100"
                                value={generateForm.data.quantity}
                                onChange={(e) => generateForm.setData('quantity', e.target.value)}
                                required
                            />
                            <InputError message={generateForm.errors.quantity} />
                        </div>
                        <Button type="submit" disabled={generateForm.processing}>
                            <Printer /> Buat dan cetak
                        </Button>
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
                                    <td className="p-3">
                                        <div className="flex justify-end gap-2">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={route('qr-labels.show', batch.id)}>
                                                    <Eye /> Detail
                                                </Link>
                                            </Button>
                                            <Button variant="outline" size="sm" asChild>
                                                <a href={route('qr-labels.print', batch.id)}>
                                                    <Printer /> Cetak ulang
                                                </a>
                                            </Button>
                                        </div>
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
