import { useEffect, useRef, useState, type FormEvent } from 'react';
import WorkspaceLayout from '../components/WorkspaceLayout';
import { RequestError, requestJson } from '../lib/request';

type Company = { id: string; name: string; base_currency: string };
type Module = {
    name: string;
    version: string;
    installed_version: string | null;
    enabled: boolean;
    entitled: boolean;
    available: boolean;
    dependencies: string[];
};
type Contact = {
    id: string;
    number: string;
    name: string;
    email: string | null;
    phone: string | null;
    customer: boolean;
    supplier: boolean;
    active: boolean;
    revision: number;
};
type Variant = {
    id: string;
    name: string;
    sku: string;
    attributes: Record<string, string>;
    list_price: string | null;
    currency: string;
    active: boolean;
    revision: number;
};
type Product = {
    id: string;
    number: string;
    name: string;
    type: string;
    unit: string;
    active: boolean;
    revision: number;
    variants: Variant[];
};
type EventRow = {
    id: string;
    event_type: string;
    aggregate_id: string;
    state: string;
    attempts: number;
    error_code: string | null;
    occurred_at: string;
};
type Page<T> = {
    data: T[];
    current_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};
type Props = {
    tenant: { name: string };
    user: { name: string; role: string };
    companies: Company[];
    initialCompany: string | null;
    modules: Module[];
    canManage: boolean;
};
const emptyForm = {
    name: '',
    email: '',
    phone: '',
    customer: false,
    supplier: false,
    active: true,
    sku: '',
    type: 'product',
    unit: 'unit',
    list_price: '',
    revision: 1,
};
type RecordForm = typeof emptyForm;

