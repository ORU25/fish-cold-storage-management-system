import { AdjustmentDecision } from '@/components/adjustment-decision';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { ACTION_BADGE, ACTION_LABELS, ADJUSTMENT_TYPE_BADGE, ADJUSTMENT_TYPE_LABELS, BOX_STATUS_LABELS } from '@/lib/labels';
import { cn, formatDate, formatDateTime } from '@/lib/utils';
import { type BreadcrumbItem, type Role, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Boxes,
    Check,
    ClipboardList,
    Clock,
    FileSpreadsheet,
    type LucideIcon,
    PackageMinus,
    PackagePlus,
    QrCode,
    ShieldAlert,
    TriangleAlert,
} from 'lucide-react';
import { FormEventHandler, ReactNode } from 'react';

interface Props {
    stock: {
        perProduct: { product: string; mc: number; pending: number; available: number; kg: number }[];
        days: number;
        nearExpiry: {
            id: string;
            qr_code: string;
            expired_date: string;
            status: string;
            product: { display_name: string };
            location: { name: string } | null;
        }[];
    } | null;
    owner: {
        pendingAdjustments: {
            id: string;
            type: string;
            reason: string;
            created_at: string;
            box: { id: string; qr_code: string; product: { display_name: string }; location: { name: string } | null };
            requested_by: { name: string };
        }[];
        fefoViolations: {
            id: string;
            fefo_reason: string | null;
            created_at: string;
            box: { id: string; qr_code: string; expired_date: string; product: { display_name: string } };
            item: { order: { id: string; order_number: string } };
            scanned_by: { name: string };
        }[];
        adminActions: {
            id: number;
            action: string;
            user: string | null;
            box_id: string | null;
            subject: string | null;
            reason: string | null;
            created_at: string;
        }[];
        daily: { date: string; in: number; out: number }[];
    } | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const ACTIONS: { title: string; description: string; href: string; icon: LucideIcon; roles: Role[] }[] = [
    { title: 'Barang Masuk', description: 'Scan dus dari truk', href: '/inbound', icon: PackagePlus, roles: ['staff', 'admin'] },
    { title: 'Barang Keluar', description: 'Scan dus sesuai order', href: '/outbound', icon: PackageMinus, roles: ['staff', 'admin'] },
    { title: 'Order Keluar', description: 'Buat dan pantau order', href: '/orders', icon: ClipboardList, roles: ['admin'] },
    { title: 'Stok', description: 'Rekap dan daftar dus', href: '/stock', icon: Boxes, roles: ['owner', 'admin'] },
    { title: 'Laporan', description: 'Stok per lokasi, mutasi, export', href: '/reports', icon: FileSpreadsheet, roles: ['owner', 'admin'] },
    { title: 'Adjustment', description: 'Dus hilang atau rusak', href: '/adjustments', icon: ShieldAlert, roles: ['owner', 'admin'] },
    { title: 'Pindah Lokasi', description: 'Scan dus ke lokasi baru', href: '/box-moves', icon: ArrowLeftRight, roles: ['admin'] },
    { title: 'Stiker QR', description: 'Buat dan cetak stiker', href: '/qr-labels', icon: QrCode, roles: ['admin'] },
];

const number = (value: number) => value.toLocaleString('id-ID');

function Panel({
    id,
    title,
    action,
    scroll,
    fill,
    children,
}: {
    id?: string;
    title: string;
    action?: ReactNode;
    scroll?: boolean;
    /** Grow to the height of the panel beside it. */
    fill?: boolean;
    children: ReactNode;
}) {
    return (
        <section id={id} className={cn('grid min-w-0 gap-3', fill ? 'grid-rows-[auto_1fr]' : 'content-start')}>
            <div className="flex min-h-9 flex-wrap items-center justify-between gap-2">
                <h2 className="font-semibold">{title}</h2>
                {action}
            </div>
            <div className={cn('overflow-x-auto rounded-lg border', scroll && 'max-h-[28rem] overflow-y-auto', fill && 'flex flex-col')}>
                {children}
            </div>
        </section>
    );
}

const panelLink = 'text-primary text-sm underline-offset-4 hover:underline';

function Stat({ label, value, sub, href }: { label: string; value: string; sub: ReactNode; href: string }) {
    const className = 'hover:bg-muted/50 grid gap-1 rounded-xl border p-4 transition-colors';
    const content = (
        <>
            <div className="text-muted-foreground text-sm">{label}</div>
            <div className="text-3xl font-semibold tabular-nums">{value}</div>
            <div className="text-muted-foreground text-sm">{sub}</div>
        </>
    );

    return href.startsWith('#') ? (
        <a href={href} className={className}>
            {content}
        </a>
    ) : (
        <Link href={href} className={className}>
            {content}
        </Link>
    );
}

/** Two thin bars per day (in green, out orange) on one shared scale; exact numbers in the hover tooltip. */
function DailyChart({ days }: { days: { date: string; in: number; out: number }[] }) {
    const busiestDay = Math.max(1, ...days.flatMap((day) => [day.in, day.out]));

    return (
        <div className="flex flex-1 flex-col p-4" role="img" aria-label="Grafik dus masuk dan keluar per hari, 14 hari terakhir">
            <div className="flex min-h-40 flex-1 items-end gap-1 border-b">
                {days.map((day) => (
                    <div
                        key={day.date}
                        title={`${formatDate(day.date)}: masuk ${day.in}, keluar ${day.out}`}
                        className="hover:bg-muted/60 flex h-full flex-1 items-end justify-center gap-0.5 rounded-t"
                    >
                        <div className="w-full max-w-3 rounded-t bg-emerald-600" style={{ height: `${(day.in / busiestDay) * 100}%` }} />
                        <div className="w-full max-w-3 rounded-t bg-orange-600" style={{ height: `${(day.out / busiestDay) * 100}%` }} />
                    </div>
                ))}
            </div>
            <div className="mt-1 flex gap-1">
                {days.map((day) => (
                    <div key={day.date} className="text-muted-foreground flex-1 text-center text-xs tabular-nums">
                        {day.date.slice(8, 10)}
                    </div>
                ))}
            </div>
        </div>
    );
}

function Empty({ colSpan, children }: { colSpan: number; children: ReactNode }) {
    return (
        <tr>
            <td colSpan={colSpan} className="text-muted-foreground p-4 text-center">
                {children}
            </td>
        </tr>
    );
}

const BoxLink = ({ id, code }: { id: string; code: string }) => (
    <Link href={route('boxes.show', id)} className="text-primary font-mono text-xs underline-offset-4 hover:underline">
        {code}
    </Link>
);

export default function Dashboard({ stock, owner }: Props) {
    const { auth } = usePage<SharedData>().props;
    const actions = ACTIONS.filter((action) => auth.user.role === 'owner' || action.roles.includes(auth.user.role));
    const today = new Date().toISOString().slice(0, 10);

    const changeDays: FormEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();
        router.get(route('dashboard'), { days: new FormData(e.currentTarget).get('days') }, { preserveScroll: true, preserveState: true });
    };

