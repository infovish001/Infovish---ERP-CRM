import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
type Readiness = { status: string; checks: Record<string, string> };
export function FoundationShell({ readiness }: { readiness: Readiness }) {
    const [dark, setDark] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    function refresh() {
        setError('');
        router.reload({
            only: ['readiness'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
            onError: () => setError('Unable to refresh. Try again.'),
        });
    }
    return (
        <div className={`workspace ${dark ? 'dark' : ''}`}>
            <a className="skip-link" href="#main">
                Skip to content
            </a>
            <aside
                className={`sidebar ${menuOpen ? 'is-open' : ''}`}
                aria-label="Workspace navigation"
            >
                <a className="brand" href="/">
                    <span className="brand-mark" aria-hidden="true">
                        ▥
                    </span>{' '}
                    infovish <small>ERP</small>
                </a>
                <div className="workspace-label">
                    <span className="workspace-avatar">IS</span>
                    <div>
                        Infovish Systems<small>Foundation workspace</small>
                    </div>
                </div>
                <p className="nav-label">WORKSPACE</p>
                <nav>
                    <a className="nav-active" href="/" aria-current="page">
                        <span aria-hidden="true">▦</span> Foundation
                    </a>
                </nav>
                <div className="sidebar-note">
                    <span className="eyebrow">MILESTONE 01</span>
                    <p>Engineering foundation</p>
                    <small>
                        Business modules will appear as they are implemented and authorized.
                    </small>
                </div>
            </aside>
            <div className="content">
                <header className="topbar">
                    <button
                        className="menu-toggle"
                        onClick={() => setMenuOpen(!menuOpen)}
                        aria-expanded={menuOpen}
                        aria-label="Toggle navigation"
                    >
                        ☰
                    </button>
                    <div className="breadcrumb">
                        Infovish ERP <span>/</span> <strong>Foundation</strong>
                    </div>
                    <div className="header-tools">
                        <span className="search-placeholder">
                            Global search available in a later milestone
                        </span>
                        <button
                            className="theme-button"
                            onClick={() => setDark(!dark)}
                            aria-label="Toggle dark theme"
                            aria-pressed={dark}
                        >
                            {dark ? 'Light' : 'Dark'}
                        </button>
                        <span className="local-badge">Local development</span>
                    </div>
                </header>
                <main id="main">
                    <div className="page-heading">
                        <div>
                            <p className="eyebrow">PLATFORM FOUNDATION</p>
                            <h1>A dependable place to begin.</h1>
                            <p>Verify the services behind your Infovish workspace.</p>
                        </div>
                        <button className="primary-button" onClick={refresh} disabled={loading}>
                            {loading ? 'Checking…' : 'Refresh checks'}
                        </button>
                    </div>
                    <section className="status-banner" aria-labelledby="status-title">
                        <div className="status-symbol" aria-hidden="true">
                            ◈
                        </div>
                        <div>
                            <p className="eyebrow">ENVIRONMENT STATUS</p>
                            <h2 id="status-title">
                                {readiness.status === 'ready'
                                    ? 'Core services are reachable'
                                    : 'Some services need attention'}
                            </h2>
                            <p>
                                These checks verify connectivity. Completion also requires
                                migrations, queue processing and automated tests.
                            </p>
                        </div>
                    </section>
                    <section className="panel" aria-labelledby="services-title">
                        <div className="panel-heading">
                            <div>
                                <h2 id="services-title">Service readiness</h2>
                                <p>Live results from the application server</p>
                            </div>
                            <span className="muted-label">M01</span>
                        </div>
                        <div role="status" aria-live="polite">
                            {Object.entries(readiness.checks).map(([name, status]) => (
                                <div className="service-row" key={name}>
                                    <div>
                                        <h3>{name === 'database' ? 'PostgreSQL' : 'Redis'}</h3>
                                        <p>
                                            {name === 'database'
                                                ? 'Platform database connection'
                                                : 'Cache and queue transport connection'}
                                        </p>
                                    </div>
                                    <span
                                        className={`status-pill ${status === 'ready' ? 'ready' : 'unavailable'}`}
                                    >
                                        {status === 'ready' ? 'Reachable' : 'Unavailable'}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </section>
                    {error && <p role="alert">{error}</p>}
                    <section className="scope-note">
                        <h2>Foundation scope</h2>
                        <p>
                            Identity, company and branch switching, notifications and business
                            workflows are scheduled for later milestones. This local verification
                            screen contains no tenant business data.
                        </p>
                    </section>
                    <footer>
                        Infovish Systems <span>•</span> Engineering foundation <span>•</span>{' '}
                        Production readiness has not been established.
                    </footer>
                </main>
            </div>
        </div>
    );
}
export default function Foundation({ readiness }: { readiness: Readiness }) {
    return (
        <>
            <Head title="Foundation" />
            <FoundationShell readiness={readiness} />
        </>
    );
}
