import { useEffect, useState } from 'react';
import { requestJson } from '../../lib/request';
import type { Report, Setup, Page } from './types';
import { today } from './types';
export default function ReportsView({
    company,
    setup,
    revision,
    error,
}: {
    company: string;
    setup: Setup;
    revision: number;
    error: (e: unknown) => void;
}) {
    const [from, setFrom] = useState(today().slice(0, 4) + '-01-01'),
        [to, setTo] = useState(today()),
        [range, setRange] = useState({ from, to }),
        [data, setData] = useState<Report>(),
        [loading, setLoading] = useState(false),
        [account, setAccount] = useState(''),
        [ledger, setLedger] = useState<Page>(),
        [page, setPage] = useState(1);
    useEffect(() => {
        let current = true;
        setLoading(true);
        setData(undefined);
        void requestJson<Report>(
            `/api/v1/finance/reports?company_id=${company}&from=${range.from}&to=${range.to}`,
        )
            .then((r) => {
                if (current) setData(r);
            })
            .catch((e) => {
                if (current) error(e);
            })
            .finally(() => {
                if (current) setLoading(false);
            });
        return () => {
            current = false;
        };
    }, [company, range, revision]);
    useEffect(() => {
        if (!account) return;
        let current = true;
        setLedger(undefined);
        void requestJson<Page>(
            `/api/v1/finance/ledger?company_id=${company}&account_id=${account}&from=${range.from}&to=${range.to}&page=${page}`,
        )
            .then((r) => {
                if (current) setLedger(r);
            })
            .catch((e) => {
                if (current) error(e);
            });
        return () => {
            current = false;
        };
    }, [company, range, revision, account, page]);
    const t = data?.trial;
    return (
        <section className="panel" aria-label="Finance reports">
            <h2>Posted ledger reports</h2>
            <p>
                Management views in LKR. These are not complete statutory financial statements or
                tax returns.
            </p>
            <form
                className="master-toolbar"
                onSubmit={(e) => {
                    e.preventDefault();
                    setRange({ from, to });
                    setPage(1);
                }}
            >
                <label>
                    Report from
                    <input
                        required
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                    />
                </label>
                <label>
                    Report to
                    <input
                        required
                        type="date"
                        min={from}
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                    />
                </label>
                <button className="secondary-button">Run reports</button>
            </form>
            {loading && <p role="status">Loading posted reports…</p>}
            {t && (
                <>
                    <h3>Trial balance</h3>
                    <p>
                        Period debits {t.period_debit} · credits {t.period_credit} · difference{' '}
                        {t.period_difference}
                    </p>
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Opening (debit − credit)</th>
                                    <th>Period debit</th>
                                    <th>Period credit</th>
                                    <th>Closing (debit − credit)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {t.accounts.map((a) => (
                                    <tr key={a.id}>
                                        <td>
                                            {a.code} · {a.name}
                                        </td>
                                        <td>{a.opening}</td>
                                        <td>{a.debit}</td>
                                        <td>{a.credit}</td>
                                        <td>{a.closing}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="sales-document-meta">
                        <div>
                            <h3>Period performance</h3>
                            <dl className="sales-totals">
                                <div>
                                    <dt>Income</dt>
                                    <dd>{t.income}</dd>
                                </div>
                                <div>
                                    <dt>Expenses</dt>
                                    <dd>{t.expense}</dd>
                                </div>
                                <div>
                                    <dt>Period result</dt>
                                    <dd>{t.period_result}</dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h3>Financial position at {t.to}</h3>
                            <dl className="sales-totals">
                                <div>
                                    <dt>Assets</dt>
                                    <dd>{t.assets}</dd>
                                </div>
                                <div>
                                    <dt>Liabilities</dt>
                                    <dd>{t.liabilities}</dd>
                                </div>
                                <div>
                                    <dt>Equity accounts</dt>
                                    <dd>{t.equity}</dd>
                                </div>
                                <div>
                                    <dt>Cumulative unclosed result</dt>
                                    <dd>{t.cumulative_unclosed_result}</dd>
                                </div>
                                <div>
                                    <dt>Position difference</dt>
                                    <dd>{t.position_difference}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    <h3>Receivable / payable aging at {data.aging.as_of ?? t.to}</h3>
                    <p>
                        {data.aging.open_count} open documents; first 100 shown. Bucket totals cover
                        all open documents.
                    </p>
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Current</th>
                                    <th>1–30 days</th>
                                    <th>31–60</th>
                                    <th>61–90</th>
                                    <th>Over 90</th>
                                </tr>
                            </thead>
                            <tbody>
                                {Object.entries(data.aging.buckets).map(([kind, b]) => (
                                    <tr key={kind}>
                                        <td>{kind}</td>
                                        <td>{b.current}</td>
                                        <td>{b['1_30']}</td>
                                        <td>{b['31_60']}</td>
                                        <td>{b['61_90']}</td>
                                        <td>{b['90_plus']}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <table>
                            <thead>
                                <tr>
                                    <th>Reference / party</th>
                                    <th>Due date</th>
                                    <th>Outstanding</th>
                                    <th>Bucket</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.aging.documents.map((d) => (
                                    <tr key={d.id}>
                                        <td>
                                            {d.number} · {d.party_name}
                                        </td>
                                        <td>{d.due_date}</td>
                                        <td>{d.outstanding}</td>
                                        <td>{d.bucket}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
            <h3>Account ledger</h3>
            <label>
                Ledger account
                <select
                    value={account}
                    onChange={(e) => {
                        setAccount(e.target.value);
                        setPage(1);
                    }}
                >
                    <option value="">Choose account</option>
                    {setup.accounts.map((a) => (
                        <option key={a.id} value={a.id}>
                            {a.code} · {a.name}
                        </option>
                    ))}
                </select>
            </label>
            {ledger && (
                <>
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date / journal</th>
                                    <th>Narration</th>
                                    <th>Debit</th>
                                    <th>Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                {ledger.data.map((l) => (
                                    <tr key={l.id}>
                                        <td>
                                            {l.date} · {l.number}
                                        </td>
                                        <td>{l.narration}</td>
                                        <td>{l.debit}</td>
                                        <td>{l.credit}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="pagination">
                        <span>Page {ledger.current_page}</span>
                        <button
                            className="secondary-button"
                            disabled={!ledger.prev_page_url}
                            onClick={() => setPage((p) => p - 1)}
                        >
                            Previous ledger page
                        </button>
                        <button
                            className="secondary-button"
                            disabled={!ledger.next_page_url}
                            onClick={() => setPage((p) => p + 1)}
                        >
                            Next ledger page
                        </button>
                    </div>
                </>
            )}
        </section>
    );
}
