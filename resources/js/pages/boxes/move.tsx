import Heading from '@/components/heading';
import { ScanInput, type ScanFeedback } from '@/components/scan-input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pindah Lokasi', href: '/box-moves' }];
const selectClass = 'border-input bg-background h-12 w-full rounded-md border px-3 text-base';

/**
 * Bulk move (rancangan 4.7): pick the destination once, then every scanned box (hardware scanner or phone camera) moves there.
 */
export default function BoxMove({ locations }: { locations: { id: string; name: string }[] }) {
    const [locationId, setLocationId] = useState('');
    const [processing, setProcessing] = useState(false);
    const [feedback, setFeedback] = useState<ScanFeedback | null>(null);
    // Only this session's moves; the full record is in each box's history.
    const [moved, setMoved] = useState<{ code: string; location: string; at: number }[]>([]);
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
                onSuccess: () => {
                    const upper = code.toUpperCase();
                    setFeedback({ id: Date.now(), type: 'success', message: `${upper} dipindah ke ${location.name}` });
                    setMoved((current) => [{ code: upper, location: location.name, at: Date.now() }, ...current]);
                },
                onError: (errors) =>
                    setFeedback({ id: Date.now(), type: 'error', message: errors.code ?? Object.values(errors)[0] ?? 'Gagal memindah' }),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pindah Lokasi" />
            <div className="mx-auto grid w-full max-w-3xl gap-6 p-4">
                <Heading
                    title="Pindah Lokasi"
                    description="Pilih lokasi tujuan, lalu scan dus satu per satu. Setiap dus langsung dipindah dan tercatat di riwayatnya."
                />

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

                <ScanInput
                    onScan={scan}
                    processing={processing}
                    feedback={feedback}
                    lockedMessage={location ? undefined : 'Pilih lokasi tujuan dulu'}
                />

                <section>
                    <h2 className="mb-3 text-lg font-semibold">Dipindah di sesi ini ({moved.length})</h2>
                    {moved.length === 0 ? (
                        <p className="text-muted-foreground rounded-lg border p-4 text-sm">Belum ada dus dipindah.</p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {moved.map((item) => (
                                <li key={`${item.code}-${item.at}`} className="flex items-center gap-3 p-3 text-sm">
                                    <span className="font-mono font-semibold">{item.code}</span>
                                    <span className="text-muted-foreground flex-1">→ {item.location}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
