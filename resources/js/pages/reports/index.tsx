import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { ADJUSTMENT_STATUS_BADGE, ADJUSTMENT_STATUS_LABELS, ADJUSTMENT_TYPE_LABELS } from '@/lib/labels';
import { cn, formatDate, formatDateTime } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Download, Eye } from 'lucide-react';
import { FormEventHandler } from 'react';

type LocationRow = {
    location: string;
    products: { product: string; boxes: { id: string; qr_code: string; expired_date: string; status: string }[] }[];
};
type MutationRow = { product: string; opening: number; in: number; out: number; adjustment: number; closing: number };
type AdjustmentRow = {
    id: string;
    type: string;
    reason: string;
    status: string;
    created_at: string;
    decided_at: string | null;
    decision_note: string | null;
    box: { id: string; qr_code: string; product: { display_name: string } };
    requested_by: { name: string };
    decided_by: { name: string } | null;
};

type Props = { from: string; to: string } & (
    | { tab: 'location'; rows: LocationRow[] }
    | { tab: 'mutation'; rows: MutationRow[] }
    | { tab: 'adjustment'; rows: AdjustmentRow[] }
);

const TABS = [
    { value: 'location', label: 'Stok per lokasi' },
    { value: 'mutation', label: 'Mutasi' },
    { value: 'adjustment', label: 'Adjustment' },
];
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Laporan', href: '/reports' }];
const number = (value: number) => value.toLocaleString('id-ID');

