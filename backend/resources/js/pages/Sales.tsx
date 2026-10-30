import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import WorkspaceLayout from '../components/WorkspaceLayout';
import { requestJson, RequestError } from '../lib/request';
import QuoteEditor from '../features/sales/QuoteEditor';
import ProfileEditor from '../features/sales/ProfileEditor';
import type { Company, Detail, Document, Page, Setup } from '../features/sales/types';

type Props = {
    tenant: { name: string };
    user: { name: string };
    companies: Company[];
    initialCompany: string | null;
    available: boolean;
    canManage: boolean;
};
const emptySetup: Setup = {
    profile: null,
    customers: [],
    products: [],
    opportunities: [],
    rule_version: '',
};

export default function Sales({
    tenant,
    user,
    companies,
    initialCompany,
    available,
    canManage,
}: Props) {
    const [company, setCompany] = useState(
        companies.some((c) => c.id === initialCompany) ? initialCompany! : (companies[0]?.id ?? ''),
    );
    const [tab, setTab] = useState<'quotations' | 'orders' | 'profile'>('quotations');
    const [setup, setSetup] = useState<Setup>(emptySetup);
    const [setupLoading, setSetupLoading] = useState(true);
    const [page, setPage] = useState<Page>();
    const [pageNumber, setPageNumber] = useState(1);
    const [query, setQuery] = useState('');
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [revision, setRevision] = useState(0);
    const [selected, setSelected] = useState<string>();
    const [detail, setDetail] = useState<Detail>();
    const [editor, setEditor] = useState<{ record?: Document }>();
    const [busy, setBusy] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [fieldErrors, setFieldErrors] = useState<string[]>([]);
    const [notice, setNotice] = useState('');
    const [convertKey, setConvertKey] = useState(() => crypto.randomUUID());
    const [reason, setReason] = useState('');
    const ready = available && Boolean(company);
    function fail(e: unknown) {
        setError(e instanceof Error ? e.message : 'The request could not be completed.');
        setFieldErrors(e instanceof RequestError ? Object.values(e.errors).flat() : []);
    }
    useEffect(() => {
        if (!ready) return;
        let current = true;
        setSetupLoading(true);
        setSetup(emptySetup);
        void requestJson<Setup>(`/api/v1/sales/setup?company_id=${company}`)
            .then((data) => {
                if (current) setSetup(data);
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
        if (!ready || tab === 'profile') return;
        let current = true;
        setLoading(true);
        setPage(undefined);
        const params = new URLSearchParams({
            company_id: company,
            page: String(pageNumber),
            q: search,
            status,
        });
        void requestJson<Page>(`/api/v1/sales/${tab}?${params}`)
            .then((data) => {
                if (current) setPage(data);
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
    }, [ready, company, tab, pageNumber, search, status, revision]);
    useEffect(() => {
        if (!ready || !selected || tab === 'profile') {
            setDetail(undefined);
            return;
        }
        let current = true;
        setDetail(undefined);
        void requestJson<Detail>(`/api/v1/sales/${tab}/${selected}?company_id=${company}`)
            .then((data) => {
                if (current) setDetail(data);
            })
            .catch((e) => {
                if (current) fail(e);
            });
        return () => {
            current = false;
        };
    }, [ready, selected, company, tab, revision]);
    function clear() {
        setEditor(undefined);
        setSelected(undefined);
        setDetail(undefined);
        setError('');
        setFieldErrors([]);
        setNotice('');
        setReason('');
        setConvertKey(crypto.randomUUID());
    }
    function switchTab(value: typeof tab) {
        clear();
        setTab(value);
        setPageNumber(1);
        setStatus('');
    }
    async function mutate(
        url: string,
        method: string,
        payload: unknown,
        key?: string,
        converted = false,
    ) {
        setBusy(true);
        setError('');
        setFieldErrors([]);
        setNotice('');
        try {
            const result = await requestJson<{ id: string }>(
                url,
                method,
                payload,
                key ? { 'Idempotency-Key': key } : {},
            );
            setEditor(undefined);
            setRevision((r) => r + 1);
            if (converted) {
                setTab('orders');
                setStatus('');
                setPageNumber(1);
                setSelected(result.id);
                setNotice('Order confirmed. Quotation contents and prices are preserved.');
            } else if (tab !== 'profile') {
                setSelected(result.id);
                setNotice('Sales document saved.');
            } else setNotice('Sales profile saved.');
        } catch (e) {
            fail(e);
        } finally {
            setBusy(false);
        }
    }
    const record = detail?.record;
    return (
        <WorkspaceLayout title="Sales" tenant={tenant.name} user={user.name} section="sales">
            <div className="page-heading">
                <div>
                    <p className="eyebrow">REVENUE OPERATIONS</p>
                    <h1>Sales command center</h1>
                    <p>Prepare quotations and turn accepted proposals into confirmed orders.</p>
                </div>
                {ready && canManage && tab !== 'profile' && (
                    <button
                        className="primary-button"
                        disabled={setupLoading || !setup.profile || busy}
                        onClick={() => {
                            clear();
                            setTab('quotations');
                            setEditor({});
                        }}
                    >
                        + New quotation
                    </button>
                )}
            </div>
            <div className="master-tabs" role="tablist" aria-label="Sales sections">
                {(
                    ['quotations', 'orders', ...(canManage ? ['profile'] : [])] as (typeof tab)[]
                ).map((t) => (
                    <button
                        role="tab"
                        key={t}
                        aria-selected={tab === t}
                        className={tab === t ? 'selected' : ''}
                        onClick={() => switchTab(t)}
                    >
                        {t === 'profile'
                            ? 'Sales tax profile'
                            : t === 'orders'
                              ? 'Orders'
                              : 'Quotations'}
                    </button>
                ))}
            </div>
            {error && (
                <div className="form-error" role="alert">
                    <p>{error}</p>
                    {fieldErrors.length > 0 && (
                        <ul>
                            {fieldErrors.map((message, i) => (
                                <li key={i}>{message}</li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
            {notice && (
                <p className="form-success" role="status">
                    {notice}
                </p>
            )}
            {!available ? (
                <section className="panel">
                    <h2>Sales is not active</h2>
                    <p>
                        Ask your workspace owner to activate Contacts, Products &amp; services, CRM
                        and Sales.
                    </p>
                    <Link href="/workspace/master-data">Manage modules</Link>
                </section>
            ) : !company ? (
                <section className="panel">
                    <h2>No company available</h2>
                    <p>Create a company or ask your owner for access.</p>
                </section>
            ) : (
                <>
                    <div className="master-toolbar">
                        <label>
                            Company
                            <select
                                aria-label="Company"
                                value={company}
                                onChange={(e) => {
                                    clear();
                                    setCompany(e.target.value);
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
                        {tab !== 'profile' && (
                            <>
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        setSearch(query);
                                        setPageNumber(1);
                                    }}
                                >
                                    <label className="sr-only" htmlFor="sales-search">
                                        Search Sales documents
                                    </label>
                                    <input
                                        id="sales-search"
                                        placeholder="Search reference, title or customer…"
                                        maxLength={100}
                                        value={query}
                                        onChange={(e) => setQuery(e.target.value)}
                                    />
                                    <button className="secondary-button">Search</button>
                                </form>
                                {tab === 'quotations' && (
                                    <label>
                                        Status
                                        <select
                                            value={status}
                                            onChange={(e) => {
                                                setStatus(e.target.value);
                                                setPageNumber(1);
                                            }}
                                        >
                                            <option value="">All statuses</option>
                                            {['draft', 'issued', 'converted', 'cancelled'].map(
                                                (s) => (
                                                    <option key={s}>{s}</option>
                                                ),
                                            )}
                                        </select>
                                    </label>
                                )}
                            </>
                        )}
                    </div>
                    {setupLoading && <p role="status">Loading Sales setup…</p>}
                    {!setupLoading && !setup.profile && tab !== 'profile' && (
                        <section className="panel">
                            <h2>Set up your Sales profile</h2>
                            <p>
                                Your seller details, VAT status and LKR tax policy are required
                                before a quotation can be saved.
                            </p>
                            {canManage && (
                                <button
                                    className="secondary-button"
                                    onClick={() => switchTab('profile')}
                                >
                                    Configure Sales profile
                                </button>
                            )}
                        </section>
                    )}
                    {tab === 'profile' && !setupLoading && canManage && (
                        <ProfileEditor
                            key={`${company}-${setup.profile?.revision ?? 0}`}
                            profile={setup.profile}
                            busy={busy}
                            onSave={(data) =>
                                void mutate('/api/v1/sales/tax-profile', 'PUT', {
                                    ...(data as object),
                                    company_id: company,
                                })
                            }
                        />
                    )}
                    {editor && !setupLoading && setup.profile && (
                        <QuoteEditor
                            key={editor.record?.id ?? 'new'}
                            company={company}
                            setup={setup}
                            record={editor.record}
                            busy={busy}
                            onClose={() => setEditor(undefined)}
                            onError={fail}
                            onSave={(payload, key) =>
                                void mutate(
                                    `/api/v1/sales/quotations${editor.record ? '/' + editor.record.id : ''}`,
                                    editor.record ? 'PUT' : 'POST',
                                    payload,
                                    editor.record ? undefined : key,
                                )
                            }
                        />
                    )}
                    {tab !== 'profile' && (
                        <section className="panel">
                            <div className="panel-heading">
                                <div>
                                    <h2>{tab === 'orders' ? 'Confirmed orders' : 'Quotations'}</h2>
                                    <p>
                                        {page
                                            ? `${page.total} matching records`
                                            : 'Your company Sales documents'}
                                    </p>
                                </div>
                            </div>
                            {loading ? (
                                <p role="status">Loading Sales documents…</p>
                            ) : (
                                <div className="table-scroll">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Reference / title</th>
                                                <th>Net · LKR</th>
                                                <th>VAT · LKR</th>
                                                <th>Total · LKR</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {page?.data.map((r) => (
                                                <tr key={r.id}>
                                                    <td>
                                                        <strong>{r.number}</strong>
                                                        <br />
                                                        {r.title}
                                                    </td>
                                                    <td>{r.net}</td>
                                                    <td>{r.vat}</td>
                                                    <td>{r.total}</td>
                                                    <td>
                                                        <span className="status-tag">
                                                            {r.status}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button
                                                            className="text-button"
                                                            onClick={() => {
                                                                setSelected(r.id);
                                                                setEditor(undefined);
                                                                setReason('');
                                                                setConvertKey(crypto.randomUUID());
                                                            }}
                                                            aria-label={`Open ${r.number}`}
                                                        >
                                                            View
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {page?.data.length === 0 && (
                                                <tr>
                                                    <td colSpan={6}>
                                                        No matching {tab}.{' '}
                                                        {tab === 'quotations' && canManage
                                                            ? 'Create a quotation when your Sales profile and master data are ready.'
                                                            : 'Confirmed orders appear after quotation conversion.'}
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
                    {selected && !detail && tab !== 'profile' && (
                        <p role="status">Loading document details…</p>
                    )}
                    {record && tab !== 'profile' && (
                        <section className="panel sales-detail" aria-label="Sales document details">
                            <div className="panel-heading">
                                <div>
                                    <p className="eyebrow">
                                        {record.number} · {record.status}
                                    </p>
                                    <h2>{record.title}</h2>
                                </div>
                                <button
                                    className="secondary-button"
                                    onClick={() => setSelected(undefined)}
                                >
                                    Close details
                                </button>
                            </div>
                            <div className="sales-document-meta">
                                <div>
                                    <small>SELLER</small>
                                    <h3>{record.snapshot.seller.seller_name}</h3>
                                    <p>{record.snapshot.seller.seller_address}</p>
                                    <p>
                                        {record.snapshot.seller.vat_registered
                                            ? `VAT registered · ${record.snapshot.seller.vat_registration}`
                                            : 'Supplier not VAT registered'}
                                    </p>
                                </div>
                                <div>
                                    <small>CUSTOMER</small>
                                    <h3>{record.snapshot.customer.name}</h3>
                                    <p>{record.snapshot.customer.number}</p>
                                    <p>
                                        Quotation date {record.snapshot.issue_date} · Valid until{' '}
                                        {record.snapshot.valid_until}
                                    </p>
                                    {record.snapshot.opportunity && (
                                        <p>Opportunity {record.snapshot.opportunity.number}</p>
                                    )}
                                </div>
                            </div>
                            <div className="table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Product / service</th>
                                            <th>Quantity</th>
                                            <th>Unit price excluding VAT</th>
                                            <th>Discount %</th>
                                            <th>VAT treatment</th>
                                            <th>Net</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {record.snapshot.pricing.lines.map((l, i) => (
                                            <tr key={i}>
                                                <td>
                                                    <strong>{l.sku}</strong>
                                                    <br />
                                                    {l.name}
                                                </td>
                                                <td>
                                                    {l.quantity} {l.unit}
                                                </td>
                                                <td>{l.unit_price}</td>
                                                <td>{l.discount_percent}</td>
                                                <td>
                                                    {l.tax_code} · {l.vat_rate}%<br />
                                                    {l.tax_basis}
                                                </td>
                                                <td>{l.net}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <dl className="sales-totals">
                                <div>
                                    <dt>Net excluding VAT</dt>
                                    <dd>LKR {record.net}</dd>
                                </div>
                                <div>
                                    <dt>VAT18 base</dt>
                                    <dd>LKR {record.snapshot.pricing.standard_base}</dd>
                                </div>
                                <div>
                                    <dt>VAT</dt>
                                    <dd>LKR {record.vat}</dd>
                                </div>
                                <div>
                                    <dt>Total</dt>
                                    <dd>LKR {record.total}</dd>
                                </div>
                            </dl>
                            {record.snapshot.terms && (
                                <div className="sales-terms">
                                    <h3>Terms</h3>
                                    <p>{record.snapshot.terms}</p>
                                </div>
                            )}
                            <p className="muted">
                                Rule {record.snapshot.pricing.rule_version}. Not a tax invoice. No
                                payment, fulfillment or accounting posting is recorded here.
                            </p>
                            <div className="record-actions">
                                <a
                                    className="secondary-button"
                                    href={`/api/v1/sales/${tab}/${record.id}/pdf?company_id=${company}`}
                                >
                                    Download {tab === 'orders' ? 'order' : 'quotation'} PDF
                                </a>
                                {record.order_id && (
                                    <button
                                        className="secondary-button"
                                        onClick={() => {
                                            switchTab('orders');
                                            setSelected(record.order_id!);
                                        }}
                                    >
                                        View confirmed order
                                    </button>
                                )}
                                {record.quote_id && (
                                    <button
                                        className="secondary-button"
                                        onClick={() => {
                                            switchTab('quotations');
                                            setSelected(record.quote_id!);
                                        }}
                                    >
                                        View source quotation
                                    </button>
                                )}
                                {canManage && tab === 'quotations' && record.status === 'draft' && (
                                    <>
                                        <button
                                            className="secondary-button"
                                            disabled={busy}
                                            onClick={() => setEditor({ record })}
                                        >
                                            Edit draft
                                        </button>
                                        <button
                                            className="primary-button"
                                            disabled={busy}
                                            onClick={() =>
                                                void mutate(
                                                    `/api/v1/sales/quotations/${record.id}/transition`,
                                                    'POST',
                                                    {
                                                        company_id: company,
                                                        revision: record.revision,
                                                        action: 'issue',
                                                    },
                                                )
                                            }
                                        >
                                            Issue quotation
                                        </button>
                                    </>
                                )}
                                {canManage &&
                                    tab === 'quotations' &&
                                    record.status === 'issued' && (
                                        <button
                                            className="primary-button"
                                            disabled={busy}
                                            onClick={() =>
                                                void mutate(
                                                    `/api/v1/sales/quotations/${record.id}/convert`,
                                                    'POST',
                                                    {
                                                        company_id: company,
                                                        revision: record.revision,
                                                    },
                                                    convertKey,
                                                    true,
                                                )
                                            }
                                        >
                                            Confirm order from quotation
                                        </button>
                                    )}
                            </div>
                            {canManage &&
                                tab === 'quotations' &&
                                ['draft', 'issued'].includes(record.status) && (
                                    <form
                                        className="sales-cancel"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            void mutate(
                                                `/api/v1/sales/quotations/${record.id}/transition`,
                                                'POST',
                                                {
                                                    company_id: company,
                                                    revision: record.revision,
                                                    action: 'cancel',
                                                    reason,
                                                },
                                            );
                                        }}
                                    >
                                        <label>
                                            Cancellation reason
                                            <input
                                                required
                                                maxLength={500}
                                                value={reason}
                                                onChange={(e) => setReason(e.target.value)}
                                            />
                                        </label>
                                        <button className="secondary-button" disabled={busy}>
                                            Cancel quotation
                                        </button>
                                    </form>
                                )}
                            <h3>Document history</h3>
                            <p className="muted">
                                Latest 100 entries. Earlier history remains stored.
                            </p>
                            <ol className="sales-history">
                                {detail.history.map((h) => (
                                    <li key={h.id}>
                                        <strong>{h.action}</strong> ·{' '}
                                        <time>{new Date(h.occurred_at).toLocaleString()}</time>
                                        {h.reason && <p>{h.reason}</p>}
                                    </li>
                                ))}
                            </ol>
                        </section>
                    )}
                </>
            )}
        </WorkspaceLayout>
    );
}
