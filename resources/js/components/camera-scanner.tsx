import type { BarcodeDetector as Detector } from 'barcode-detector/ponyfill';
import { CameraOff } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const SCAN_INTERVAL_MS = 250;
/** A box still in front of the camera is read many times a second; the same code within this window counts once. */
const REPEAT_WINDOW_MS = 2000;
/**
 * After each scan the camera reads nothing for this long, counted from when the scan has finished processing, so the
 * worker can move to the next box instead of the neighbouring sticker being picked up straight away. Tune after the trial.
 */
const SCAN_COOLDOWN_MS = 1500;

/**
 * The browser's own QR reader (Chrome Android) when there is one, otherwise the zxing-wasm ponyfill with the same API,
 * loaded only on browsers that need it (iPhone Safari, desktop Chrome on Windows).
 * ponytail: the ponyfill fetches its wasm from the library's default CDN; self-host it if the warehouse has no internet.
 */
async function createDetector(): Promise<Detector> {
    const Native = (window as unknown as { BarcodeDetector?: typeof Detector }).BarcodeDetector;
    if (Native && (await Native.getSupportedFormats()).includes('qr_code')) {
        return new Native({ formats: ['qr_code'] });
    }
    const { BarcodeDetector } = await import('barcode-detector/ponyfill');
    return new BarcodeDetector({ formats: ['qr_code'] });
}

function errorMessage(error: unknown): string {
    if (!window.isSecureContext) {
        return 'Kamera hanya bisa dipakai lewat HTTPS.';
    }
    const name = error instanceof DOMException ? error.name : '';
    if (name === 'NotAllowedError') {
        return 'Izin kamera ditolak. Izinkan kamera untuk situs ini di pengaturan browser.';
    }
    if (name === 'NotFoundError' || name === 'OverconstrainedError') {
        return 'Kamera tidak ditemukan di perangkat ini.';
    }
    return 'Kamera tidak bisa dibuka. Tutup aplikasi lain yang memakai kamera lalu coba lagi.';
}

/**
 * Rear camera preview that reads QR stickers and hands each code to onScan, like a keyboard-mode scanner would.
 * While paused (a scan is processing, a FEFO prompt is open, the order is complete) and for SCAN_COOLDOWN_MS afterwards,
 * nothing is read. Decoding runs on the device; the server only sees one request per box, like a hardware scanner.
 */
export function CameraScanner({ onScan, paused }: { onScan: (code: string) => void; paused: boolean }) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const [error, setError] = useState<string | null>(null);
    const [starting, setStarting] = useState(true);
    const [cooling, setCooling] = useState(false);
    // Read inside the scan loop without restarting the camera on every render.
    const latest = useRef({ onScan, paused });
    latest.current = { onScan, paused };

    useEffect(() => {
        let stream: MediaStream | null = null;
        let timer: number | undefined;
        let stopped = false;
        let last = { code: '', at: 0 };
        let resumeAt = 0;

        (async () => {
            try {
                if (!navigator.mediaDevices?.getUserMedia) {
                    throw new Error('getUserMedia unavailable');
                }
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                const detector = await createDetector();
                const video = videoRef.current;
                if (stopped || !video) {
                    return;
                }
                video.srcObject = stream;
                await video.play();
                setStarting(false);

                const tick = async () => {
                    if (stopped) {
                        return;
                    }
                    if (latest.current.paused) {
                        resumeAt = Date.now() + SCAN_COOLDOWN_MS;
                    }
                    setCooling(!latest.current.paused && Date.now() < resumeAt);
                    if (Date.now() >= resumeAt && video.readyState >= video.HAVE_CURRENT_DATA) {
                        try {
                            const code = (await detector.detect(video))[0]?.rawValue.trim();
                            const now = Date.now();
                            if (code && !latest.current.paused && (code !== last.code || now - last.at > REPEAT_WINDOW_MS)) {
                                last = { code, at: now };
                                resumeAt = now + SCAN_COOLDOWN_MS;
                                latest.current.onScan(code);
                            }
                        } catch {
                            // A frame that can not be decoded is simply skipped.
                        }
                    }
                    timer = window.setTimeout(tick, SCAN_INTERVAL_MS);
                };
                tick();
            } catch (e) {
                stream?.getTracks().forEach((track) => track.stop());
                if (!stopped) {
                    setError(errorMessage(e));
                    setStarting(false);
                }
            }
        })();

        return () => {
            stopped = true;
            window.clearTimeout(timer);
            stream?.getTracks().forEach((track) => track.stop());
        };
    }, []);

    if (error) {
        return (
            <div className="flex items-center gap-2 rounded-lg border-2 border-red-600 bg-red-50 p-3 font-medium text-red-800">
                <CameraOff className="size-5 shrink-0" />
                {error}
            </div>
        );
    }

    return (
        <div className="relative overflow-hidden rounded-xl bg-black">
            <video ref={videoRef} playsInline muted className="aspect-[4/3] w-full object-cover" />
            {/* Aiming frame; the whole image is read, this only helps the worker point the phone. */}
            <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                <div className={`size-48 rounded-2xl border-4 ${paused || cooling ? 'border-white/30' : 'border-white/80'}`} />
            </div>
            {(starting || paused || cooling) && (
                <div className="absolute inset-x-0 bottom-0 bg-black/60 p-2 text-center text-sm font-medium text-white">
                    {starting ? 'Membuka kamera…' : paused ? 'Kamera dijeda' : 'Siap scan berikutnya…'}
                </div>
            )}
        </div>
    );
}
