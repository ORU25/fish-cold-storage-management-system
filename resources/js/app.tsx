import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// Back/forward makes Inertia restore the page snapshot from browser history without asking the server,
// which can show an outdated order or stock status. Once that snapshot is shown, fetch fresh data for it.
let restoredFromHistory = false;
window.addEventListener('popstate', () => {
    restoredFromHistory = true;
});
// A popstate Inertia ignores (e.g. a #hash change) must not trigger a reload on the next normal visit.
router.on('before', () => {
    restoredFromHistory = false;
});
router.on('navigate', () => {
    if (restoredFromHistory) {
        restoredFromHistory = false;
        router.reload();
    }
});

// This will set light / dark mode on load...
initializeTheme();
