import { useEffect, useRef } from 'react';

interface TurnstileApi {
    render: (
        container: HTMLElement,
        options: {
            sitekey: string;
            language?: string;
            size?: 'normal' | 'flexible' | 'compact';
            theme?: 'light' | 'dark' | 'auto';
            callback: (token: string) => void;
            'expired-callback': () => void;
            'error-callback': () => void;
        },
    ) => string;
    remove: (widgetId: string) => void;
}

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
let scriptPromise: Promise<void> | null = null;

/** Loads Cloudflare's script once per page; later widgets reuse it. */
function loadScript(): Promise<void> {
    scriptPromise ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT_URL;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => {
            scriptPromise = null;
            reject(new Error('Turnstile script failed to load'));
        };
        document.head.appendChild(script);
    });
    return scriptPromise;
}

/**
 * Cloudflare Turnstile widget. Reports a token when the check passes and an empty string when it expires or errors.
 * A token is single use: remount the widget (change its `key`) after a failed submit to get a new one.
 */
export function Turnstile({ siteKey, onToken }: { siteKey: string; onToken: (token: string) => void }) {
    const containerRef = useRef<HTMLDivElement>(null);
    const onTokenRef = useRef(onToken);
    onTokenRef.current = onToken;

    useEffect(() => {
        let widgetId: string | null = null;
        let cancelled = false;

        loadScript()
            .then(() => {
                if (cancelled || !containerRef.current || !window.turnstile) {
                    return;
                }
                widgetId = window.turnstile.render(containerRef.current, {
                    sitekey: siteKey,
                    language: 'id',
                    size: 'flexible',
                    theme: 'light',
                    callback: (token) => onTokenRef.current(token),
                    'expired-callback': () => onTokenRef.current(''),
                    'error-callback': () => onTokenRef.current(''),
                });
            })
            .catch(() => onTokenRef.current(''));

        return () => {
            cancelled = true;
            if (widgetId && window.turnstile) {
                window.turnstile.remove(widgetId);
            }
        };
    }, [siteKey]);

    return <div ref={containerRef} className="min-h-[65px]" />;
}
