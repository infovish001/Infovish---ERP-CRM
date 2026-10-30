import { useState, type FormEvent } from 'react';
import type { Setup, Entry, Line } from './types';
import { today } from './types';
export default function JournalForm({
    setup,
    record,
    busy,
    save,
    close,
}: {
    setup: Setup;
    record?: Entry;
    busy: boolean;
    save: (data: unknown, key: string) => void;
    close: () => void;
}) {
    const [key] = useState(() => crypto.randomUUID()),
        [date, setDate] = useState(record?.date ?? today()),
        [narration, setNarration] = useState(record?.narration ?? '');
    const blank = (): Line => ({ account_id: '', debit: '0.00', credit: '0.00', description: '' });
    const [lines, setLines] = useState<Line[]>(record?.lines ?? [blank(), blank()]);
    const setLine = (i: number, value: Partial<Line>) =>
        setLines((old) => old.map((l, n) => (n === i ? { ...l, ...value } : l)));
    function submit(e: FormEvent) {
        e.preventDefault();
        save(
            {
                date,
                narration,
                lines: lines.map((l) => ({
                    account_id: l.account_id,
                    debit: l.debit,
                    credit: l.credit,
                    description: l.description || null,
                })),
                ...(record ? { revision: record.revision } : {}),
            },
            key,
        );
    }
    return (
        <section className="panel" aria-label="Journal editor">
            <div className="panel-heading">
                <h2>{record ? 'Edit journal draft' : 'New journal draft'}</h2>
                <button className="secondary-button" onClick={close}>
                    Close editor
                </button>
            </div>
            <p>
                Enter cent-precision debit and credit strings. Posting requires equal positive
                totals, active accounts and an open period. Use invoice/payment workflows for AR/AP
                control accounts.
            </p>
            <form onSubmit={submit}>
                <div className="form-grid">
                    <label>
                        Journal date
                        <input
                            required
                            type="date"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                        />
                    </label>
                    <label>
                        Narration
                        <input
                            required
                            maxLength={500}
                            value={narration}
                            onChange={(e) => setNarration(e.target.value)}
                        />
                    </label>
                </div>
                {lines.map((l, i) => (
                    <fieldset className="sales-line" key={i}>
                        <legend>Journal line {i + 1}</legend>
                        <div className="form-grid">
                            <label>
                                Account {i + 1}
                                <select
                                    required
                                    value={l.account_id}
                                    onChange={(e) => setLine(i, { account_id: e.target.value })}
                                >
                                    <option value="">Choose account</option>
                                    {setup.accounts
                                        .filter((a) => a.active)
                                        .map((a) => (
                                            <option key={a.id} value={a.id}>
                                                {a.code} · {a.name}
                                            </option>
                                        ))}
                                </select>
                            </label>
                            <label>
                                Debit {i + 1}
                                <input
                                    required
                                    inputMode="decimal"
                                    value={l.debit}
                                    onChange={(e) => setLine(i, { debit: e.target.value })}
                                />
                            </label>
                            <label>
                                Credit {i + 1}
                                <input
                                    required
                                    inputMode="decimal"
                                    value={l.credit}
                                    onChange={(e) => setLine(i, { credit: e.target.value })}
                                />
                            </label>
                            <label>
                                Description {i + 1}
                                <input
                                    maxLength={300}
                                    value={l.description ?? ''}
                                    onChange={(e) => setLine(i, { description: e.target.value })}
                                />
                            </label>
                        </div>
                        <button
                            type="button"
                            className="text-button"
                            disabled={lines.length <= 2}
                            onClick={() => setLines((old) => old.filter((_, n) => n !== i))}
                        >
                            Remove journal line {i + 1}
                        </button>
                    </fieldset>
                ))}
                <div className="record-actions">
                    <button
                        type="button"
                        className="secondary-button"
                        disabled={lines.length >= 100}
                        onClick={() => setLines((old) => [...old, blank()])}
                    >
                        Add journal line
                    </button>
                    <button className="primary-button" disabled={busy}>
                        Save journal draft
                    </button>
                </div>
            </form>
        </section>
    );
}
