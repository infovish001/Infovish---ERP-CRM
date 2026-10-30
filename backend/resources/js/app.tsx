import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import Foundation from './pages/Foundation';
import Login from './pages/Login';
import Challenge from './pages/Challenge';
import ConfirmPassword from './pages/ConfirmPassword';
import Security from './pages/Security';
import Workspace from './pages/Workspace';
import MasterData from './pages/MasterData';
import CRM from './pages/CRM';
const pages = {
    Foundation,
    Login,
    Challenge,
    ConfirmPassword,
    Security,
    Workspace,
    MasterData,
    CRM,
};
void createInertiaApp({
    title: (title) => `${title} · Infovish ERP`,
    resolve: (name) => {
        if (!(name in pages)) throw new Error('Unknown application page');
        return pages[name as keyof typeof pages];
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