    // Staff only get the big shortcut cards.
    if (!stock) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Dashboard" />
                <div className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    {actions.map((action) => (
                        <Link
                            key={action.href}
                            href={action.href}
                            className="hover:bg-muted/50 flex min-h-32 items-center gap-4 rounded-xl border p-6 transition-colors"
                        >
                            <action.icon className="size-10 shrink-0" />
                            <div>
                                <div className="text-xl font-semibold">{action.title}</div>
                                <div className="text-muted-foreground text-sm">{action.description}</div>
                            </div>
                        </Link>
                    ))}
                </div>
            </AppLayout>
        );
    }

    const total = (key: 'mc' | 'kg' | 'pending') => stock.perProduct.reduce((sum, row) => sum + row[key], 0);
    const expiredCount = stock.nearExpiry.filter((box) => box.expired_date < today).length;
    const pendingCount = total('pending');

    const nearExpiryPanel = (
        <Panel
            id="mendekati-expired"
            title={`Mendekati expired (≤ ${stock.days} hari)`}
            scroll
            action={
                <form onSubmit={changeDays} className="flex items-center gap-2">
                    <Input name="days" type="number" min={1} max={365} defaultValue={stock.days} className="h-9 w-20" aria-label="Batas hari" />
                    <Button type="submit" size="sm" variant="neutral">
                        <Check /> Terapkan
                    </Button>
                </form>
            }
        >
            <table className="w-full text-sm">
                <thead className="bg-muted sticky top-0 text-left">
                    <tr>
                        <th className="p-3">Expired</th>
                        <th className="p-3">Dus</th>
                        <th className="p-3">Lokasi</th>
                    </tr>
                </thead>
                <tbody>
                    {stock.nearExpiry.length === 0 && <Empty colSpan={3}>Tidak ada dus yang mendekati expired.</Empty>}
                    {stock.nearExpiry.map((box) => (
                        <tr key={box.id} className="border-t">
                            <td className={cn('p-3 whitespace-nowrap', box.expired_date < today && 'font-semibold text-red-600')}>
                                {formatDate(box.expired_date)}
                            </td>
                            <td className="p-3">
                                <BoxLink id={box.id} code={box.qr_code} />
                                <div>
                                    {box.product.display_name}
                                    {box.status !== 'in_warehouse' && (
                                        <span className="text-muted-foreground ml-2 text-xs">({BOX_STATUS_LABELS[box.status]})</span>
                                    )}
                                </div>
                            </td>
                            <td className="p-3">{box.location?.name ?? '-'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </Panel>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="grid grid-cols-1 gap-6 p-4">
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {actions.map((action) => (
                        <Link
                            key={action.href}
                            href={action.href}
                            className="hover:bg-muted/50 flex items-center gap-3 rounded-lg border p-3 text-sm font-medium transition-colors"
                        >
                            <action.icon className="size-5 shrink-0" />
                            {action.title}
                        </Link>
                    ))}
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <Stat label="Stok di gudang" value={`${number(total('mc'))} dus`} sub={`${number(total('kg'))} kg`} href="/stock" />
                    <Stat
                        label={`Mendekati expired (≤ ${stock.days} hari)`}
                        value={stock.nearExpiry.length >= 50 ? '50+ dus' : `${stock.nearExpiry.length} dus`}
                        sub={
                            expiredCount > 0 ? (
                                <span className="inline-flex items-center gap-1 font-medium text-red-600">
                                    <TriangleAlert className="size-4" /> {expiredCount} sudah lewat expired
                                </span>
                            ) : (
                                'Belum ada yang lewat expired'
                            )
                        }
                        href="#mendekati-expired"
                    />
                    <Stat
                        label="Menunggu approval"
                        value={`${pendingCount} dus`}
                        sub={
                            pendingCount > 0 ? (
                                <span className="inline-flex items-center gap-1 font-medium text-amber-700">
                                    <Clock className="size-4" /> Belum diputuskan Owner
                                </span>
                            ) : (
                                'Tidak ada pengajuan'
                            )
                        }
                        href="/adjustments"
                    />
                </div>

                {owner && owner.pendingAdjustments.length > 0 && (
                    <Panel
                        title={`Adjustment menunggu keputusan (${owner.pendingAdjustments.length})`}
                        action={
                            <Link href={route('adjustments.index')} className={panelLink}>
                                Lihat semua
                            </Link>
                        }
                    >
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3">Diajukan</th>
                                    <th className="p-3">Dus</th>
                                    <th className="p-3">Jenis dan alasan</th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {owner.pendingAdjustments.map((adjustment) => (
                                    <tr key={adjustment.id} className="border-t align-top">
                                        <td className="p-3 whitespace-nowrap">
                                            {formatDateTime(adjustment.created_at)}
                                            <div className="text-muted-foreground">{adjustment.requested_by.name}</div>
                                        </td>
                                        <td className="p-3">
                                            <BoxLink id={adjustment.box.id} code={adjustment.box.qr_code} />
                                            <div>{adjustment.box.product.display_name}</div>
                                        </td>
                                        <td className="p-3">
                                            <Badge variant={ADJUSTMENT_TYPE_BADGE[adjustment.type]}>{ADJUSTMENT_TYPE_LABELS[adjustment.type]}</Badge>
                                            <div className="mt-1">{adjustment.reason}</div>
                                        </td>
                                        <td className="p-3">
                                            <AdjustmentDecision adjustment={adjustment} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Panel>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Panel title="Rekap stok per produk" scroll>
                        <table className="w-full text-sm">
                            <thead className="bg-muted sticky top-0 text-left">
                                <tr>
                                    <th className="p-3">Produk</th>
                                    <th className="p-3 text-right">MC</th>
                                    <th className="p-3 text-right">KG</th>
                                </tr>
                            </thead>
                            <tbody>
                                {stock.perProduct.length === 0 && <Empty colSpan={3}>Gudang kosong.</Empty>}
                                {stock.perProduct.map((row) => (
                                    <tr key={row.product} className="border-t">
                                        <td className="p-3">
                                            {row.product}
                                            {row.pending > 0 && (
                                                <span className="text-muted-foreground ml-2 text-xs">({row.pending} menunggu approval)</span>
                                            )}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">{number(row.mc)}</td>
                                        <td className="p-3 text-right tabular-nums">{number(row.kg)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            {stock.perProduct.length > 0 && (
                                <tfoot className="bg-background sticky bottom-0 font-semibold shadow-[inset_0_1px_0_var(--color-border)]">
                                    <tr>
                                        <td className="p-3">Total</td>
                                        <td className="p-3 text-right tabular-nums">{number(total('mc'))}</td>
                                        <td className="p-3 text-right tabular-nums">{number(total('kg'))}</td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </Panel>

                    {owner ? (
                        <Panel
                            title="Masuk dan keluar 14 hari terakhir"
                            fill
                            action={
                                <div className="text-muted-foreground flex gap-4 text-sm">
                                    <span className="inline-flex items-center gap-1.5">
                                        <span className="size-2.5 rounded-sm bg-emerald-600" /> Masuk{' '}
                                        <span className="text-foreground font-semibold tabular-nums">
                                            {number(owner.daily.reduce((sum, day) => sum + day.in, 0))}
                                        </span>
                                    </span>
                                    <span className="inline-flex items-center gap-1.5">
                                        <span className="size-2.5 rounded-sm bg-orange-600" /> Keluar{' '}
                                        <span className="text-foreground font-semibold tabular-nums">
                                            {number(owner.daily.reduce((sum, day) => sum + day.out, 0))}
                                        </span>
                                    </span>
                                </div>
                            }
                        >
                            <DailyChart days={owner.daily} />
                        </Panel>
                    ) : (
                        nearExpiryPanel
                    )}
                </div>

                {owner && (
                    <>
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
                            {nearExpiryPanel}

                            <Panel title="Pelanggaran FEFO terbaru">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="p-3">Waktu</th>
                                            <th className="p-3">Dus</th>
                                            <th className="p-3">Alasan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {owner.fefoViolations.length === 0 && <Empty colSpan={3}>Belum ada pelanggaran FEFO.</Empty>}
                                        {owner.fefoViolations.map((scan) => (
                                            <tr key={scan.id} className="border-t align-top">
                                                <td className="p-3 whitespace-nowrap">
                                                    {formatDateTime(scan.created_at)}
                                                    <div className="text-muted-foreground">{scan.scanned_by.name}</div>
                                                </td>
                                                <td className="p-3">
                                                    <BoxLink id={scan.box.id} code={scan.box.qr_code} />
                                                    <div>
                                                        {scan.box.product.display_name}, exp {formatDate(scan.box.expired_date)}
                                                    </div>
                                                    <Link href={route('orders.show', scan.item.order.id)} className={panelLink}>
                                                        {scan.item.order.order_number}
                                                    </Link>
                                                </td>
                                                <td className="p-3">{scan.fefo_reason ?? '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </Panel>
                        </div>

                        <Panel
                            title="Revisi dan pembatalan terbaru"
                            action={
                                <Link href={route('activity-logs.index')} className={panelLink}>
                                    Semua log
                                </Link>
                            }
                        >
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3">Waktu</th>
                                        <th className="p-3">Aksi</th>
                                        <th className="p-3">Dus / order</th>
                                        <th className="p-3">Alasan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {owner.adminActions.length === 0 && <Empty colSpan={4}>Belum ada revisi atau pembatalan.</Empty>}
                                    {owner.adminActions.map((log) => (
                                        <tr key={log.id} className="border-t align-top">
                                            <td className="p-3 whitespace-nowrap">
                                                {formatDateTime(log.created_at)}
                                                <div className="text-muted-foreground">{log.user ?? '-'}</div>
                                            </td>
                                            <td className="p-3">
                                                <Badge variant={ACTION_BADGE[log.action] ?? 'neutral'} className="whitespace-nowrap">
                                                    {ACTION_LABELS[log.action] ?? log.action}
                                                </Badge>
                                            </td>
                                            <td className="p-3">
                                                {log.box_id && log.subject ? (
                                                    <BoxLink id={log.box_id} code={log.subject} />
                                                ) : (
                                                    <span className="font-mono text-xs">{log.subject ?? '-'}</span>
                                                )}
                                            </td>
                                            <td className="p-3">{log.reason ?? '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Panel>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
