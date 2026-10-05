import { ConfirmDialog, type Confirmation } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { ScanInput, type ScanFeedback } from '@/components/scan-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { MapPin, RefreshCw, Undo2 } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pindah Lokasi', href: '/box-moves' }];
const selectClass = 'border-input bg-background h-12 w-full rounded-md border pl-3 pr-10 text-base';

interface RecentMove {
    id: number;
    is_cancellation: boolean;
    code: string;
    product: string;
    from: string;
    to: string;
    created_at: string;
    can_cancel: boolean;
}

/**
 * Bulk move (rancangan 4.7): pick the destination once, then every scanned box (hardware scanner or phone camera) moves there.
 * Each scan shows where the box came from, and a wrong scan is undone from today's list.
 */
export default function BoxMove({ locations, recentMoves }: { locations: { id: string; name: string }[]; recentMoves: RecentMove[] }) {
    const [locationId, setLocationId] = useState('');
    const [confirmation, setConfirmation] = useState<Confirmation | null>(null);
    const [processing, setProcessing] = useState(false);
    const [feedback, setFeedback] = useState<ScanFeedback | null>(null);
    const location = locations.find((item) => item.id === locationId);

    const scan = (code: string) => {
        if (!location) {
            return;
        }
        setProcessing(true);
        router.post(
            route('box-moves.store'),
            { code, location_id: location.id },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    const move = (page.props.recentMoves as RecentMove[])[0];
                    setFeedback({ id: Date.now(), type: 'success', message: `${move.code} · ${move.product}: ${move.from} → ${move.to}` });
                },
                onError: (errors) =>
                    setFeedback({ id: Date.now(), type: 'error', message: errors.code ?? Object.values(errors)[0] ?? 'Gagal memindah' }),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const cancel = (move: RecentMove) =>
        setConfirmation({
            title: `Batalkan pindahan ${move.code}?`,
            description: `Dus dikembalikan ke ${move.from}.`,
            confirmLabel: 'Batalkan pindahan',
            variant: 'destructive',
            onConfirm: () => cancelMove(move),
        });

    const cancelMove = (move: RecentMove) => {
        setProcessing(true);
        router.post(
            route('box-moves.cancel', move.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setFeedback({ id: Date.now(), type: 'warning', message: `${move.code} dikembalikan ke ${move.from}` }),
                onError: (errors) => setFeedback({ id: Date.now(), type: 'error', message: Object.values(errors)[0] ?? 'Gagal membatalkan' }),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pindah Lokasi" />
            <div className="mx-auto grid w-full max-w-3xl grid-cols-1 gap-6 p-4">
                <Heading
                    title="Pindah Lokasi"
                    description="Pilih lokasi tujuan, lalu scan dus satu per satu. Setiap dus langsung dipindah dan tercatat di riwayatnya. Salah scan bisa dibatalkan dari daftar di bawah."
                />

                {location ? (
                    <div className="bg-primary text-primary-foreground sticky top-0 z-10 flex items-center gap-3 rounded-lg p-4">
                        <MapPin className="size-6 shrink-0" />
                        <div className="flex-1">
                            <div className="text-sm opacity-80">Lokasi tujuan</div>
                            <div className="text-2xl font-bold">{location.name}</div>
                        </div>
                        <Button variant="secondary" onClick={() => setLocationId('')}>
                            <RefreshCw /> Ganti
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-2">
                        <Label htmlFor="location_id">Lokasi tujuan</Label>
                        <select id="location_id" className={selectClass} value={locationId} onChange={(e) => setLocationId(e.target.value)}>
                            <option value="">Pilih lokasi</option>
                            {locations.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                <ScanInput
                    onScan={scan}
                    processing={processing}
                    feedback={feedback}
                    lockedMessage={location ? undefined : 'Pilih lokasi tujuan dulu'}
                />

                <section>
                    <h2 className="mb-3 text-lg font-semibold">
                        Pindahan Anda hari ini ({recentMoves.filter((move) => !move.is_cancellation).length})
                    </h2>
                    {recentMoves.length === 0 ? (
                        <p className="text-muted-foreground rounded-lg border p-4 text-sm">Belum ada dus dipindah.</p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {recentMoves.map((move) => (
                                <li key={move.id} className="flex flex-wrap items-center gap-3 p-3 text-sm">
                                    <div className="min-w-0 flex-1">
                                        <div>
                                            <span className="font-mono font-semibold">{move.code}</span>{' '}
                                            <span className="text-muted-foreground">{move.product}</span>
                                        </div>
                                        <div>
                                            {move.from} → <span className="font-semibold">{move.to}</span>
                                            <span className="text-muted-foreground ml-2 text-xs">{formatDateTime(move.created_at)}</span>
                                        </div>
                                    </div>
                                    {move.is_cancellation ? (
                                        <Badge variant="warning">Pembatalan</Badge>
                                    ) : (
                                        move.can_cancel && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="text-red-700 hover:text-red-800"
                                                disabled={processing}
                                                onClick={() => cancel(move)}
                                            >
                                                <Undo2 /> Batalkan
                                            </Button>
                                        )
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
            <ConfirmDialog confirmation={confirmation} onClose={() => setConfirmation(null)} />
        </AppLayout>
    );
}
