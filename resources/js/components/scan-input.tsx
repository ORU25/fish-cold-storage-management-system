import { cn } from '@/lib/utils';
import { CheckCircle2, ScanLine, TriangleAlert, WifiOff, XCircle } from 'lucide-react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

export type ScanFeedbackType = 'success' | 'warning' | 'error';

export interface ScanFeedback {
    /** Changes on every scan so the same message twice still beeps again. */
    id: number;
    type: ScanFeedbackType;
    message: string;
}

const TONES: Record<ScanFeedbackType, { frequency: number; duration: number; repeat: number }> = {
    success: { frequency: 1200, duration: 0.12, repeat: 1 },
    warning: { frequency: 700, duration: 0.18, repeat: 2 },
    error: { frequency: 220, duration: 0.45, repeat: 1 },
};

let audioContext: AudioContext | null = null;

/** Short beep via Web Audio, no sound files needed. Staff often do not look at the screen (rancangan 7). */
function beep(type: ScanFeedbackType) {
    try {
        audioContext ??= new AudioContext();
        const { frequency, duration, repeat } = TONES[type];
        for (let i = 0; i < repeat; i++) {
            const start = audioContext.currentTime + i * (duration + 0.08);
            const oscillator = audioContext.createOscillator();
            const gain = audioContext.createGain();
            oscillator.type = type === 'error' ? 'square' : 'sine';
            oscillator.frequency.value = frequency;
            gain.gain.value = 0.2;
            oscillator.connect(gain).connect(audioContext.destination);
            oscillator.start(start);
            oscillator.stop(start + duration);
        }
    } catch {
        // Audio is a nice-to-have; the colored banner still shows the result.
    }
}

function useOnline() {
    const [online, setOnline] = useState(typeof navigator === 'undefined' ? true : navigator.onLine);
    useEffect(() => {
        const update = () => setOnline(navigator.onLine);
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
        return () => {
            window.removeEventListener('online', update);
            window.removeEventListener('offline', update);
        };
    }, []);
    return online;
}

const STYLES: Record<ScanFeedbackType, { className: string; icon: typeof CheckCircle2 }> = {
    success: { className: 'border-green-600 bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200', icon: CheckCircle2 },
    warning: { className: 'border-amber-500 bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-200', icon: TriangleAlert },
    error: { className: 'border-red-600 bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200', icon: XCircle },
};

/**
 * Shared scan field (PRD 5.12): works with a keyboard-mode scanner (types the code then Enter),
 * stays focused, locks while a scan is processing and refuses scans while offline.
 */
export function ScanInput({ onScan, processing, feedback }: { onScan: (code: string) => void; processing: boolean; feedback: ScanFeedback | null }) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [code, setCode] = useState('');
    const online = useOnline();
    const disabled = processing || !online;

    useEffect(() => {
        if (feedback) {
            beep(feedback.type);
        }
    }, [feedback]);

    useEffect(() => {
        if (!disabled) {
            inputRef.current?.focus();
        }
    }, [disabled, feedback]);

    // Tapping anywhere that is not another form control brings focus back to the scan field.
    useEffect(() => {
        const refocus = (event: PointerEvent) => {
            if (!(event.target as HTMLElement).closest('input, select, textarea, button, a, [role="combobox"], [role="dialog"]')) {
                inputRef.current?.focus();
            }
        };
        document.addEventListener('pointerup', refocus);
        return () => document.removeEventListener('pointerup', refocus);
    }, []);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const value = code.trim();
        if (!value || disabled) {
            return;
        }
        setCode('');
        onScan(value);
    };

    const style = feedback ? STYLES[feedback.type] : null;

    return (
        <div className="grid gap-3">
            {!online && (
                <div className="flex items-center gap-2 rounded-lg border-2 border-red-600 bg-red-50 p-3 font-medium text-red-800 dark:bg-red-950 dark:text-red-200">
                    <WifiOff className="size-5 shrink-0" />
                    Koneksi terputus. Scan tidak diterima sampai jaringan kembali.
                </div>
            )}
            <form onSubmit={submit} className="relative">
                <ScanLine className="text-muted-foreground absolute top-1/2 left-4 size-6 -translate-y-1/2" />
                <input
                    ref={inputRef}
                    value={code}
                    onChange={(e) => setCode(e.target.value)}
                    disabled={disabled}
                    autoFocus
                    autoComplete="off"
                    autoCapitalize="characters"
                    spellCheck={false}
                    enterKeyHint="go"
                    placeholder={processing ? 'Memproses…' : 'Scan atau ketik kode QR lalu Enter'}
                    aria-label="Kode QR"
                    className="border-input bg-background focus-visible:ring-ring h-16 w-full rounded-xl border-2 pr-4 pl-14 font-mono text-xl uppercase focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60"
                />
            </form>
            {feedback && style && (
                <div
                    role="status"
                    aria-live="assertive"
                    className={cn('flex items-center gap-3 rounded-xl border-2 p-4 text-lg font-semibold', style.className)}
                >
                    <style.icon className="size-7 shrink-0" />
                    {feedback.message}
                </div>
            )}
        </div>
    );
}