export default function MasterData({
    tenant,
    user,
    companies,
    initialCompany,
    modules: initialModules,
    canManage,
}: Props) {
    const [tab, setTab] = useState<'contacts' | 'products' | 'modules' | 'events'>('contacts');
    const [modules, setModules] = useState(initialModules);
    const [company, setCompany] = useState(
        companies.some((c) => c.id === initialCompany) ? initialCompany! : (companies[0]?.id ?? ''),
    );
    const [query, setQuery] = useState('');
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const [page, setPage] = useState(1);
    const [result, setResult] = useState<Page<Contact | Product>>();
    const [events, setEvents] = useState<Page<EventRow>>();
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [form, setForm] = useState<RecordForm>(emptyForm);
    const [editing, setEditing] = useState<string | null>(null);
    const [showForm, setShowForm] = useState(false);
    const [variantProduct, setVariantProduct] = useState<Product | null>(null);
    const [variantId, setVariantId] = useState<string | null>(null);
    const [attributes, setAttributes] = useState<[string, string][]>([]);
    const [revision, setRevision] = useState(0);
    const key = useRef('');
    const currentModule = modules.find(
        (m) => m.name === (tab === 'contacts' ? 'Parties' : 'Catalog'),
    );
    const canRead =
        (tab === 'contacts' || tab === 'products') && Boolean(company && currentModule?.available);
    const selectedCompany = companies.find((c) => c.id === company);
    useEffect(() => {
        if (!canRead && tab !== 'events') {
            setResult(undefined);
            return;
        }
        let current = true;
        setLoading(true);
        setError('');
        const url =
            tab === 'events'
                ? `/api/v1/events?page=${page}`
                : `/api/v1/${tab}?company_id=${company}&q=${encodeURIComponent(search)}&status=${status}&page=${page}`;
        void requestJson<Page<Contact | Product> | Page<EventRow>>(url)
            .then((data) => {
                if (!current) return;
                if (tab === 'events') setEvents(data as Page<EventRow>);
                else setResult(data as Page<Contact | Product>);
            })
            .catch((e) => {
                if (current) {
                    setError(e instanceof Error ? e.message : 'Unable to load records.');
                    setResult(undefined);
                    setEvents(undefined);
                }
            })
            .finally(() => {
                if (current) setLoading(false);
            });
        return () => {
            current = false;
        };
    }, [tab, company, search, status, page, revision, canRead]);
    function resetEditor() {
        setShowForm(false);
        setEditing(null);
        setVariantProduct(null);
        setVariantId(null);
        setForm(emptyForm);
        setErrors({});
        setAttributes([]);
        key.current = '';
    }
    function changeTab(value: typeof tab) {
        resetEditor();
        setTab(value);
        setPage(1);
        setQuery('');
        setSearch('');
        setError('');
        setNotice('');
        setResult(undefined);
        setEvents(undefined);
    }
    function edit(row: Contact | Product) {
        setEditing(row.id);
        setVariantProduct(null);
        setVariantId(null);
        setErrors({});
        setError('');
        setShowForm(true);
        key.current = crypto.randomUUID();
        setForm({
            ...emptyForm,
            ...row,
            email: 'email' in row ? (row.email ?? '') : '',
            phone: 'phone' in row ? (row.phone ?? '') : '',
        });
    }
    function newRecord() {
        resetEditor();
        setForm(emptyForm);
        setError('');
        setShowForm(true);
        key.current = crypto.randomUUID();
    }
    function editVariant(product: Product, variant?: Variant) {
        resetEditor();
        setVariantProduct(product);
        setVariantId(variant?.id ?? null);
        setShowForm(true);
        key.current = crypto.randomUUID();
        setForm({
            ...emptyForm,
            name: variant?.name ?? product.name,
            sku: variant?.sku ?? '',
            list_price: variant?.list_price ?? '',
            active: variant?.active ?? true,
            revision: variant?.revision ?? 1,
        });
        setAttributes(Object.entries(variant?.attributes ?? {}));
    }
    function field<K extends keyof RecordForm>(name: K, value: RecordForm[K]) {
        setForm((data) => ({ ...data, [name]: value }));
    }
    function displayError(e: unknown) {
        setError(e instanceof Error ? e.message : 'Unable to complete the request.');
        setErrors(e instanceof RequestError ? e.errors : {});
    }
    async function save(event: FormEvent) {
        event.preventDefault();
        setSaving(true);
        setError('');
        setErrors({});
        setNotice('');
        const creating = variantProduct ? !variantId : !editing;
        const url = variantProduct
            ? `/api/v1/products/${variantProduct.id}/variants${variantId ? '/' + variantId : ''}`
            : `/api/v1/${tab}${editing ? '/' + editing : ''}`;
        const body = variantProduct
            ? {
                  company_id: company,
                  name: form.name,
                  sku: form.sku,
                  active: form.active,
                  revision: form.revision,
                  list_price: form.list_price || null,
                  attributes: Object.fromEntries(attributes),
              }
            : tab === 'contacts'
              ? {
                    company_id: company,
                    name: form.name,
                    email: form.email || null,
                    phone: form.phone || null,
                    customer: form.customer,
                    supplier: form.supplier,
                    active: form.active,
                    revision: form.revision,
                }
              : {
                    company_id: company,
                    name: form.name,
                    active: form.active,
                    revision: form.revision,
                    ...(!editing
                        ? {
                              sku: form.sku,
                              type: form.type,
                              unit: form.unit,
                              list_price: form.list_price || null,
                          }
                        : {}),
                };
        try {
            await requestJson(
                url,
                creating ? 'POST' : 'PUT',
                body,
                creating ? { 'Idempotency-Key': key.current } : {},
            );
            resetEditor();
            setNotice('Record saved.');
            setRevision((v) => v + 1);
        } catch (e) {
            displayError(e);
        } finally {
            setSaving(false);
        }
    }
    async function toggle(module: Module) {
        setSaving(true);
        setError('');
        setNotice('');
        try {
            const data = await requestJson<{ data: Module[] }>(
                `/api/v1/modules/${module.name}`,
                'PUT',
                { enabled: !module.enabled },
            );
            setModules(data.data);
            setNotice(
                `${module.name} ${module.enabled ? 'deactivated' : 'activated'}. Existing records are preserved.`,
            );
        } catch (e) {
            displayError(e);
        } finally {
            setSaving(false);
        }
    }
    async function replay(id: string) {
        setSaving(true);
        setError('');
        try {
            await requestJson(`/api/v1/events/${id}/replay`, 'POST');
            setRevision((v) => v + 1);
            setNotice('Event is pending authorized delivery.');
        } catch (e) {
            displayError(e);
        } finally {
            setSaving(false);
        }
    }
    const currentPage = tab === 'events' ? events : result;
    return (
        <WorkspaceLayout title="Contacts & catalog" tenant={tenant.name} user={user.name}>
            <div className="page-heading">
                <div>
                    <p className="eyebrow">SHARED BUSINESS RECORDS</p>
                    <h1>Contacts &amp; catalog</h1>
                    <p>
                        A consistent starting point for your customers, suppliers, products and
                        services.
                    </p>
                </div>
            </div>
            <div className="master-tabs" role="tablist" aria-label="Master data sections">
                {(
                    ['contacts', 'products', 'modules', ...(canManage ? ['events'] : [])] as const
                ).map((value) => (
                    <button
                        key={value}
                        role="tab"
                        aria-selected={tab === value}
                        className={tab === value ? 'selected' : ''}
                        onClick={() => changeTab(value as typeof tab)}
                    >
                        {value === 'contacts'
                            ? 'Contacts'
                            : value === 'products'
                              ? 'Products & services'
                              : value === 'modules'
                                ? 'Modules'
                                : 'Event delivery'}
                    </button>
                ))}
            </div>
            {error && (
                <div className="form-error" role="alert">
                    {error}{' '}
                    {Object.entries(errors).map(([name, messages]) => (
                        <div key={name}>{messages.join(' ')}</div>
                    ))}
                    {!showForm && (
                        <button className="text-button" onClick={() => setRevision((v) => v + 1)}>
                            Retry loading
                        </button>
                    )}
                </div>
            )}
            {notice && (
                <p className="success-message" role="status">
                    {notice}
                </p>
            )}
            {(tab === 'contacts' || tab === 'products') && (
                <>
                    <div className="master-toolbar">
                        <label>
                            Company
                            <select
                                aria-label="Company"
                                value={company}
                                onChange={(e) => {
                                    setCompany(e.target.value);
                                    setPage(1);
                                    setResult(undefined);
                                    resetEditor();
                                }}
                            >
                                {!companies.length && <option value="">No company access</option>}
                                {companies.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                setSearch(query);
                                setPage(1);
                            }}
                        >
                            <label className="sr-only" htmlFor="record-search">
                                Search records
                            </label>
                            <input
                                id="record-search"
                                placeholder="Search records…"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
                            <button className="secondary-button">Search</button>
                        </form>
                        <label>
                            Status
                            <select
                                value={status}
                                onChange={(e) => {
                                    setStatus(e.target.value);
                                    setPage(1);
                                }}
                            >
                                <option value="all">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </label>
                        {canManage && canRead && (
                            <button className="primary-button" onClick={newRecord}>
                                + New {tab === 'contacts' ? 'contact' : 'product or service'}
                            </button>
                        )}
                    </div>
                    {!company && (
                        <section className="panel empty-state">
                            <h2>No company available</h2>
                            <p>
                                Create a company in Organization, or ask your workspace owner for
                                access.
                            </p>
                        </section>
                    )}
                    {company && !currentModule?.available && (
                        <section className="panel empty-state">
                            <h2>{currentModule?.name ?? 'Module'} is unavailable</h2>
                            <p>
                                {canManage
                                    ? 'Review its installation and entitlement in Modules, then activate it to use these records.'
                                    : 'Ask your workspace owner to activate this module.'}
                            </p>
                            <button
                                className="secondary-button"
                                onClick={() => changeTab('modules')}
                            >
                                View modules
                            </button>
                        </section>
                    )}
                    {showForm && canRead && (
                        <section className="panel master-editor">
                            <div className="panel-heading">
                                <div>
                                    <h2>
                                        {variantProduct
                                            ? `${variantId ? 'Edit' : 'Add'} variant · ${variantProduct.name}`
                                            : `${editing ? 'Edit' : 'New'} ${tab === 'contacts' ? 'contact' : 'product or service'}`}
                                    </h2>
                                    <p>
                                        {editing || variantId
                                            ? 'Save the current revision, or reload if another team member has changed it.'
                                            : 'New records receive a permanent reference number.'}
                                    </p>
                                </div>
                                <button
                                    className="text-button"
                                    onClick={resetEditor}
                                    disabled={saving}
                                >
                                    Cancel
                                </button>
                            </div>
                            <form onSubmit={(e) => void save(e)} className="master-form">
                                <label>
                                    Name
                                    <input
                                        required
                                        maxLength={160}
                                        value={form.name}
                                        onChange={(e) => field('name', e.target.value)}
                                    />
                                </label>
                                {tab === 'contacts' && !variantProduct ? (
                                    <>
                                        <label>
                                            Email
                                            <input
                                                type="email"
                                                maxLength={254}
                                                value={form.email}
                                                onChange={(e) => field('email', e.target.value)}
                                            />
                                        </label>
                                        <label>
                                            Phone
                                            <input
                                                maxLength={40}
                                                value={form.phone}
                                                onChange={(e) => field('phone', e.target.value)}
                                            />
                                        </label>
                                        <div className="checkbox-row">
                                            <label>
                                                <input
                                                    type="checkbox"
                                                    checked={form.customer}
                                                    onChange={(e) =>
                                                        field('customer', e.target.checked)
                                                    }
                                                />
                                                Customer
                                            </label>
                                            <label>
                                                <input
                                                    type="checkbox"
                                                    checked={form.supplier}
                                                    onChange={(e) =>
                                                        field('supplier', e.target.checked)
                                                    }
                                                />
                                                Supplier
                                            </label>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        {(!editing || variantProduct) && (
                                            <label>
                                                SKU
                                                <input
                                                    required
                                                    disabled={Boolean(variantId)}
                                                    maxLength={50}
                                                    value={form.sku}
                                                    onChange={(e) =>
                                                        field('sku', e.target.value.toUpperCase())
                                                    }
                                                />
                                            </label>
                                        )}
                                        {!editing && !variantProduct && (
                                            <>
                                                <label>
                                                    Type
                                                    <select
                                                        value={form.type}
                                                        onChange={(e) =>
                                                            field('type', e.target.value)
                                                        }
                                                    >
                                                        <option value="product">Product</option>
                                                        <option value="service">Service</option>
                                                    </select>
                                                </label>
                                                <label>
                                                    Unit label
                                                    <input
                                                        required
                                                        maxLength={30}
                                                        value={form.unit}
                                                        onChange={(e) =>
                                                            field('unit', e.target.value)
                                                        }
                                                    />
                                                </label>
                                            </>
                                        )}
                                        {(!editing || variantProduct) && (
                                            <label>
                                                List price ({selectedCompany?.base_currency})
                                                <input
                                                    inputMode="decimal"
                                                    placeholder="Optional"
                                                    value={form.list_price}
                                                    onChange={(e) =>
                                                        field('list_price', e.target.value)
                                                    }
                                                />
                                                <small>
                                                    Up to 6 decimal places. No tax or discount
                                                    calculation.
                                                </small>
                                            </label>
                                        )}
                                        {variantProduct && (
                                            <fieldset className="attribute-editor">
                                                <legend>Variant attributes</legend>
                                                {attributes.map(([name, value], index) => (
                                                    <div className="attribute-row" key={index}>
                                                        <input
                                                            aria-label={`Attribute ${index + 1} name`}
                                                            placeholder="e.g. Size"
                                                            value={name}
                                                            maxLength={40}
                                                            onChange={(e) =>
                                                                setAttributes((rows) =>
                                                                    rows.map((row, i) =>
                                                                        i === index
                                                                            ? [
                                                                                  e.target.value,
                                                                                  row[1],
                                                                              ]
                                                                            : row,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                        <input
                                                            aria-label={`Attribute ${index + 1} value`}
                                                            placeholder="e.g. Large"
                                                            required
                                                            value={value}
                                                            maxLength={100}
                                                            onChange={(e) =>
                                                                setAttributes((rows) =>
                                                                    rows.map((row, i) =>
                                                                        i === index
                                                                            ? [
                                                                                  row[0],
                                                                                  e.target.value,
                                                                              ]
                                                                            : row,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                        <button
                                                            type="button"
                                                            className="text-button"
                                                            onClick={() =>
                                                                setAttributes((rows) =>
                                                                    rows.filter(
                                                                        (_, i) => i !== index,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            Remove
                                                        </button>
                                                    </div>
                                                ))}
                                                <button
                                                    type="button"
                                                    className="secondary-button"
                                                    disabled={attributes.length >= 10}
                                                    onClick={() =>
                                                        setAttributes((rows) => [...rows, ['', '']])
                                                    }
                                                >
                                                    Add attribute
                                                </button>
                                            </fieldset>
                                        )}
                                    </>
                                )}
                                <label className="checkbox-label">
                                    <input
                                        type="checkbox"
                                        checked={form.active}
                                        onChange={(e) => field('active', e.target.checked)}
                                    />
                                    Active
                                </label>
                                <div className="form-actions">
                                    <button className="primary-button" disabled={saving}>
                                        {saving ? 'Saving…' : 'Save record'}
                                    </button>
                                    <button
                                        type="button"
                                        className="secondary-button"
                                        disabled={saving}
                                        onClick={() => {
                                            resetEditor();
                                            setRevision((v) => v + 1);
                                        }}
                                    >
                                        Cancel and reload
                                    </button>
                                </div>
                            </form>
                        </section>
                    )}
                    {canRead && (
                        <section className="panel">
                            <div className="panel-heading">
                                <div>
                                    <h2>
                                        {tab === 'contacts'
                                            ? 'Contact directory'
                                            : 'Product catalog'}
                                    </h2>
                                    <p>
                                        {selectedCompany?.name} ·{' '}
                                        {tab === 'contacts'
                                            ? 'Customer, supplier and contact identities'
                                            : 'Definitions and variant list prices'}
                                    </p>
                                </div>
                            </div>
                            {loading ? (
                                <p className="empty-state" role="status">
                                    Loading records…
                                </p>
                            ) : !error && !result?.data.length ? (
                                <div className="empty-state">
                                    <h3>No records found</h3>
                                    <p>
                                        {search
                                            ? 'Try another search or status filter.'
                                            : canManage
                                              ? 'Create the first record for this company.'
                                              : 'Your team has not added records here yet.'}
                                    </p>
                                </div>
                            ) : (
                                <div className="table-wrap">
                                    <table className="data-table">
                                        <thead>
                                            <tr>
                                                <th>Reference / name</th>
                                                <th>
                                                    {tab === 'contacts'
                                                        ? 'Contact details'
                                                        : 'Type / unit'}
                                                </th>
                                                <th>
                                                    {tab === 'contacts'
                                                        ? 'Roles'
                                                        : 'Variants / list price'}
                                                </th>
                                                <th>Status</th>
                                                {canManage && <th>Actions</th>}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {result?.data.map((row) => (
                                                <tr key={row.id}>
                                                    <td>
                                                        <small>{row.number}</small>
                                                        <strong>{row.name}</strong>
                                                    </td>
                                                    {'email' in row ? (
                                                        <>
                                                            <td>
                                                                {row.email || '—'}
                                                                <small>{row.phone}</small>
                                                            </td>
                                                            <td>
                                                                {[
                                                                    row.customer && 'Customer',
                                                                    row.supplier && 'Supplier',
                                                                ]
                                                                    .filter(Boolean)
                                                                    .join(' · ') || 'Contact'}
                                                            </td>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <td>
                                                                {row.type}
                                                                <small>{row.unit}</small>
                                                            </td>
                                                            <td>
                                                                {row.variants.map((v) => (
                                                                    <div
                                                                        className="variant-line"
                                                                        key={v.id}
                                                                    >
                                                                        <span>
                                                                            {v.sku}
                                                                            <small>
                                                                                {v.name}
                                                                                {!v.active
                                                                                    ? ' · Inactive'
                                                                                    : ''}
                                                                                {Object.entries(
                                                                                    v.attributes,
                                                                                ).length
                                                                                    ? ' · ' +
                                                                                      Object.entries(
                                                                                          v.attributes,
                                                                                      )
                                                                                          .map(
                                                                                              ([
                                                                                                  k,
                                                                                                  val,
                                                                                              ]) =>
                                                                                                  `${k}: ${val}`,
                                                                                          )
                                                                                          .join(
                                                                                              ', ',
                                                                                          )
                                                                                    : ''}
                                                                            </small>
                                                                        </span>
                                                                        <span>
                                                                            {v.list_price === null
                                                                                ? 'No list price'
                                                                                : `${v.currency} ${v.list_price}`}
                                                                        </span>
                                                                        {canManage &&
                                                                            row.active && (
                                                                                <button
                                                                                    className="text-button"
                                                                                    aria-label={`Edit variant ${v.sku}`}
                                                                                    onClick={() =>
                                                                                        editVariant(
                                                                                            row,
                                                                                            v,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    Edit
                                                                                </button>
                                                                            )}
                                                                    </div>
                                                                ))}
                                                            </td>
                                                        </>
                                                    )}
                                                    <td>
                                                        <span
                                                            className={`status-pill ${row.active ? 'ready' : 'unavailable'}`}
                                                        >
                                                            {row.active ? 'Active' : 'Inactive'}
                                                        </span>
                                                    </td>
                                                    {canManage && (
                                                        <td>
                                                            <button
                                                                className="text-button"
                                                                aria-label={`Edit ${row.name}`}
                                                                onClick={() => edit(row)}
                                                            >
                                                                Edit
                                                            </button>
                                                            {'variants' in row && row.active && (
                                                                <button
                                                                    className="text-button"
                                                                    onClick={() => editVariant(row)}
                                                                >
                                                                    Add variant
                                                                </button>
                                                            )}
                                                        </td>
                                                    )}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    )}
                </>
            )}
            {tab === 'modules' && (
                <section className="panel">
                    <div className="panel-heading">
                        <div>
                            <h2>Workspace modules</h2>
                            <p>
                                Activate included modules when your team is ready. Deactivation
                                preserves their records.
                            </p>
                        </div>
                    </div>
                    {modules.map((module) => (
                        <div className="module-row" key={module.name}>
                            <div>
                                <h3>
                                    {module.name === 'Sales'
                                        ? 'Sales'
                                        : module.name === 'CRM'
                                          ? 'CRM'
                                          : module.name === 'Parties'
                                            ? 'Contacts'
                                            : 'Products & services'}
                                </h3>
                                <p>
                                    {module.name === 'Sales'
                                        ? 'Quotations, tax profiles and confirmed orders.'
                                        : module.name === 'CRM'
                                          ? 'Leads, pipelines, opportunities and activities.'
                                          : module.name === 'Parties'
                                            ? 'A shared directory for customer and supplier identities.'
                                            : 'Product definitions, variants and explicit list prices.'}
                                </p>
                                <small>
                                    Version {module.installed_version ?? 'not installed'}
                                    {!module.entitled ? ' · Entitlement unavailable' : ''}
                                    {module.dependencies.length
                                        ? ' · Requires ' + module.dependencies.join(', ')
                                        : ''}
                                </small>
                            </div>
                            <span
                                className={`status-pill ${module.available ? 'ready' : 'unavailable'}`}
                            >
                                {module.available
                                    ? 'Active'
                                    : module.enabled
                                      ? 'Unavailable'
                                      : 'Inactive'}
                            </span>
                            {canManage && (
                                <button
                                    className="secondary-button"
                                    disabled={
                                        saving ||
                                        !module.installed_version ||
                                        (!module.entitled && !module.enabled)
                                    }
                                    onClick={() => void toggle(module)}
                                >
                                    {module.enabled ? 'Deactivate' : 'Activate'}
                                </button>
                            )}
                        </div>
                    ))}
                </section>
            )}
            {tab === 'events' && canManage && (
                <section className="panel">
                    <div className="panel-heading">
                        <div>
                            <h2>Event delivery</h2>
                            <p>
                                Committed business changes awaiting or recorded by the internal
                                event journal.
                            </p>
                        </div>
                        <button
                            className="secondary-button"
                            onClick={() => setRevision((v) => v + 1)}
                        >
                            Refresh
                        </button>
                    </div>
                    {loading ? (
                        <p className="empty-state" role="status">
                            Loading delivery history…
                        </p>
                    ) : !events?.data.length ? (
                        <p className="empty-state">No master-data changes recorded yet.</p>
                    ) : (
                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>State</th>
                                        <th>Attempts</th>
                                        <th>Recorded</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {events.data.map((event) => (
                                        <tr key={event.id}>
                                            <td>
                                                {event.event_type}
                                                <small>{event.id}</small>
                                            </td>
                                            <td>
                                                {event.state}
                                                <small>{event.error_code}</small>
                                            </td>
                                            <td>{event.attempts}</td>
                                            <td>{new Date(event.occurred_at).toLocaleString()}</td>
                                            <td>
                                                {event.state === 'dead' && (
                                                    <button
                                                        className="secondary-button"
                                                        disabled={saving}
                                                        onClick={() => void replay(event.id)}
                                                    >
                                                        Replay
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            )}
            {currentPage && (tab === 'events' || canRead) && (
                <div className="pagination">
                    <span>Page {currentPage.current_page}</span>
                    <button
                        className="secondary-button"
                        disabled={!currentPage.prev_page_url || loading}
                        onClick={() => setPage((p) => Math.max(1, p - 1))}
                    >
                        Previous
                    </button>
                    <button
                        className="secondary-button"
                        disabled={!currentPage.next_page_url || loading}
                        onClick={() => setPage((p) => p + 1)}
                    >
                        Next
                    </button>
                </div>
            )}
        </WorkspaceLayout>
    );
}
