import { useEffect, useState, type FormEvent } from 'react';
import { requestJson } from '../../lib/request';
import type { Setup, Entry, Page } from './types';
import { today } from './types';
export default function PaymentForm({
    company,
    setup,
    document,
    busy,
    save,
    close,
    error,
}: {
    company: string;
    setup: Setup;
    document?: Entry;
    busy: boolean;
    save: (data: unknown, key: string) => void;
    close: () => void;
    error: (e: unknown) => void;
}) {
    const [key] = useState(() => crypto.randomUUID()),
        [documents, setDocuments] = useState<Entry[]>(document ? [document] : []),
        [doc, setDoc] = useState(document?.id ?? ''),
        [query, setQuery] = useState(''),
        [search, setSearch] = useState(''),
        [bank, setBank] = useState(''),
        [date, setDate] = useState(today()),
        [reference, setReference] = useState(''),
        [amount, setAmount] = useState('');
    useEffect(() => {
        if (document) return;
        let current = true;
        void Promise.all(
            ['invoices', 'bills'].map((k) =>
                requestJson<Page>(
                    `/api/v1/finance/${k}?company_id=${company}&q=${encodeURIComponent(search)}`,
                ),
            ),
        )
            .then((pages) => {
                if (current)
                    setDocuments(
                        pages
                            .flatMap((p) => p.data)
                            .filter((d) => d.state === 'posted' && d.outstanding !== '0.00'),
                    );
            })
            .catch((e) => {
                if (current) error(e);
            });
        return () => {
            current = false;
        };
    }, [company, search, document]);
    function submit(e: FormEvent) {
        e.preventDefault();
        save({ document_id: doc, bank_account_id: bank, date, reference, amount }, key);
    }
    return (
        <section className="panel" aria-label="Payment recorder">
            <div className="panel-heading">
                <h2>Record receipt or supplier payment</h2>
                <button className="secondary-button" onClick={close}>
                    Close editor
                </button>
            </div>
            <p>
                This records a reviewed bank transaction in the ledger. It does not transfer money
                or contact a bank.
            </p>
            {!document && (
                <div className="sales-lookup">
                    <label>
                        Find invoice or bill reference
                        <input
                            value={query}
                            maxLength={100}
                            onChange={(e) => setQuery(e.target.value)}
                        />
                    </label>
                    <button className="secondary-button" onClick={() => setSearch(query)}>
                        Find documents
                    </button>
                </div>
            )}
            <form onSubmit={submit}>
                <div className="form-grid">
                    <label>
                        Posted document
                        <select required value={doc} onChange={(e) => setDoc(e.target.value)}>
                            <option value="">Choose document</option>
                            {documents.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.number} · {d.kind} · outstanding {d.outstanding ?? d.total}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Bank account
                        <select required value={bank} onChange={(e) => setBank(e.target.value)}>
                            <option value="">Choose bank asset</option>
                            {setup.accounts
                                .filter((a) => a.active && a.bank)
                                .map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.code} · {a.name}
                                    </option>
                                ))}
                        </select>
                    </label>
                    <label>
                        Payment date
                        <input
                            type="date"
                            required
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                        />
                    </label>
                    <label>
                        Bank reference
                        <input
                            required
                            maxLength={100}
                            value={reference}
                            onChange={(e) => setReference(e.target.value)}
                        />
                    </label>
                    <label>
                        Payment amount
                        <input
                            required
                            inputMode="decimal"
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                        />
                    </label>
                </div>
                <button className="primary-button" disabled={busy}>
                    Record payment
                </button>
            </form>
        </section>
    );
}
