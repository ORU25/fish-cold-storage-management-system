import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { BOX_STATUS_LABELS } from '@/lib/labels';
import { formatDate } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface BoxRow {
    id: string;
    qr_code: string;
    production_date: string | null;
    expired_date: string;
    status: string;
    product: { id: string; display_name: string };
    location: { id: string; name: string } | null;
}

interface Props {
    perProduct: { product: string; mc: number; pending: number; available: number; kg: number }[];
    perLocation: { location: string; product: string; mc: number }[];
    boxes: Paginated<BoxRow>;
    filters: { product_id?: string; location_id?: string; status?: string; code?: string };
    products: { id: string; display_name: string }[];
    locations: { id: string; name: string }[];
    statuses: string[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Stok', href: '/stock' }];
const selectClass = 'border-input bg-background h-9 rounded-md border px-3 text-sm';
const number = (value: number) => value.toLocaleString('id-ID');

export default function StockIndex({ perProduct, perLocation, boxes, filters, products, locations, statuses }: Props) {
    const totalMc = perProduct.reduce((sum, row) => sum + row.mc, 0);
    const totalKg = perProduct.reduce((sum, row) => sum + row.kg, 0);

    const submit: FormEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();
        const data = Object.fromEntries([...new FormData(e.currentTarget)].filter(([, value]) => value !== ''));
        router.get(route('stock.index'), data, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Stok" />
            <div className="grid gap-6 p-4">
                <Heading title="Stok" description="Stok riil = dus di gudang, termasuk yang menunggu keputusan adjustment." />

                <div className="grid gap-6 lg:grid-cols-2">
                    <section>
                        <h2 className="mb-3 font-semibold">Rekap per produk</h2>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3">Produk</th>
                                        <th className="p-3 text-right">MC</th>
                                        <th className="p-3 text-right">KG</th>
                                        <th className="p-3 text-right" title="Di gudang dikurangi kebutuhan order open">
                                            Tersedia
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {perProduct.length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="text-muted-foreground p-4 text-center">
                                                Gudang kosong.
                                            </td>
                                        </tr>
                                    )}
                                    {perProduct.map((row) => (
                                        <tr key={row.product} className="border-t">
                                            <td className="p-3">
                                                {row.product}
                                                {row.pending > 0 && (
                                                    <span className="text-muted-foreground ml-2 text-xs">({row.pending} menunggu approval)</span>
                                                )}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">{number(row.mc)}</td>
                                            <td className="p-3 text-right tabular-nums">{number(row.kg)}</td>
                                            <td className="p-3 text-right tabular-nums">{number(row.available)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                {perProduct.length > 0 && (
                                    <tfoot className="border-t font-semibold">
                                        <tr>
                                            <td className="p-3">Total</td>
                                            <td className="p-3 text-right tabular-nums">{number(totalMc)}</td>
                                            <td className="p-3 text-right tabular-nums">{number(totalKg)}</td>
                                            <td className="p-3 text-right tabular-nums">
                                                {number(perProduct.reduce((sum, row) => sum + row.available, 0))}
                                            </td>
                                        </tr>
                                    </tfoot>
                                )}
                            </table>
                        </div>
                    </section>

                    <section>
                        <h2 className="mb-3 font-semibold">Per lokasi</h2>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3">Lokasi</th>
                                        <th className="p-3">Produk</th>
                                        <th className="p-3 text-right">MC</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {perLocation.length === 0 && (
                                        <tr>
                                            <td colSpan={3} className="text-muted-foreground p-4 text-center">
                                                Gudang kosong.
                                            </td>
                                        </tr>
                                    )}
                                    {perLocation.map((row) => (
                                        <tr key={`${row.location}-${row.product}`} className="border-t">
                                            <td className="p-3">{row.location}</td>
                                            <td className="p-3">{row.product}</td>
                                            <td className="p-3 text-right tabular-nums">{number(row.mc)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <section>
                    <h2 className="mb-3 font-semibold">Daftar dus ({number(boxes.total)})</h2>
                    <form onSubmit={submit} className="mb-3 flex flex-wrap items-end gap-2">
                        <Input
                            name="code"
                            defaultValue={filters.code ?? ''}
                            placeholder="Cari kode dus"
                            className="w-48 font-mono"
                            aria-label="Kode dus"
                        />
                        <select name="product_id" defaultValue={filters.product_id ?? ''} className={selectClass} aria-label="Produk">
                            <option value="">Semua produk</option>
                            {products.map((product) => (
                                <option key={product.id} value={product.id}>
                                    {product.display_name}
                                </option>
                            ))}
                        </select>
                        <select name="location_id" defaultValue={filters.location_id ?? ''} className={selectClass} aria-label="Lokasi">
                            <option value="">Semua lokasi</option>
                            {locations.map((location) => (
                                <option key={location.id} value={location.id}>
                                    {location.name}
                                </option>
                            ))}
                        </select>
                        <select name="status" defaultValue={filters.status ?? ''} className={selectClass} aria-label="Status">
                            <option value="">Stok riil</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {BOX_STATUS_LABELS[status] ?? status}
                                </option>
                            ))}
                        </select>
                        <Button type="submit">Filter</Button>
                        <Button variant="outline" asChild>
                            <Link href={route('stock.index')}>Reset</Link>
                        </Button>
                    </form>

                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3">Kode</th>
                                    <th className="p-3">Produk</th>
                                    <th className="p-3">Lokasi</th>
                                    <th className="p-3">Produksi</th>
                                    <th className="p-3">Expired</th>
                                    <th className="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {boxes.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-muted-foreground p-6 text-center">
                                            Tidak ada dus.
                                        </td>
                                    </tr>
                                )}
                                {boxes.data.map((box) => (
                                    <tr key={box.id} className="border-t">
                                        <td className="p-3 font-mono text-xs">
                                            <Link href={route('boxes.show', box.id)} className="hover:underline">
                                                {box.qr_code}
                                            </Link>
                                        </td>
                                        <td className="p-3">{box.product.display_name}</td>
                                        <td className="p-3">{box.location?.name ?? '-'}</td>
                                        <td className="p-3">{formatDate(box.production_date)}</td>
                                        <td className="p-3">{formatDate(box.expired_date)}</td>
                                        <td className="p-3">
                                            <Badge variant={box.status === 'in_warehouse' ? 'default' : 'secondary'}>
                                                {BOX_STATUS_LABELS[box.status] ?? box.status}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={boxes.links} />
                </section>
            </div>
        </AppLayout>
    );
}
