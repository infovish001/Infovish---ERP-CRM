import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import WorkspaceLayout from '../components/WorkspaceLayout';
import { requestJson, RequestError } from '../lib/request';
import SetupForms from '../features/finance/SetupForms';
import JournalForm from '../features/finance/JournalForm';
import DocumentForm from '../features/finance/DocumentForm';
import PaymentForm from '../features/finance/PaymentForm';
import StatementForm from '../features/finance/StatementForm';
import ReportsView from '../features/finance/ReportsView';
import type { Setup, Kind, Page, Detail, Entry } from '../features/finance/types';
import { today } from '../features/finance/types';
type Tab = Kind | 'reports' | 'setup';
type Props = {
    tenant: { name: string };
    user: { name: string; role: string };
    companies: { id: string; name: string; base_currency: string }[];
    initialCompany: string | null;
    available: boolean;
    canManage: boolean;
};
const empty: Setup = { accounts: [], periods: [], settings: null, orders: [], suppliers: [] };
const labels: Record<Tab, string> = {
    drafts: 'Journal drafts',
    journals: 'Posted journals',
    invoices: 'Invoices',
    bills: 'Expense bills',
    payments: 'Payments',
    statements: 'Bank reconciliation',
    reports: 'Reports',
    setup: 'Finance setup',
};
export default function Finance({
    tenant,
    user,
    companies,
    initialCompany,
    available,
    canManage,
}: Props) {
    const [company, setCompany] = useState(
            companies.some((c) => c.id === initialCompany)
                ? initialCompany!
                : (companies[0]?.id ?? ''),
        ),
        [tab, setTab] = useState<Tab>('journals'),
        [setup, setSetup] = useState<Setup>(empty),
        [setupLoading, setSetupLoading] = useState(true),
        [revision, setRevision] = useState(0),
        [page, setPage] = useState<Page>(),
        [pageNumber, setPageNumber] = useState(1),
        [query, setQuery] = useState(''),
        [search, setSearch] = useState(''),
        [loading, setLoading] = useState(false),
        [selected, setSelected] = useState<string>(),
        [detail, setDetail] = useState<Detail>(),
        [editor, setEditor] = useState<{ kind: Kind; record?: Entry; document?: Entry }>(),
        [busy, setBusy] = useState(false),
        [error, setError] = useState(''),
        [fields, setFields] = useState<string[]>([]),
        [notice, setNotice] = useState(''),
        [reason, setReason] = useState(''),
        [actionDate, setActionDate] = useState(today());
    const keys = useRef<Record<string, string>>({});
    const ready = available && Boolean(company);
    const collection = tab !== 'reports' && tab !== 'setup';
    function fail(e: unknown) {
        setError(e instanceof Error ? e.message : 'The operation could not be completed.');
        setFields(e instanceof RequestError ? Object.values(e.errors).flat() : []);
    }
    useEffect(() => {
        if (!ready) return;
        let current = true;
        setSetupLoading(true);
        void requestJson<Setup>(`/api/v1/finance/setup?company_id=${company}`)
            .then((r) => {
                if (current) setSetup(r);
            })
            .catch((e) => {
                if (current) fail(e);
            })
            .finally(() => {
                if (current) setSetupLoading(false);
            });
        return () => {
            current = false;
        };
    }, [ready, company, revision]);
    useEffect(() => {
        if (!ready || !collection) return;
        let current = true;
        setLoading(true);
        setPage(undefined);
        void requestJson<Page>(
            `/api/v1/finance/${tab}?company_id=${company}&page=${pageNumber}&q=${encodeURIComponent(search)}`,
        )
            .then((r) => {
                if (current) setPage(r);
            })
            .catch((e) => {
                if (current) fail(e);
            })
            .finally(() => {
                if (current) setLoading(false);
            });
        return () => {
            current = false;
        };
    }, [ready, company, tab, collection, pageNumber, search, revision]);
    useEffect(() => {
        if (!ready || !selected || !collection) {
            setDetail(undefined);
            return;
        }
        let current = true;
        setDetail(undefined);
        void requestJson<Detail>(`/api/v1/finance/${tab}/${selected}?company_id=${company}`)
            .then((r) => {
                if (current) setDetail(r);
            })
            .catch((e) => {
                if (current) fail(e);
            });
        return () => {
            current = false;
        };
    }, [ready, company, tab, collection, selected, revision]);
    function clear() {
        setEditor(undefined);
        setSelected(undefined);
        setDetail(undefined);
        setReason('');
        setError('');
        setFields([]);
        setNotice('');
        keys.current = {};
    }
    function switchTab(t: Tab) {
        clear();
        setTab(t);
        setPageNumber(1);
        setSearch('');
        setQuery('');
    }
    function stable(intent: string) {
        return keys.current[intent] ?? (keys.current[intent] = crypto.randomUUID());
    }
    async function write(
        path: string,
        data: unknown,
        key?: string,
        method = 'POST',
        resultTab?: Kind,
    ) {
        setBusy(true);
        setError('');
        setFields([]);
        setNotice('');
        try {
            const result = await requestJson<{ id: string }>(
                '/api/v1/finance' + path,
                method,
                { ...(data as object), company_id: company },
                key ? { 'Idempotency-Key': key } : {},
            );
            setEditor(undefined);
            setReason('');
            setRevision((r) => r + 1);
            if (resultTab) {
                setTab(resultTab);
                setSelected(result.id);
                setPageNumber(1);
            } else if (
                collection &&
                !path.startsWith('/accounts') &&
                !path.startsWith('/periods') &&
                path !== '/settings'
            )
                setSelected(result.id);
            setNotice('Finance operation recorded.');
        } catch (e) {
            fail(e);
        } finally {
            setBusy(false);
        }
    }
    function saveEditor(data: unknown, key: string) {
        if (!editor) return;
        const k = editor.kind,
            r = editor.record;
        let path =
            k === 'drafts'
                ? '/drafts'
                : k === 'statements'
                  ? '/statements'
                  : k === 'payments'
                    ? '/payments'
                    : '/documents/' + (k === 'invoices' ? 'invoice' : 'bill');
        if (r) path += '/' + r.id;
        void write(path, data, r ? undefined : key, r ? 'PUT' : 'POST', k);
    }
    async function print(kind: string) {
        if (!detail) return;
        const r = detail.record;
        setBusy(true);
        setError('');
        try {
            const token =
                document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
            const response = await fetch(`/api/v1/finance/documents/${r.id}/print`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/pdf',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Idempotency-Key': stable('print:' + r.id + ':' + kind + ':' + reason),
                },
                body: JSON.stringify({ company_id: company, kind, reason: reason || null }),
            });
            if (!response.ok) {
                const e = (await response.json()) as { message?: string };
                throw new Error(e.message ?? 'PDF issuance failed.');
            }
            const url = URL.createObjectURL(await response.blob());
            const a = document.createElement('a');
            a.href = url;
            a.download = r.number + '-' + kind + '.pdf';
            a.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            setRevision((v) => v + 1);
            setNotice('PDF issuance recorded; no external delivery was performed.');
        } catch (e) {
            fail(e);
        } finally {
            setBusy(false);
        }
    }
    const r = detail?.record;
    return (
        <WorkspaceLayout
            title="Accounting & finance"
            tenant={tenant.name}
            user={user.name}
            section="finance"
            financeVisible
        >
            <div className="page-heading">
                <div>
                    <p className="eyebrow">FINANCE</p>
                    <h1>Accounting &amp; finance</h1>
                    <p>Review the posted ledger, controlled documents and bank reconciliation.</p>
                </div>
                {ready && canManage && collection && tab !== 'journals' && (
                    <button
                        className="primary-button"
                        disabled={busy || setupLoading || (tab !== 'drafts' && !setup.settings)}
                        onClick={() => {
                            clear();
                            setEditor({ kind: tab });
                        }}
                    >
                        +{' '}
                        {tab === 'drafts'
                            ? 'New journal draft'
                            : tab === 'invoices'
                              ? 'New invoice'
                              : tab === 'bills'
                                ? 'New expense bill'
                                : tab === 'payments'
                                  ? 'Record payment'
                                  : 'New bank statement'}
                    </button>
                )}
            </div>
            <div className="master-tabs finance-tabs" role="tablist" aria-label="Finance sections">
                {(Object.keys(labels) as Tab[]).map((t) => (
                    <button
                        key={t}
                        role="tab"
                        aria-selected={tab === t}
                        className={tab === t ? 'selected' : ''}
                        onClick={() => switchTab(t)}
                    >
                        {labels[t]}
                    </button>
                ))}
            </div>
            {error && (
                <div className="form-error" role="alert">
                    <p>{error}</p>
                    {fields.length > 0 && (
                        <ul>
                            {fields.map((f, i) => (
                                <li key={i}>{f}</li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
            {notice && <p role="status">{notice}</p>}
            {!available ? (
                <section className="panel">
                    <h2>Finance is not active</h2>
                    <p>
                        Ask your owner to activate its Sales and Contacts dependencies, then
                        Finance.
                    </p>
                    <Link href="/workspace/master-data">Manage modules</Link>
                </section>
            ) : !company ? (
                <section className="panel">
                    <h2>No company available</h2>
                    <p>Ask the owner for company access.</p>
                </section>
            ) : (
                <>
                    <div className="master-toolbar">
                        <label>
                            Company
                            <select
                                value={company}
                                onChange={(e) => {
                                    clear();
                                    setCompany(e.target.value);
                                    setSetup(empty);
                                    setPageNumber(1);
                                }}
                            >
                                {companies.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name} · {c.base_currency}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {collection && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    setSearch(query);
                                    setPageNumber(1);
                                }}
                            >
                                <label className="sr-only" htmlFor="finance-search">
                                    Search Finance reference
                                </label>
                                <input
                                    id="finance-search"
                                    placeholder="Search reference…"
                                    value={query}
                                    maxLength={100}
                                    onChange={(e) => setQuery(e.target.value)}
                                />
                                <button className="secondary-button">Search</button>
                            </form>
                        )}
                    </div>
                    {setupLoading && <p role="status">Loading Finance setup…</p>}
                    {!setupLoading && !setup.settings && tab !== 'setup' && (
                        <section className="panel">
                            <h2>Finance setup is required before posting</h2>
                            <p>
                                Create reviewed accounts, an open period and explicit posting
                                mappings. No opening balances have been assumed.
                            </p>
                            <button className="secondary-button" onClick={() => switchTab('setup')}>
                                Review Finance setup
                            </button>
                        </section>
                    )}
                    {tab === 'setup' &&
                        !setupLoading &&
                        (canManage ? (
                            <SetupForms
                                key={`${company}-${revision}`}
                                setup={setup}
                                busy={busy}
                                save={(path, data, key) =>
                                    void write(
                                        path,
                                        data,
                                        key,
                                        path === '/settings' ||
                                            path.includes('/lock') ||
                                            path.startsWith('/accounts/')
                                            ? 'PUT'
                                            : 'POST',
                                    )
                                }
                            />
                        ) : (
                            <section className="panel">
                                <h2>Read-only Finance setup</h2>
                                <p>
                                    Account configuration and period changes require privileged MFA.
                                </p>
                                <ul>
                                    {setup.accounts.map((a) => (
                                        <li key={a.id}>
                                            {a.code} · {a.name} · {a.type}
                                        </li>
                                    ))}
                                </ul>
                                <ul>
                                    {setup.periods.map((p) => (
                                        <li key={p.id}>
                                            {p.name} · {p.starts_on} – {p.ends_on} ·{' '}
                                            {p.locked ? 'Locked' : 'Open'}
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    {tab === 'reports' && !setupLoading && (
                        <ReportsView
                            company={company}
                            setup={setup}
                            revision={revision}
                            error={fail}
                        />
                    )}
                    {editor && canManage && (
                        <>
                            {editor.kind === 'drafts' ? (
                                <JournalForm
                                    key={editor.record?.id ?? 'draft'}
                                    setup={setup}
                                    record={editor.record}
                                    busy={busy}
                                    save={saveEditor}
                                    close={() => setEditor(undefined)}
                                />
                            ) : editor.kind === 'invoices' || editor.kind === 'bills' ? (
                                <DocumentForm
                                    key={editor.record?.id ?? editor.kind}
                                    company={company}
                                    setup={setup}
                                    kind={editor.kind === 'invoices' ? 'invoice' : 'bill'}
                                    record={editor.record}
                                    busy={busy}
                                    save={saveEditor}
                                    close={() => setEditor(undefined)}
                                    error={fail}
                                />
                            ) : editor.kind === 'payments' ? (
                                <PaymentForm
                                    key={editor.document?.id ?? 'payment'}
                                    company={company}
                                    setup={setup}
                                    document={editor.document}
                                    busy={busy}
                                    save={saveEditor}
                                    close={() => setEditor(undefined)}
                                    error={fail}
                                />
                            ) : (
                                <StatementForm
                                    key={editor.record?.id ?? 'statement'}
                                    company={company}
                                    setup={setup}
                                    record={editor.record}
                                    busy={busy}
                                    save={saveEditor}
                                    close={() => setEditor(undefined)}
                                    error={fail}
                                />
                            )}
                        </>
                    )}
                    {collection && (
                        <section className="panel">
                            <div className="panel-heading">
                                <div>
                                    <h2>{labels[tab]}</h2>
                                    <p>
                                        {page
                                            ? `${page.total} matching records`
                                            : 'Company Finance records'}
                                    </p>
                                </div>
                            </div>
                            {loading ? (
                                <p role="status">Loading Finance records…</p>
                            ) : (
                                <div className="table-scroll">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Reference / detail</th>
                                                <th>Date</th>
                                                <th>LKR amount</th>
                                                <th>State</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {page?.data.map((e) => (
                                                <tr key={e.id}>
                                                    <td>
                                                        <strong>{e.number}</strong>
                                                        <br />
                                                        {e.snapshot?.party.name ??
                                                            e.narration ??
                                                            e.reference ??
                                                            ''}
                                                    </td>
                                                    <td>{e.date ?? e.ends_on}</td>
                                                    <td>
                                                        {e.total ?? e.amount ?? e.closing ?? '—'}
                                                        {e.outstanding != null && (
                                                            <small>
                                                                {' '}
                                                                · outstanding {e.outstanding}
                                                            </small>
                                                        )}
                                                    </td>
                                                    <td>
                                                        {e.state ??
                                                            (tab === 'payments'
                                                                ? 'recorded'
                                                                : 'posted')}
                                                    </td>
                                                    <td>
                                                        <button
                                                            className="text-button"
                                                            aria-label={`Open ${e.number}`}
                                                            onClick={() => {
                                                                setSelected(e.id);
                                                                setEditor(undefined);
                                                                setReason('');
                                                                keys.current = {};
                                                            }}
                                                        >
                                                            View
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {page?.data.length === 0 && (
                                                <tr>
                                                    <td colSpan={5}>
                                                        No matching records. Finance operations
                                                        appear here after authorized entry.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <div className="pagination">
                                <span>Page {page?.current_page ?? pageNumber}</span>
                                <button
                                    className="secondary-button"
                                    disabled={!page?.prev_page_url || loading}
                                    onClick={() => setPageNumber((p) => p - 1)}
                                >
                                    Previous
                                </button>
                                <button
                                    className="secondary-button"
                                    disabled={!page?.next_page_url || loading}
                                    onClick={() => setPageNumber((p) => p + 1)}
                                >
                                    Next
                                </button>
                            </div>
                        </section>
                    )}
                    {selected && !detail && collection && (
                        <p role="status">Loading Finance details…</p>
                    )}
                    {r && collection && (
                        <section className="panel" aria-label="Finance record details">
                            <div className="panel-heading">
                                <div>
                                    <p className="eyebrow">
                                        {r.state ?? 'posted'} · {r.kind ?? r.source_kind ?? tab}
                                    </p>
                                    <h2>{r.number}</h2>
                                </div>
                                <button
                                    className="secondary-button"
                                    onClick={() => setSelected(undefined)}
                                >
                                    Close details
                                </button>
                            </div>
                            <p>
                                {r.date ?? `${r.starts_on} – ${r.ends_on}`} ·{' '}
                                {r.narration ?? r.reference ?? r.snapshot?.party.name}
                            </p>
                            {r.snapshot && (
                                <>
                                    <h3>{r.snapshot.party.name}</h3>
                                    <p>
                                        {r.snapshot.party.address}{' '}
                                        {r.snapshot.party.tin && `· TIN ${r.snapshot.party.tin}`}
                                    </p>
                                    <p>{r.snapshot.description}</p>
                                    <dl className="sales-totals">
                                        <div>
                                            <dt>Net</dt>
                                            <dd>LKR {r.net}</dd>
                                        </div>
                                        <div>
                                            <dt>VAT</dt>
                                            <dd>LKR {r.vat}</dd>
                                        </div>
                                        <div>
                                            <dt>Total</dt>
                                            <dd>LKR {r.total}</dd>
                                        </div>
                                        {detail.outstanding != null && (
                                            <div>
                                                <dt>Outstanding</dt>
                                                <dd>LKR {detail.outstanding}</dd>
                                            </div>
                                        )}
                                    </dl>
                                    {r.snapshot.tax_invoice && (
                                        <p>VAT tax invoice · supply {r.snapshot.supply_date}</p>
                                    )}
                                </>
                            )}
                            {(detail.lines ?? r.lines) && (
                                <div className="table-scroll">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Account / reference</th>
                                                <th>Debit / signed amount</th>
                                                <th>Credit / match</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(detail.lines ?? r.lines ?? []).map((l, i) => (
                                                <tr key={l.id ?? i}>
                                                    <td>
                                                        {l.account_code ??
                                                            setup.accounts.find(
                                                                (a) => a.id === l.account_id,
                                                            )?.code ??
                                                            l.reference}{' '}
                                                        {l.account_name ?? l.description}
                                                    </td>
                                                    <td>{l.debit ?? l.amount}</td>
                                                    <td>
                                                        {l.credit ??
                                                            (l.line_id
                                                                ? 'Matched book entry'
                                                                : 'Unmatched')}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            {r.summary && (
                                <dl className="sales-totals">
                                    {Object.entries(r.summary).map(([key, value]) => (
                                        <div key={key}>
                                            <dt>{key.replaceAll('_', ' ')}</dt>
                                            <dd>{value}</dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                            {r.journal_id && (
                                <button
                                    className="text-button"
                                    onClick={() => {
                                        switchTab('journals');
                                        setSelected(r.journal_id!);
                                    }}
                                >
                                    View posted journal
                                </button>
                            )}
                            {detail.reversal_id && (
                                <button
                                    className="text-button"
                                    onClick={() => setSelected(detail.reversal_id!)}
                                >
                                    View reversal journal
                                </button>
                            )}
                            {detail.payments && (
                                <>
                                    <h3>Payment allocations</h3>
                                    <ul>
                                        {detail.payments.map((p) => (
                                            <li key={p.id}>
                                                <button
                                                    className="text-button"
                                                    onClick={() => {
                                                        switchTab('payments');
                                                        setSelected(p.id);
                                                    }}
                                                >
                                                    {p.number} · {p.date} · {p.amount}{' '}
                                                    {p.void_id ? '· voided' : ''}
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                </>
                            )}
                            {detail.void && <p>Payment voided: {detail.void.reason}</p>}
                            {canManage && (
                                <div className="record-actions">
                                    {r.state === 'draft' &&
                                        tab !== 'payments' &&
                                        tab !== 'journals' && (
                                            <button
                                                className="secondary-button"
                                                onClick={() =>
                                                    setEditor({ kind: tab as Kind, record: r })
                                                }
                                            >
                                                Edit draft
                                            </button>
                                        )}
                                    {r.state === 'draft' && tab === 'drafts' && (
                                        <button
                                            className="primary-button"
                                            disabled={busy || !setup.settings}
                                            onClick={() =>
                                                void write(
                                                    `/drafts/${r.id}/post`,
                                                    { revision: r.revision },
                                                    stable('post:' + r.id),
                                                    'POST',
                                                    'journals',
                                                )
                                            }
                                        >
                                            Post balanced journal
                                        </button>
                                    )}
                                    {r.state === 'draft' &&
                                        (tab === 'invoices' || tab === 'bills') && (
                                            <button
                                                className="primary-button"
                                                disabled={busy}
                                                onClick={() =>
                                                    void write(
                                                        `/documents/${r.id}/post`,
                                                        { revision: r.revision },
                                                        stable('post:' + r.id),
                                                    )
                                                }
                                            >
                                                Post document
                                            </button>
                                        )}
                                    {r.state === 'draft' && tab === 'statements' && (
                                        <button
                                            className="primary-button"
                                            disabled={busy}
                                            onClick={() =>
                                                void write(
                                                    `/statements/${r.id}/finalize`,
                                                    { revision: r.revision },
                                                    stable('finalize:' + r.id),
                                                )
                                            }
                                        >
                                            Finalize reconciliation
                                        </button>
                                    )}
                                    {r.state === 'posted' &&
                                        (tab === 'invoices' || tab === 'bills') &&
                                        detail.outstanding !== '0.00' && (
                                            <button
                                                className="primary-button"
                                                onClick={() =>
                                                    setEditor({
                                                        kind: 'payments',
                                                        document: {
                                                            ...r,
                                                            outstanding: detail.outstanding,
                                                        },
                                                    })
                                                }
                                            >
                                                Record receipt or payment
                                            </button>
                                        )}
                                </div>
                            )}
                            {canManage &&
                                ((tab === 'journals' &&
                                    r.source_kind === 'manual' &&
                                    !detail.reversal_id) ||
                                    (tab === 'payments' && !detail.void) ||
                                    ((tab === 'invoices' || tab === 'bills') &&
                                        r.state === 'draft')) && (
                                    <form
                                        className="sales-cancel"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            if (tab === 'journals')
                                                void write(
                                                    `/journals/${r.id}/reverse`,
                                                    { date: actionDate, reason },
                                                    stable(
                                                        'reverse:' +
                                                            r.id +
                                                            ':' +
                                                            actionDate +
                                                            ':' +
                                                            reason,
                                                    ),
                                                    'POST',
                                                    'journals',
                                                );
                                            else if (tab === 'payments')
                                                void write(
                                                    `/payments/${r.id}/void`,
                                                    { date: actionDate, reason },
                                                    stable(
                                                        'void:' +
                                                            r.id +
                                                            ':' +
                                                            actionDate +
                                                            ':' +
                                                            reason,
                                                    ),
                                                );
                                            else
                                                void write(`/documents/${r.id}/cancel`, {
                                                    revision: r.revision,
                                                    reason,
                                                });
                                        }}
                                    >
                                        <label>
                                            Correction reason
                                            <input
                                                required
                                                maxLength={500}
                                                value={reason}
                                                onChange={(e) => setReason(e.target.value)}
                                            />
                                        </label>
                                        {(tab === 'journals' || tab === 'payments') && (
                                            <label>
                                                Correction date
                                                <input
                                                    required
                                                    type="date"
                                                    value={actionDate}
                                                    onChange={(e) => setActionDate(e.target.value)}
                                                />
                                            </label>
                                        )}
                                        <button className="secondary-button" disabled={busy}>
                                            {tab === 'journals'
                                                ? 'Post opposite reversal'
                                                : tab === 'payments'
                                                  ? 'Void with opposite journal'
                                                  : 'Cancel draft document'}
                                        </button>
                                    </form>
                                )}
                            {r.kind === 'invoice' && r.state === 'posted' && canManage && (
                                <>
                                    <h3>Invoice PDF issuance</h3>
                                    <p>
                                        One original issuance; retained supplier duplicates and
                                        reasoned replacement copies are marked. This downloads a
                                        document without sending it externally.
                                    </p>
                                    <label>
                                        Replacement-copy reason
                                        <input
                                            maxLength={500}
                                            value={reason}
                                            onChange={(e) => setReason(e.target.value)}
                                        />
                                    </label>
                                    <div className="record-actions">
                                        <button
                                            className="secondary-button"
                                            disabled={busy}
                                            onClick={() => void print('original')}
                                        >
                                            Issue original PDF
                                        </button>
                                        <button
                                            className="secondary-button"
                                            disabled={busy}
                                            onClick={() => void print('duplicate')}
                                        >
                                            Retained duplicate PDF
                                        </button>
                                        <button
                                            className="secondary-button"
                                            disabled={busy || !reason.trim()}
                                            onClick={() => void print('copy')}
                                        >
                                            Download COPY ONLY
                                        </button>
                                    </div>
                                    <h3>Latest 100 issuance records</h3>
                                    <ul>
                                        {detail.prints?.map((p) => (
                                            <li key={p.id}>
                                                {p.kind} · {new Date(p.created_at).toLocaleString()}{' '}
                                                {p.reason}
                                            </li>
                                        ))}
                                    </ul>
                                </>
                            )}
                        </section>
                    )}
                </>
            )}
        </WorkspaceLayout>
    );
}
