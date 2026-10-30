import { useState, type FormEvent } from 'react';
import { requestJson } from '../../lib/request';
import type { Setup, Entry, Line } from './types';
import { today } from './types';
type Choice = {
    id: string;
    debit: string;
    credit: string;
    date: string;
    number: string;
    narration: string;
};
export default function StatementForm({
    company,
    setup,
    record,
    busy,
    save,
    close,
    error,
}: {
    company: string;
    setup: Setup;
    record?: Entry;
    busy: boolean;
    save: (data: unknown, key: string) => void;
    close: () => void;
    error: (e: unknown) => void;
}) {
    const [key] = useState(() => crypto.randomUUID()),
        [bank, setBank] = useState(record?.bank_account_id ?? ''),
        [from, setFrom] = useState(record?.starts_on ?? ''),
        [to, setTo] = useState(record?.ends_on ?? today()),
        [opening, setOpening] = useState(record?.opening ?? ''),
        [closing, setClosing] = useState(record?.closing ?? ''),
        [confirmed, setConfirmed] = useState(record?.opening_cleared_confirmed ?? false),
        [choices, setChoices] = useState<Choice[]>([]);
    const blank = (): Line => ({ date: '', reference: '', amount: '', line_id: '' });
    const [lines, setLines] = useState<Line[]>(record?.lines ?? [blank()]);
    function setLine(i: number, update: Partial<Line>) {
        setLines((old) => old.map((l, n) => (n === i ? { ...l, ...update } : l)));
    }
    async function load() {
        try {
            setChoices(
                (
                    await requestJson<{ data: Choice[] }>(
                        `/api/v1/finance/bank-lines?company_id=${company}&account_id=${bank}&as_of=${to}`,
                    )
                ).data,
            );
        } catch (e) {
            error(e);
        }
    }
    function submit(e: FormEvent) {
        e.preventDefault();
        save(
            {
                bank_account_id: bank,
                starts_on: from,
                ends_on: to,
                opening,
                closing,
                opening_cleared_confirmed: confirmed,
                lines: lines.map((l) => ({
                    date: l.date,
                    reference: l.reference,
                    amount: l.amount,
                    line_id: l.line_id || null,
                })),
                ...(record ? { revision: record.revision } : {}),
            },
            key,
        );
    }
    return (
        <section className="panel" aria-label="Bank statement editor">
            <div className="panel-heading">
                <h2>{record ? 'Edit draft' : 'New'} bank statement</h2>
                <button className="secondary-button" onClick={close}>
                    Close editor
                </button>
            </div>
            <p>
                Enter the actual statement opening, signed movements and closing. Each nonzero line
                must match one ledger line before finalization. No balancing plug is created.
            </p>
            <form onSubmit={submit}>
                <div className="form-grid">
                    <label>
                        Statement bank account
                        <select
                            required
                            value={bank}
                            onChange={(e) => {
                                setBank(e.target.value);
                                setChoices([]);
                            }}
                        >
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
                        Statement starts on
                        <input
                            required
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </label>
                    <label>
                        Statement ends on
                        <input
                            required
                            type="date"
                            min={from}
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </label>
                    <label>
                        Opening bank balance
                        <input
                            required
                            inputMode="decimal"
                            value={opening}
                            onChange={(e) => setOpening(e.target.value)}
                        />
                    </label>
                    <label>
                        Closing bank balance
                        <input
                            required
                            inputMode="decimal"
                            value={closing}
                            onChange={(e) => setClosing(e.target.value)}
                        />
                    </label>
                </div>
                <label className="checkbox-label">
                    <input
                        type="checkbox"
                        checked={confirmed}
                        onChange={(e) => setConfirmed(e.target.checked)}
                    />
                    For the first statement, I confirm all book entries before its start form a
                    cleared opening baseline
                </label>
                <p className="muted">
                    Pre-baseline outstanding items are unsupported. Later statements continue from
                    the prior closing. Eligible unmatched ledger choices are limited to 500; use
                    supported statement windows and review earlier outstanding entries.
                </p>
                <button
                    type="button"
                    className="secondary-button"
                    disabled={!bank || !to}
                    onClick={() => void load()}
                >
                    Load unmatched bank entries
                </button>
                {lines.map((l, i) => (
                    <fieldset className="sales-line" key={i}>
                        <legend>Statement line {i + 1}</legend>
                        <div className="form-grid">
                            <label>
                                Statement line date {i + 1}
                                <input
                                    required
                                    type="date"
                                    min={from}
                                    max={to}
                                    value={l.date}
                                    onChange={(e) => setLine(i, { date: e.target.value })}
                                />
                            </label>
                            <label>
                                Statement reference {i + 1}
                                <input
                                    required
                                    maxLength={100}
                                    value={l.reference}
                                    onChange={(e) => setLine(i, { reference: e.target.value })}
                                />
                            </label>
                            <label>
                                Signed amount {i + 1}
                                <input
                                    required
                                    inputMode="decimal"
                                    value={l.amount}
                                    onChange={(e) => setLine(i, { amount: e.target.value })}
                                />
                            </label>
                            <label>
                                Matching book entry {i + 1}
                                <select
                                    value={l.line_id ?? ''}
                                    onChange={(e) => setLine(i, { line_id: e.target.value })}
                                >
                                    <option value="">Unmatched</option>
                                    {l.line_id && !choices.some((c) => c.id === l.line_id) && (
                                        <option value={l.line_id}>
                                            Stored match · {l.line_id}
                                        </option>
                                    )}
                                    {choices.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.number} · {c.date} ·{' '}
                                            {c.debit !== '0.00' ? c.debit : '-' + c.credit}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>
                        <button
                            type="button"
                            className="text-button"
                            onClick={() => setLines((old) => old.filter((_, n) => n !== i))}
                        >
                            Remove statement line {i + 1}
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
                        Add statement line
                    </button>
                    <button className="primary-button" disabled={busy}>
                        Save statement draft
                    </button>
                </div>
            </form>
        </section>
    );
}