export default function ReportsIndex(props: Props) {
    const { tab, from, to } = props;
    const period = tab === 'location' ? {} : { from, to };

    const changePeriod: FormEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();
        router.get(route('reports.index'), { tab, ...Object.fromEntries(new FormData(e.currentTarget)) }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Laporan" />
            <div className="grid grid-cols-1 gap-4 p-4">
                <Heading
                    title="Laporan"
                    description="Stok per lokasi untuk hitung fisik manual, mutasi per periode, dan adjustment. Unduh sebagai file Excel."
                />

                <nav className="flex flex-wrap gap-2">
                    {TABS.map((item) => (
                        <Link
                            key={item.value}
                            href={route('reports.index', { tab: item.value, ...(item.value === 'location' ? {} : { from, to }) })}
                            className={cn(
                                'rounded-md border px-3 py-1.5 text-sm',
                                tab === item.value ? 'bg-primary text-primary-foreground' : 'hover:bg-muted',
                            )}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>

                <div className="flex flex-wrap items-end justify-between gap-3">
                    {tab === 'location' ? (
                        <p className="text-muted-foreground text-sm">Stok riil saat ini, termasuk dus yang menunggu keputusan adjustment.</p>
                    ) : (
                        <form key={`${from}-${to}`} onSubmit={changePeriod} className="flex flex-wrap items-end gap-2">
                            <div className="grid gap-1">
                                <Label htmlFor="from">Dari</Label>
                                <Input id="from" name="from" type="date" defaultValue={from} required />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="to">Sampai</Label>
                                <Input id="to" name="to" type="date" defaultValue={to} required />
                            </div>
                            <Button type="submit" variant="neutral">
                                <Eye /> Tampilkan
                            </Button>
                        </form>
                    )}
                    <Button asChild>
                        <a href={route('reports.export', { tab, ...period })}>
                            <Download className="size-4" /> Unduh Excel
                        </a>
                    </Button>
                </div>

                {props.tab === 'location' && <LocationReport rows={props.rows} />}
                {props.tab === 'mutation' && <MutationReport rows={props.rows} />}
                {props.tab === 'adjustment' && <AdjustmentReport rows={props.rows} />}
            </div>
        </AppLayout>
    );
}

function LocationReport({ rows }: { rows: LocationRow[] }) {
    if (rows.length === 0) {
        return <p className="text-muted-foreground rounded-lg border p-6 text-center text-sm">Gudang kosong.</p>;
    }

    return (
        <div className="grid gap-4">
            {rows.map((location) => (
                <section key={location.location} className="rounded-lg border">
                    <h2 className="bg-muted/50 flex justify-between p-3 font-semibold">
                        <span>{location.location}</span>
                        <span>{number(location.products.reduce((sum, product) => sum + product.boxes.length, 0))} dus</span>
                    </h2>
                    {location.products.map((product) => (
                        <div key={product.product} className="border-t p-3">
                            <div className="mb-2 flex justify-between font-medium">
                                <span>{product.product}</span>
                                <span className="tabular-nums">{number(product.boxes.length)} dus</span>
                            </div>
                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                {product.boxes.map((box) => (
                                    <Link
                                        key={box.id}
                                        href={route('boxes.show', box.id)}
                                        className="text-primary font-mono underline-offset-4 hover:underline"
                                    >
                                        {box.qr_code}
                                        <span className="text-muted-foreground font-sans"> exp {formatDate(box.expired_date)}</span>
                                        {box.status === 'pending_adjustment' && (
                                            <span className="font-sans text-amber-600"> (menunggu approval)</span>
                                        )}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    ))}
                </section>
            ))}
        </div>
    );
}

function MutationReport({ rows }: { rows: MutationRow[] }) {
    const total = (key: keyof Omit<MutationRow, 'product'>) => number(rows.reduce((sum, row) => sum + row[key], 0));

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left">
                    <tr>
                        <th className="p-3">Produk</th>
                        <th className="p-3 text-right">Stok awal</th>
                        <th className="p-3 text-right">Masuk</th>
                        <th className="p-3 text-right">Keluar</th>
                        <th className="p-3 text-right">Adjustment</th>
                        <th className="p-3 text-right">Stok akhir</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && (
                        <tr>
                            <td colSpan={6} className="text-muted-foreground p-6 text-center">
                                Tidak ada stok atau mutasi di periode ini.
                            </td>
                        </tr>
                    )}
                    {rows.map((row) => (
                        <tr key={row.product} className="border-t tabular-nums">
                            <td className="p-3">{row.product}</td>
                            <td className="p-3 text-right">{number(row.opening)}</td>
                            <td className="p-3 text-right text-emerald-700">+{number(row.in)}</td>
                            <td className="p-3 text-right text-orange-700">−{number(row.out)}</td>
                            <td className="p-3 text-right text-red-700">−{number(row.adjustment)}</td>
                            <td className="p-3 text-right font-semibold">{number(row.closing)}</td>
                        </tr>
                    ))}
                </tbody>
                {rows.length > 0 && (
                    <tfoot className="border-t font-semibold tabular-nums">
                        <tr>
                            <td className="p-3">Total (dus)</td>
                            <td className="p-3 text-right">{total('opening')}</td>
                            <td className="p-3 text-right">+{total('in')}</td>
                            <td className="p-3 text-right">−{total('out')}</td>
                            <td className="p-3 text-right">−{total('adjustment')}</td>
                            <td className="p-3 text-right">{total('closing')}</td>
                        </tr>
                    </tfoot>
                )}
            </table>
        </div>
    );
}

function AdjustmentReport({ rows }: { rows: AdjustmentRow[] }) {
    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left">
                    <tr>
                        <th className="p-3">Diajukan</th>
                        <th className="p-3">Dus</th>
                        <th className="p-3">Jenis</th>
                        <th className="p-3">Alasan</th>
                        <th className="p-3">Status</th>
                        <th className="p-3">Keputusan</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && (
                        <tr>
                            <td colSpan={6} className="text-muted-foreground p-6 text-center">
                                Tidak ada pengajuan di periode ini.
                            </td>
                        </tr>
                    )}
                    {rows.map((adjustment) => (
                        <tr key={adjustment.id} className="border-t align-top">
                            <td className="p-3 whitespace-nowrap">
                                {formatDateTime(adjustment.created_at)}
                                <div className="text-muted-foreground">{adjustment.requested_by.name}</div>
                            </td>
                            <td className="p-3">
                                <Link
                                    href={route('boxes.show', adjustment.box.id)}
                                    className="text-primary font-mono text-xs underline-offset-4 hover:underline"
                                >
                                    {adjustment.box.qr_code}
                                </Link>
                                <div>{adjustment.box.product.display_name}</div>
                            </td>
                            <td className="p-3">{ADJUSTMENT_TYPE_LABELS[adjustment.type]}</td>
                            <td className="p-3">{adjustment.reason}</td>
                            <td className="p-3">
                                <Badge variant={ADJUSTMENT_STATUS_BADGE[adjustment.status]}>{ADJUSTMENT_STATUS_LABELS[adjustment.status]}</Badge>
                            </td>
                            <td className="p-3">
                                {adjustment.decided_by ? (
                                    <>
                                        {adjustment.decided_by.name}, {formatDateTime(adjustment.decided_at)}
                                        {adjustment.decision_note && <div className="text-muted-foreground">{adjustment.decision_note}</div>}
                                    </>
                                ) : (
                                    '-'
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
