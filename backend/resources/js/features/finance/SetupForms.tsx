import { useState, type FormEvent } from 'react';
import type { Setup, Account, Period } from './types';
type Save = (path: string, data: unknown, key?: string) => void;
export default function SetupForms({
    setup,
    busy,
    save,
}: {
    setup: Setup;
    busy: boolean;
    save: Save;
}) {
    const [code, setCode] = useState(''),
        [name, setName] = useState(''),
        [type, setType] = useState('asset'),
        [bank, setBank] = useState(false),
        [account, setAccount] = useState<Account>();
    const [periodName, setPeriodName] = useState(''),
        [from, setFrom] = useState(''),
        [to, setTo] = useState('');
    const [lock, setLock] = useState<Period>(),
        [reason, setReason] = useState('');
    const [accountKey] = useState(() => crypto.randomUUID()),
        [periodKey] = useState(() => crypto.randomUUID());
    const [settings, setSettings] = useState({
        ...setup.settings,
        revision: setup.settings?.revision ?? 0,
        seller_name: setup.settings?.seller_name ?? '',
        seller_address: setup.settings?.seller_address ?? '',
        vat_registered: setup.settings?.vat_registered ?? false,
        tin: setup.settings?.tin ?? '',
        vat_registration: setup.settings?.vat_registration ?? '',
        vat_effective_from: setup.settings?.vat_effective_from ?? '',
        issuer_code: setup.settings?.issuer_code ?? '',
    });
    function submitAccount(e: FormEvent) {
        e.preventDefault();
        save(
            `/accounts${account ? '/' + account.id : ''}`,
            {
                code,
                name,
                type,
                bank,
                active: account?.active ?? true,
                ...(account ? { revision: account.revision } : {}),
            },
            account ? undefined : accountKey,
        );
    }
    return (
        <div className="finance-setup">
            <section className="panel">
                <h2>Chart of accounts</h2>
                <p>
                    Enter your reviewed accounts. Codes, classifications and bank flags remain
                    stable. No chart or opening balance is assumed.
                </p>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {setup.accounts.map((a) => (
                                <tr key={a.id}>
                                    <td>{a.code}</td>
                                    <td>{a.name}</td>
                                    <td>
                                        {a.type}
                                        {a.bank ? ' · bank' : ''}
                                    </td>
                                    <td>{a.active ? 'Active' : 'Inactive'}</td>
                                    <td>
                                        <button
                                            className="text-button"
                                            onClick={() => {
                                                setAccount(a);
                                                setCode(a.code);
                                                setName(a.name);
                                                setType(a.type);
                                                setBank(a.bank);
                                            }}
                                        >
                                            Edit name
                                        </button>
                                        <button
                                            className="text-button"
                                            disabled={busy}
                                            onClick={() =>
                                                save('/accounts/' + a.id, {
                                                    ...a,
                                                    active: !a.active,
                                                })
                                            }
                                        >
                                            {a.active ? 'Deactivate' : 'Activate'}
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <form onSubmit={submitAccount}>
                    <h3>{account ? 'Edit account' : 'Create account'}</h3>
                    <div className="form-grid">
                        <label>
                            Account code
                            <input
                                required
                                maxLength={20}
                                value={code}
                                disabled={Boolean(account)}
                                onChange={(e) => setCode(e.target.value.toUpperCase())}
                            />
                        </label>
                        <label>
                            Account name
                            <input
                                required
                                maxLength={160}
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                            />
                        </label>
                        <label>
                            Account type
                            <select
                                value={type}
                                disabled={Boolean(account)}
                                onChange={(e) => setType(e.target.value)}
                            >
                                {['asset', 'liability', 'equity', 'income', 'expense'].map((t) => (
                                    <option key={t}>{t}</option>
                                ))}
                            </select>
                        </label>
                        <label className="checkbox-label">
                            <input
                                type="checkbox"
                                checked={bank}
                                disabled={Boolean(account)}
                                onChange={(e) => setBank(e.target.checked)}
                            />
                            Bank asset account
                        </label>
                    </div>
                    <button className="primary-button" disabled={busy}>
                        Save account
                    </button>
                    {account && (
                        <button
                            type="button"
                            className="secondary-button"
                            onClick={() => {
                                setAccount(undefined);
                                setCode('');
                                setName('');
                            }}
                        >
                            New account
                        </button>
                    )}
                </form>
            </section>
            <section className="panel">
                <h2>Accounting periods</h2>
                <p>
                    Periods cannot overlap. Locked periods reject posting; only the owner can reopen
                    one with a reason.
                </p>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Dates</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {setup.periods.map((p) => (
                                <tr key={p.id}>
                                    <td>{p.name}</td>
                                    <td>
                                        {p.starts_on} – {p.ends_on}
                                    </td>
                                    <td>{p.locked ? 'Locked' : 'Open'}</td>
                                    <td>
                                        <button
                                            className="text-button"
                                            onClick={() => {
                                                setLock(p);
                                                setReason('');
                                            }}
                                        >
                                            {p.locked ? 'Review reopening' : 'Lock period'}
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {lock && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            save('/periods/' + lock.id + '/lock', {
                                revision: lock.revision,
                                locked: !lock.locked,
                                reason,
                            });
                            setLock(undefined);
                        }}
                    >
                        <label>
                            Period action reason
                            <input
                                required
                                maxLength={500}
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                            />
                        </label>
                        <button className="secondary-button" disabled={busy}>
                            {lock.locked ? 'Reopen' : 'Lock'} {lock.name}
                        </button>
                    </form>
                )}
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        save(
                            '/periods',
                            { name: periodName, starts_on: from, ends_on: to },
                            periodKey,
                        );
                    }}
                >
                    <div className="form-grid">
                        <label>
                            Period name
                            <input
                                required
                                value={periodName}
                                maxLength={100}
                                onChange={(e) => setPeriodName(e.target.value)}
                            />
                        </label>
                        <label>
                            Starts on
                            <input
                                required
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                            />
                        </label>
                        <label>
                            Ends on
                            <input
                                required
                                type="date"
                                min={from}
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                            />
                        </label>
                    </div>
                    <button className="primary-button" disabled={busy}>
                        Create period
                    </button>
                </form>
            </section>
            <section className="panel">
                <h2>Posting mappings and issuer details</h2>
                <p>
                    Mappings must use distinct, active accounts of the required types. After the
                    first posting, control mappings cannot change.
                </p>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        save('/settings', {
                            ...settings,
                            tin: settings.tin || null,
                            vat_registration: settings.vat_registration || null,
                            vat_effective_from: settings.vat_effective_from || null,
                        });
                    }}
                >
                    <div className="form-grid">
                        {Object.entries({
                            receivable: 'asset',
                            payable: 'liability',
                            revenue: 'income',
                            expense: 'expense',
                            vat_input: 'asset',
                            vat_output: 'liability',
                        }).map(([key, type]) => (
                            <label key={key}>
                                {key.replaceAll('_', ' ')} account
                                <select
                                    required
                                    value={(settings[key as keyof typeof settings] as string) ?? ''}
                                    onChange={(e) =>
                                        setSettings((s) => ({ ...s, [key]: e.target.value }))
                                    }
                                >
                                    <option value="">Choose {type} account</option>
                                    {setup.accounts
                                        .filter((a) => a.active && !a.bank && a.type === type)
                                        .map((a) => (
                                            <option key={a.id} value={a.id}>
                                                {a.code} · {a.name}
                                            </option>
                                        ))}
                                </select>
                            </label>
                        ))}
                        <label>
                            Seller legal name
                            <input
                                required
                                value={settings.seller_name}
                                maxLength={160}
                                onChange={(e) =>
                                    setSettings((s) => ({ ...s, seller_name: e.target.value }))
                                }
                            />
                        </label>
                        <label>
                            Seller registered address
                            <textarea
                                required
                                value={settings.seller_address}
                                maxLength={500}
                                onChange={(e) =>
                                    setSettings((s) => ({ ...s, seller_address: e.target.value }))
                                }
                            />
                        </label>
                        <label>
                            Invoice issuer code
                            <input
                                required
                                value={settings.issuer_code}
                                maxLength={15}
                                onChange={(e) =>
                                    setSettings((s) => ({
                                        ...s,
                                        issuer_code: e.target.value.toUpperCase(),
                                    }))
                                }
                            />
                        </label>
                        <label className="checkbox-label">
                            <input
                                type="checkbox"
                                checked={settings.vat_registered}
                                onChange={(e) =>
                                    setSettings((s) => ({ ...s, vat_registered: e.target.checked }))
                                }
                            />
                            Seller is VAT registered
                        </label>
                        {settings.vat_registered && (
                            <>
                                <label>
                                    Seller TIN (9 digits)
                                    <input
                                        required
                                        pattern="[0-9]{9}"
                                        value={settings.tin}
                                        onChange={(e) =>
                                            setSettings((s) => ({ ...s, tin: e.target.value }))
                                        }
                                    />
                                </label>
                                <label>
                                    VAT registration number
                                    <input
                                        required
                                        maxLength={30}
                                        value={settings.vat_registration}
                                        onChange={(e) =>
                                            setSettings((s) => ({
                                                ...s,
                                                vat_registration: e.target.value,
                                            }))
                                        }
                                    />
                                </label>
                                <label>
                                    VAT effective from
                                    <input
                                        required
                                        type="date"
                                        value={settings.vat_effective_from}
                                        onChange={(e) =>
                                            setSettings((s) => ({
                                                ...s,
                                                vat_effective_from: e.target.value,
                                            }))
                                        }
                                    />
                                </label>
                            </>
                        )}
                    </div>
                    <p className="muted">
                        Actual company registration and accounting/tax applicability require review.
                        Ordinary LKR workflows only; no automatic tax eligibility or statutory
                        reporting.
                    </p>
                    <button className="primary-button" disabled={busy}>
                        Save Finance settings
                    </button>
                </form>
            </section>
        </div>
    );
}
