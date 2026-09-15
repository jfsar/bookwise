import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

import ErrorBoundary from '@/Components/ErrorBoundary';

import '../css/app.css';

const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

createInertiaApp({
    title: (title) => (title ? `${title} — BookWise` : 'BookWise'),
    resolve: (name) => pages[`./Pages/${name}.jsx`],
    setup({ el, App, props }) {
        createRoot(el).render(
            <ErrorBoundary name="app">
                <App {...props} />
            </ErrorBoundary>
        );
    },
    progress: {
        color: '#006d32',
    },
});