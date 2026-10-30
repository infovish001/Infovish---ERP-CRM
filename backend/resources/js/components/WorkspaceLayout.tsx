import { Head, Link } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

export default function WorkspaceLayout({
    title,
    tenant,
    user,
    children,
    section = 'master-data',
}: {
    title: string;
    tenant: string;
    user: string;
    children: ReactNode;
    section?: 'master-data' | 'crm';
}) {
    const [dark, setDark] = useState(false);
    const [menu, setMenu] = useState(false);
    return (
        <div className={`workspace ${dark ? 'dark' : ''}`}>
            <Head title={title} />
            <a className="skip-link" href="#main">
                Skip to content
            </a>
            <aside className={`sidebar ${menu ? 'is-open' : ''}`} aria-label="Workspace navigation">
                <Link className="brand" href="/workspace">
                    <span className="brand-mark" aria-hidden="true">
                        ▥
                    </span>{' '}
                    infovish <small>ERP</small>
                </Link>
                <div className="workspace-label">
                    <span className="workspace-avatar">{tenant.slice(0, 2).toUpperCase()}</span>
                    <div>
                        {tenant}
                        <small>Workspace</small>
                    </div>
                </div>
                <p className="nav-label">WORKSPACE</p>
                <nav className="workspace-nav">
                    <Link href="/workspace">Organization</Link>
                    <Link
                        href="/workspace/master-data"
                        className={section === 'master-data' ? 'nav-active' : ''}
                        aria-current={section === 'master-data' ? 'page' : undefined}
                    >
                        Contacts &amp; catalog
                    </Link>
                    <Link
                        href="/workspace/crm"
                        className={section === 'crm' ? 'nav-active' : ''}
                        aria-current={section === 'crm' ? 'page' : undefined}
                    >
                        CRM
                    </Link>
                    <Link href="/account/security">Account security</Link>
                </nav>
                <div className="sidebar-note">
                    <p>Shared business records</p>
                    <small>
                        Manage the contacts and product definitions your team will use across
                        Infovish.
                    </small>
                </div>
            </aside>
            <div className="content">
                <header className="topbar">
                    <button
                        className="theme-button menu-toggle"
                        onClick={() => setMenu(!menu)}
                        aria-expanded={menu}
                        aria-label="Toggle navigation"
                    >
                        ☰
                    </button>
                    <div className="breadcrumb">
                        Infovish ERP <span>›</span>
                        <strong>{title}</strong>
                    </div>
                    <div className="header-tools">
                        <button
                            className="theme-button"
                            onClick={() => setDark(!dark)}
                            aria-label="Toggle theme"
                        >
                            {dark ? 'Light' : 'Dark'}
                        </button>
                        <span>{user}</span>
                        <Link href="/logout" method="post" as="button" className="text-button">
                            Sign out
                        </Link>
                    </div>
                </header>
                <main id="main">{children}</main>
            </div>
        </div>
    );
}
