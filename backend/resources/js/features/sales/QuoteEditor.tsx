import { useEffect, useState, type FormEvent } from 'react';
import { requestJson } from '../../lib/request';
import type { Document, Line, Opportunity, Setup } from './types';

export default function QuoteEditor({
    company,
    setup,
    record,
    busy,
    onSave,
    onClose,
    onError,
}: {
    company: string;
    setup: Setup;
    record?: Document;
    busy: boolean;
    onSave: (payload: unknown, key: string) => void;
    onClose: () => void;
    onError: (error: unknown) => void;
}) {
    const [key] = useState(() => crypto.randomUUID());
    const [customer, setCustomer] = useState(record?.customer_id ?? '');
    const [title, setTitle] = useState(record?.title ?? '');
    const [opportunity, setOpportunity] = useState(record?.opportunity_id ?? '');
    const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
    const [issueDate, setIssueDate] = useState(
        record?.issue_date ??
            new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Colombo' }).format(new Date()),
    );
    const [validUntil, setValidUntil] = useState(record?.valid_until ?? '');
    const [terms, setTerms] = useState(record?.terms ?? '');
    const [lookup, setLookup] = useState('');
    const [search, setSearch] = useState('');
    const [choices, setChoices] = useState<Setup>(setup);
    const blank = (): Line => ({
        variant_id: '',
        quantity: '1',
        unit_price: '',
        discount_percent: '0',
        tax_code: setup.profile?.vat_registered ? 'standard' : 'unregistered',
        tax_basis: '',
    });
    const [lines, setLines] = useState<Line[]>(
        record?.snapshot.pricing.lines.map((l) => ({ ...l, tax_basis: l.tax_basis ?? '' })) ?? [
            blank(),
        ],
    );
    useEffect(() => {
        let current = true;
        void requestJson<Setup>(
            `/api/v1/sales/setup?company_id=${company}&q=${encodeURIComponent(search)}${customer ? '&customer_id=' + customer : ''}`,
        )
            .then((data) => {
                if (current) {
                    setChoices(data);
                    setOpportunities(data.opportunities);
                }
            })
            .catch((e) => {
                if (current) onError(e);
            });
        return () => {
            current = false;
        };
    }, [company, customer, search]); // onError only displays request feedback.
    function line(index: number, update: Partial<Line>) {
        setLines((old) => old.map((l, i) => (i === index ? { ...l, ...update } : l)));
    }
    function submit(e: FormEvent) {
        e.preventDefault();
        onSave(
            {
                company_id: company,
                customer_id: customer,
                opportunity_id: opportunity || null,
                title,
                issue_date: issueDate,
                valid_until: validUntil,
                terms: terms || null,
                ...(record ? { revision: record.revision } : {}),
                lines: lines.map((l) => ({
                    variant_id: l.variant_id,
                    quantity: l.quantity,
                    unit_price: l.unit_price,
                    discount_percent: l.discount_percent,
                    tax_code: l.tax_code,
                    tax_basis: l.tax_basis || null,
                })),
            },
            key,
        );
    }
    return (
        <section className="panel sales-editor" aria-label="Quotation editor">
            <div className="panel-heading">
                <h2>{record ? 'Edit draft quotation' : 'New quotation'}</h2>
                <button className="secondary-button" onClick={onClose}>
                    Close editor
                </button>
            </div>
            <p className="muted">
                Prices exclude VAT. Totals are calculated on the server when you save. Six-place
                inputs; LKR totals round to cents.
            </p>
            <form onSubmit={submit}>
                <div className="sales-lookup">
                    <label>
                        Find customer or product
                        <input
                            value={lookup}
                            onChange={(e) => setLookup(e.target.value)}
                            maxLength={100}
                        />
                    </label>
                    <button
                        type="button"
                        className="secondary-button"
                        onClick={() => setSearch(lookup)}
                    >
                        Find matches
                    </button>
                </div>
                <div className="form-grid">
                    <label>
                        Quotation title
                        <input
                            required
                            maxLength={160}
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                        />
                    </label>
                    <label>
                        Customer
                        <select
                            required
                            value={customer}
                            onChange={(e) => {
                                setCustomer(e.target.value);
                                setOpportunity('');
                            }}
                        >
                            <option value="">Choose a customer</option>
                            {record &&
                                !choices.customers.some((c) => c.id === record.customer_id) && (
                                    <option value={record.customer_id}>
                                        {record.snapshot.customer.name}
                                    </option>
                                )}
                            {choices.customers.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.number} · {c.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Opportunity (optional)
                        <select
                            value={opportunity}
                            onChange={(e) => setOpportunity(e.target.value)}
                        >
                            <option value="">No linked opportunity</option>
                            {opportunities.map((o) => (
                                <option key={o.id} value={o.id}>
                                    {o.number} · {o.title}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Quotation date
                        <input
                            type="date"
                            required
                            value={issueDate}
                            onChange={(e) => setIssueDate(e.target.value)}
                        />
                    </label>
                    <label>
                        Valid until
                        <input
                            type="date"
                            required
                            min={issueDate}
                            value={validUntil}
                            onChange={(e) => setValidUntil(e.target.value)}
                        />
                    </label>
                </div>
                <h3>Quotation lines</h3>
                {lines.map((l, i) => (
                    <fieldset className="sales-line" key={i}>
                        <legend>Line {i + 1}</legend>
                        <div className="form-grid">
                            <label>
                                Product or service {i + 1}
                                <select
                                    required
                                    value={l.variant_id}
                                    onChange={(e) => line(i, { variant_id: e.target.value })}
                                >
                                    <option value="">Choose a variant</option>
                                    {l.variant_id &&
                                        !choices.products.some((p) => p.id === l.variant_id) && (
                                            <option value={l.variant_id}>
                                                {l.sku} · {l.name}
                                            </option>
                                        )}
                                    {choices.products.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.sku} · {p.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                Quantity {i + 1}
                                <input
                                    required
                                    inputMode="decimal"
                                    value={l.quantity}
                                    onChange={(e) => line(i, { quantity: e.target.value })}
                                />
                            </label>
                            <label>
                                Unit price excluding VAT {i + 1}
                                <input
                                    required
                                    inputMode="decimal"
                                    value={l.unit_price}
                                    onChange={(e) => line(i, { unit_price: e.target.value })}
                                />
                            </label>
                            <label>
                                Discount % {i + 1}
                                <input
                                    required
                                    inputMode="decimal"
                                    value={l.discount_percent}
                                    onChange={(e) => line(i, { discount_percent: e.target.value })}
                                />
                            </label>
                            <label>
                                VAT treatment {i + 1}
                                <select
                                    value={l.tax_code}
                                    onChange={(e) => line(i, { tax_code: e.target.value })}
                                >
                                    {setup.profile?.vat_registered ? (
                                        <>
                                            <option value="standard">Standard VAT 18%</option>
                                            <option value="zero">
                                                Zero-rated · legal basis required
                                            </option>
                                            <option value="exempt">
                                                Exempt · legal basis required
                                            </option>
                                        </>
                                    ) : (
                                        <option value="unregistered">
                                            Supplier not VAT registered
                                        </option>
                                    )}
                                </select>
                            </label>
                            {['zero', 'exempt'].includes(l.tax_code) && (
                                <label>
                                    Legal basis {i + 1}
                                    <input
                                        required
                                        maxLength={300}
                                        value={l.tax_basis}
                                        onChange={(e) => line(i, { tax_basis: e.target.value })}
                                    />
                                </label>
                            )}
                        </div>
                        <button
                            type="button"
                            className="text-button"
                            disabled={lines.length === 1}
                            onClick={() => setLines((old) => old.filter((_, n) => n !== i))}
                        >
                            Remove line {i + 1}
                        </button>
                    </fieldset>
                ))}
                <button
                    type="button"
                    className="secondary-button"
                    disabled={lines.length >= 50}
                    onClick={() => setLines((old) => [...old, blank()])}
                >
                    Add line
                </button>
                <label className="sales-terms">
                    Terms
                    <textarea
                        maxLength={4000}
                        rows={4}
                        value={terms}
                        onChange={(e) => setTerms(e.target.value)}
                    />
                </label>
                <button className="primary-button" disabled={busy}>
                    {busy ? 'Saving…' : 'Save draft quotation'}
                </button>
            </form>
        </section>
    );
}
