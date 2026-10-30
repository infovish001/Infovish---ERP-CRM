import { useState, type FormEvent } from 'react';
import { requestJson } from '../../lib/request';
import type { Setup, Entry } from './types';
import { today } from './types';
export default function DocumentForm({
    company,
    setup,
    kind,
    record,
    busy,
    save,
    close,
    error,
}: {
    company: string;
    setup: Setup;
    kind: 'invoice' | 'bill';
    record?: Entry;
    busy: boolean;
    save: (data: unknown, key: string) => void;
    close: () => void;
    error: (e: unknown) => void;
}) {
    const [key] = useState(() => crypto.randomUUID()),
        [choices, setChoices] = useState(setup),
        [lookup, setLookup] = useState('');
    const [date, setDate] = useState(record?.date ?? today()),
        [due, setDue] = useState(record?.due_date ?? ''),
        [order, setOrder] = useState(record?.order_id ?? ''),
        [tax, setTax] = useState(
            record?.snapshot?.tax_invoice ?? Boolean(setup.settings?.vat_registered),
        );
    const [buyer, setBuyer] = useState(record?.snapshot?.party.name ?? ''),
        [address, setAddress] = useState(record?.snapshot?.party.address ?? ''),
        [tin, setTin] = useState(record?.snapshot?.party.tin ?? ''),
        [supply, setSupply] = useState(record?.snapshot?.supply_date ?? ''),
        [evidence, setEvidence] = useState(record?.snapshot?.request_evidence ?? '');
    const [supplier, setSupplier] = useState(record?.party_id ?? ''),
        [reference, setReference] = useState(record?.supplier_reference ?? ''),
        [description, setDescription] = useState(record?.snapshot?.description ?? ''),
        [net, setNet] = useState(record?.net ?? ''),
        [vat, setVat] = useState(record?.vat ?? '0.00'),
        [vatEvidence, setVatEvidence] = useState(record?.snapshot?.vat_evidence ?? '');
    function submit(e: FormEvent) {
        e.preventDefault();
        save(
            {
                date,
                due_date: due,
                ...(record ? { revision: record.revision } : {}),
                ...(kind === 'invoice'
                    ? {
                          order_id: order,
                          tax_invoice: tax,
                          buyer_name: buyer,
                          buyer_address: address,
                          buyer_tin: tin || null,
                          supply_date: supply,
                          request_evidence: evidence || null,
                      }
                    : {
                          party_id: supplier,
                          supplier_reference: reference,
                          description,
                          net,
                          vat,
                          vat_evidence: vatEvidence || null,
                      }),
            },
            key,
        );
    }
    async function find() {
        try {
            setChoices(
                await requestJson<Setup>(
                    `/api/v1/finance/setup?company_id=${company}&q=${encodeURIComponent(lookup)}`,
                ),
            );
        } catch (e) {
            error(e);
        }
    }
    return (
        <section className="panel" aria-label="Finance document editor">
            <div className="panel-heading">
                <h2>
                    {record ? 'Edit draft' : 'New'}{' '}
                    {kind === 'bill' ? 'expense bill' : 'customer invoice'}
                </h2>
                <button className="secondary-button" onClick={close}>
                    Close editor
                </button>
            </div>
            <div className="sales-lookup">
                <label>
                    Find order or supplier
                    <input
                        maxLength={100}
                        value={lookup}
                        onChange={(e) => setLookup(e.target.value)}
                    />
                </label>
                <button className="secondary-button" onClick={() => void find()}>
                    Find matches
                </button>
            </div>
            <form onSubmit={submit}>
                <div className="form-grid">
                    <label>
                        Document date
                        <input
                            required
                            type="date"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                        />
                    </label>
                    <label>
                        Due date
                        <input
                            required
                            type="date"
                            min={date}
                            value={due}
                            onChange={(e) => setDue(e.target.value)}
                        />
                    </label>
                    {kind === 'invoice' ? (
                        <>
                            <label>
                                Confirmed Sales order
                                <select
                                    required
                                    value={order}
                                    onChange={(e) => setOrder(e.target.value)}
                                >
                                    <option value="">Choose order</option>
                                    {order && !choices.orders.some((o) => o.id === order) && (
                                        <option value={order}>
                                            {record?.snapshot?.order_number ?? order}
                                        </option>
                                    )}
                                    {choices.orders.map((o) => (
                                        <option key={o.id} value={o.id}>
                                            {o.number} · LKR {o.total}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                Purchaser registered name
                                <input
                                    required
                                    maxLength={160}
                                    value={buyer}
                                    onChange={(e) => setBuyer(e.target.value)}
                                />
                            </label>
                            <label>
                                Purchaser address
                                <textarea
                                    required
                                    maxLength={500}
                                    value={address}
                                    onChange={(e) => setAddress(e.target.value)}
                                />
                            </label>
                            <label>
                                Purchaser TIN
                                <input
                                    required={tax}
                                    pattern="[0-9]{9}"
                                    value={tin}
                                    onChange={(e) => setTin(e.target.value)}
                                />
                            </label>
                            <label>
                                Actual date of supply
                                <input
                                    required
                                    type="date"
                                    max={date}
                                    value={supply}
                                    onChange={(e) => setSupply(e.target.value)}
                                />
                            </label>
                            <label className="checkbox-label">
                                <input
                                    type="checkbox"
                                    checked={tax}
                                    onChange={(e) => setTax(e.target.checked)}
                                />
                                Registered-purchaser VAT tax invoice
                            </label>
                            {tax && (
                                <label>
                                    Timely written request / registration evidence
                                    <textarea
                                        required
                                        maxLength={500}
                                        value={evidence}
                                        onChange={(e) => setEvidence(e.target.value)}
                                    />
                                </label>
                            )}
                        </>
                    ) : (
                        <>
                            <label>
                                Supplier
                                <select
                                    required
                                    value={supplier}
                                    onChange={(e) => setSupplier(e.target.value)}
                                >
                                    <option value="">Choose supplier</option>
                                    {supplier &&
                                        !choices.suppliers.some((s) => s.id === supplier) && (
                                            <option value={supplier}>
                                                {record?.snapshot?.party.name ?? supplier}
                                            </option>
                                        )}
                                    {choices.suppliers.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.number} · {s.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                Supplier invoice reference
                                <input
                                    required
                                    maxLength={100}
                                    value={reference}
                                    onChange={(e) => setReference(e.target.value)}
                                />
                            </label>
                            <label>
                                Expense description
                                <input
                                    required
                                    maxLength={500}
                                    value={description}
                                    onChange={(e) => setDescription(e.target.value)}
                                />
                            </label>
                            <label>
                                Expense net amount
                                <input
                                    required
                                    inputMode="decimal"
                                    value={net}
                                    onChange={(e) => setNet(e.target.value)}
                                />
                            </label>
                            <label>
                                Recoverable input VAT
                                <input
                                    required
                                    inputMode="decimal"
                                    value={vat}
                                    onChange={(e) => setVat(e.target.value)}
                                />
                            </label>
                            <label>
                                Input VAT eligibility / source evidence
                                <textarea
                                    maxLength={500}
                                    value={vatEvidence}
                                    onChange={(e) => setVatEvidence(e.target.value)}
                                />
                            </label>
                        </>
                    )}
                </div>
                <p className="muted">
                    {kind === 'invoice'
                        ? 'Full-order billing only. Prices/taxes remain those of the confirmed order. Tax invoices require all-standard VAT18 lines and reviewed registration, request and supply dates. Other zero-VAT orders use commercial invoices.'
                        : 'Expense bills only. Recoverable VAT is your verified declaration, not an automatic credit claim. Capital/inventory bills and statutory adjustments are outside this baseline.'}
                </p>
                <button className="primary-button" disabled={busy}>
                    Save document draft
                </button>
            </form>
        </section>
    );
}
