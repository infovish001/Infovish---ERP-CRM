import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import Foundation from './pages/Foundation';
void createInertiaApp({
    title: (title) => `${title} · Infovish ERP`,
    resolve: (name) => {
        if (name !== 'Foundation') throw new Error('Unknown application page');
        return Foundation;
    },
    setup({ el, App, props }) { createRoot(el).render(<App {...props} />); },
});
