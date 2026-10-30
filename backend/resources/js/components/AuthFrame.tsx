import type { ReactNode } from 'react';

export default function AuthFrame({ title, children }: { title: string; children: ReactNode }) {
    return (
        <main className="auth-page">
            <a className="auth-brand" href="/">
                infovish <small>ERP</small>
            </a>
            <section className="panel auth-panel">
                <p className="eyebrow">YOUR WORKSPACE</p>
                <h1>{title}</h1>
                {children}
            </section>
            <p className="auth-footer">Infovish Systems</p>
        </main>
    );
}
