import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import WorkspaceLayout from '../components/WorkspaceLayout';
import { RequestError, requestJson } from '../lib/request';
import {
    ActivityForm,
    ConvertForm,
    PipelineForm,
    RecordForm,
    StageForm,
} from '../features/crm/Forms';
import RecordDetail from '../features/crm/RecordDetail';
import type {
    Activity,
    Company,
    CrmRecord,
    Detail,
    Forecast,
    Page,
    Pipeline,
    Setup,
    Stage,
} from '../features/crm/types';

type Props = {
    tenant: { name: string };
    user: { id: string; name: string; role: string };
    companies: Company[];
    initialCompany: string | null;
    available: boolean;
    canManage: boolean;
    canAdmin: boolean;
};
type Editor = {
    type: 'record' | 'convert' | 'pipeline' | 'stage' | 'activity';
    record?: CrmRecord;
    pipeline?: Pipeline;
    stage?: Stage;
    activity?: Activity;
};
const emptySetup: Setup = { pipelines: [], assignees: [], customers: [] };

export default function CRM({
    tenant,
    user,
    companies,
    initialCompany,
    available,
    canManage,
    canAdmin,
}: Props) {
    const [company, setCompany] = useState(
        companies.some((c) => c.id === initialCompany) ? initialCompany! : (companies[0]?.id ?? ''),
    );
    const [tab, setTab] = useState<'leads' | 'opportunities' | 'pipelines'>('leads');
    const [board, setBoard] = useState(true);
    const [setup, setSetup] = useState<Setup>(emptySetup);
    const [page, setPage] = useState(1);
    const [query, setQuery] = useState('');
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [pipeline, setPipeline] = useState('');
    const [stage, setStage] = useState('');
    const [records, setRecords] = useState<Page>();
    const [forecast, setForecast] = useState<Forecast>();
    const [forecastLoading, setForecastLoading] = useState(false);
    const [selected, setSelected] = useState<string | null>(null);
    const [detail, setDetail] = useState<Detail>();
    const [editor, setEditor] = useState<Editor>();
    const [error, setError] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [notice, setNotice] = useState('');
    const [loading, setLoading] = useState(false);
    const [busy, setBusy] = useState(false);
    const [revision, setRevision] = useState(0);
    const kind = tab === 'leads' ? 'lead' : 'opportunity';
    const ready = available && Boolean(company);
    const currentCompany = companies.find((c) => c.id === company);
    function showError(e: unknown) {
        setError(e instanceof Error ? e.message : 'The request could not be completed.');
        setErrors(e instanceof RequestError ? e.errors : {});
    }
    useEffect(() => {
        if (!ready) return;
        let current = true;
        void requestJson<Setup>(`/api/v1/crm/setup?company_id=${company}`)
            .then((data) => {
                if (current) setSetup(data);
            })
            .catch((e) => {
                if (current) showError(e);
            });
        return () => {
            current = false;
        };
    }, [ready, company, revision]);
    useEffect(() => {
        if (!ready || tab === 'pipelines') return;
        let current = true;
        setLoading(true);
        setError('');
        setErrors({});
        const params = new URLSearchParams({
            company_id: company,
            page: String(page),
            q: search,
            status,
        });
        if (tab === 'opportunities') {
            if (pipeline) params.set('pipeline_id', pipeline);
            if (stage) params.set('stage_id', stage);
        }
        void requestJson<Page>(`/api/v1/crm/${tab}?${params}`)
            .then((data) => {
                if (current) setRecords(data);
            })
            .catch((e) => {
                if (current) {
                    setRecords(undefined);
                    showError(e);
                }
            })
            .finally(() => {
                if (current) setLoading(false);
            });
        return () => {
            current = false;
        };
    }, [ready, company, tab, page, search, status, pipeline, stage, revision]);
    useEffect(() => {
        if (!ready || tab !== 'opportunities') return;
        let current = true;
        setForecast(undefined);
        setForecastLoading(true);
        void requestJson<Forecast>(
            `/api/v1/crm/forecast?company_id=${company}${pipeline ? '&pipeline_id=' + pipeline : ''}`,
        )
            .then((data) => {
                if (current) setForecast(data);
            })
            .catch((e) => {
                if (current) {
                    setForecast(undefined);
                    showError(e);
                }
            })
            .finally(() => {
                if (current) setForecastLoading(false);
            });
        return () => {
            current = false;
        };
    }, [ready, company, tab, pipeline, revision]);
    useEffect(() => {
        if (!selected || !ready || tab === 'pipelines') {
            setDetail(undefined);
            return;
        }
        let current = true;
        setDetail(undefined);
        void requestJson<Detail>(`/api/v1/crm/${tab}/${selected}?company_id=${company}`)
            .then((data) => {
                if (current) setDetail(data);
            })
            .catch((e) => {
                if (current) showError(e);
            });
        return () => {
            current = false;
        };
    }, [ready, company, tab, selected, revision]);
    function changeTab(next: typeof tab) {
        setTab(next);
        setSelected(null);
        setEditor(undefined);
        setRecords(undefined);
        setPage(1);
        setStatus(next === 'opportunities' ? 'open' : '');
        setQuery('');
        setSearch('');
        setStage('');
        setError('');
        setNotice('');
        setErrors({});
    }
    async function mutate(
        url: string,
        method: string,
        body: Record<string, unknown>,
        key?: string,
    ) {
        setBusy(true);
        setError('');
        setErrors({});
        setNotice('');
        try {
            await requestJson(
                url,
                method,
                { ...body, company_id: company },
                key ? { 'Idempotency-Key': key } : {},
            );
            setEditor(undefined);
            setNotice('CRM record saved.');
            setRevision((v) => v + 1);
        } catch (e) {
            showError(e);
            throw e;
        } finally {
            setBusy(false);
        }
    }
    async function save(body: Record<string, unknown>, key: string) {
        if (!editor) return;
        const id = editor.record?.id;
        let url = '';
        let method = 'POST';
        switch (editor.type) {
            case 'record':
                url = `/api/v1/crm/${tab}${id ? '/' + id : ''}`;
                method = id ? 'PUT' : 'POST';
                break;
            case 'convert':
                url = `/api/v1/crm/leads/${id}/convert`;
                break;
            case 'pipeline':
                url = `/api/v1/crm/pipelines${editor.pipeline ? '/' + editor.pipeline.id : ''}`;
                method = editor.pipeline ? 'PUT' : 'POST';
                break;
            case 'stage':
                url = `/api/v1/crm/pipelines/${editor.pipeline?.id}/stages${editor.stage ? '/' + editor.stage.id : ''}`;
                method = editor.stage ? 'PUT' : 'POST';
                break;
            case 'activity':
                url = `/api/v1/crm/${kind}/${selected}/activities${editor.activity ? '/' + editor.activity.id : ''}`;
                method = editor.activity ? 'PUT' : 'POST';
                break;
        }
        await mutate(url, method, body, method === 'POST' ? key : undefined);
    }
    async function searchCustomers(q: string) {
        try {
            const data = await requestJson<Setup>(
                `/api/v1/crm/setup?company_id=${company}&q=${encodeURIComponent(q)}`,
            );
            setSetup(data);
        } catch (e) {
            showError(e);
        }
    }
    function select(row: CrmRecord) {
        setSelected(row.id);
        setEditor(undefined);
        setError('');
        setErrors({});
    }
    const shownPipelines = setup.pipelines.filter((p) => !pipeline || p.id === pipeline);
    const stages = setup.pipelines.find((p) => p.id === pipeline)?.stages ?? [];
    return (
        <WorkspaceLayout
            title="CRM"
            tenant={tenant.name}
            user={user.name}
            section="crm"
            financeVisible={['owner', 'administrator', 'auditor'].includes(user.role)}
        >
            <div className="page-heading">
                <div>
                    <p className="eyebrow">CUSTOMER RELATIONSHIPS</p>
                    <h1>Sales pipeline</h1>
                    <p>
                        Qualify leads, plan the next activity and move opportunities toward close.
                    </p>
                </div>
                {ready && canManage && tab !== 'pipelines' && (
                    <button
                        className="primary-button"
                        onClick={() => {
                            setEditor({ type: 'record' });
                            setSelected(null);
                            setError('');
                            setErrors({});
                        }}
                    >
                        + New {kind}
                    </button>
                )}
                {ready && canAdmin && tab === 'pipelines' && (
                    <button
                        className="primary-button"
                        onClick={() => setEditor({ type: 'pipeline' })}
                    >
                        + New pipeline
                    </button>
                )}
            </div>
            {!available ? (
                <section className="panel empty-state">
                    <h2>CRM is not active</h2>
                    <p>
                        CRM needs an entitled installation and the Contacts module. Ask your
                        workspace administrator to complete setup.
                    </p>
                    <Link className="secondary-button" href="/workspace/master-data">
                        View workspace modules
                    </Link>
                </section>
            ) : (
                <>
                    <div className="master-tabs" role="tablist" aria-label="CRM sections">
                        {(['leads', 'opportunities', 'pipelines'] as const).map((value) => (
                            <button
                                role="tab"
                                aria-selected={tab === value}
                                className={tab === value ? 'selected' : ''}
                                key={value}
                                onClick={() => changeTab(value)}
                            >
                                {value === 'leads'
                                    ? 'Leads'
                                    : value === 'opportunities'
                                      ? 'Opportunities'
                                      : 'Pipeline settings'}
                            </button>
                        ))}
                    </div>
                    <div className="master-toolbar">
                        <label>
                            Company
                            <select
                                value={company}
                                onChange={(e) => {
                                    setCompany(e.target.value);
                                    setSelected(null);
                                    setEditor(undefined);
                                    setSetup(emptySetup);
                                    setRecords(undefined);
                                    setForecast(undefined);
                                    setPage(1);
                                    setPipeline('');
                                    setStage('');
                                    setError('');
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
                        {tab !== 'pipelines' && (
                            <>
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        setSearch(query);
                                        setPage(1);
                                    }}
                                >
                                    <label className="sr-only" htmlFor="crm-search">
                                        Search CRM records
                                    </label>
                                    <input
                                        id="crm-search"
                                        placeholder="Search title or reference…"
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
                                        <option value="">All states</option>
                                        {(tab === 'leads'
                                            ? ['new', 'qualified', 'lost', 'converted']
                                            : ['open', 'won', 'lost']
                                        ).map((s) => (
                                            <option key={s} value={s}>
                                                {s}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </>
                        )}
                        {tab === 'opportunities' && (
                            <>
                                <label>
                                    Pipeline
                                    <select
                                        value={pipeline}
                                        onChange={(e) => {
                                            setPipeline(e.target.value);
                                            setStage('');
                                            setPage(1);
                                        }}
                                    >
                                        <option value="">All pipelines</option>
                                        {setup.pipelines.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                                {!p.active ? ' (inactive)' : ''}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label>
                                    Stage
                                    <select
                                        value={stage}
                                        onChange={(e) => {
                                            setStage(e.target.value);
                                            setPage(1);
                                        }}
                                        disabled={!pipeline}
                                    >
                                        <option value="">All stages</option>
                                        {stages.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <div className="crm-view-controls">
                                    <button
                                        className="secondary-button"
                                        aria-pressed={board}
                                        onClick={() => setBoard(true)}
                                    >
                                        Board
                                    </button>
                                    <button
                                        className="secondary-button"
                                        aria-pressed={!board}
                                        onClick={() => setBoard(false)}
                                    >
                                        List
                                    </button>
                                </div>
                            </>
                        )}
                    </div>
                    {error && (
                        <div className="form-error" role="alert">
                            {error}
                            {Object.entries(errors).map(([field, messages]) => (
                                <p key={field}>{messages.join(' ')}</p>
                            ))}
                            {!editor && (
                                <button
                                    className="text-button"
                                    onClick={() => setRevision((v) => v + 1)}
                                >
                                    Reload
                                </button>
                            )}
                        </div>
                    )}
                    {notice && (
                        <p className="success-message" role="status">
                            {notice}
                        </p>
                    )}
                    {!company && (
                        <section className="panel empty-state">
                            <h2>No company available</h2>
                            <p>Create a company in Organization, or ask the owner for access.</p>
                        </section>
                    )}
                    {ready && tab === 'opportunities' && forecastLoading && (
                        <p role="status">Loading estimates…</p>
                    )}
                    {ready && tab === 'opportunities' && forecast && (
                        <section className="crm-forecast" aria-label="Opportunity estimates">
                            <div>
                                <small>Known open value · {forecast.currency}</small>
                                <strong>{forecast.unweighted}</strong>
                            </div>
                            <div>
                                <small>Weighted estimate · {forecast.currency}</small>
                                <strong>{forecast.weighted}</strong>
                            </div>
                            <div>
                                <small>Authorized open opportunities</small>
                                <strong>{forecast.open_count}</strong>
                                <span>
                                    {forecast.known_count} known · {forecast.unknown_count} unknown
                                    amounts
                                </span>
                            </div>
                            <p>
                                {forecast.label} All authorized open records in the selected
                                pipeline; independent of search and state filters.
                            </p>
                        </section>
                    )}
                    {ready && editor?.type === 'record' && (
                        <RecordForm
                            key={`${company}-${editor.record?.id ?? 'new'}-${kind}`}
                            kind={kind}
                            record={editor.record}
                            setup={setup}
                            currency={currentCompany?.base_currency ?? ''}
                            onSave={save}
                            onCancel={() => setEditor(undefined)}
                            searchCustomers={(q) => void searchCustomers(q)}
                        />
                    )}
                    {ready && editor?.type === 'convert' && editor.record && (
                        <ConvertForm
                            record={editor.record}
                            setup={setup}
                            currency={currentCompany?.base_currency ?? ''}
                            onSave={save}
                            onCancel={() => setEditor(undefined)}
                            searchCustomers={(q) => void searchCustomers(q)}
                        />
                    )}
                    {ready && editor?.type === 'pipeline' && (
                        <PipelineForm
                            key={editor.pipeline?.id ?? 'new'}
                            pipeline={editor.pipeline}
                            onSave={save}
                            onCancel={() => setEditor(undefined)}
                        />
                    )}
                    {ready && editor?.type === 'stage' && (
                        <StageForm
                            key={editor.stage?.id ?? 'new'}
                            stage={editor.stage}
                            onSave={save}
                            onCancel={() => setEditor(undefined)}
                        />
                    )}
                    {ready && editor?.type === 'activity' && (
                        <ActivityForm
                            key={editor.activity?.id ?? 'new'}
                            activity={editor.activity}
                            onSave={save}
                            onCancel={() => setEditor(undefined)}
                        />
                    )}
                    {ready && detail && (
                        <RecordDetail
                            key={`${detail.record.id}-${detail.record.revision}-${revision}`}
                            kind={kind}
                            detail={detail}
                            setup={setup}
                            canManage={canManage}
                            canAdmin={canAdmin}
                            busy={busy}
                            onClose={() => setSelected(null)}
                            onEdit={() => setEditor({ type: 'record', record: detail.record })}
                            onConvert={() => setEditor({ type: 'convert', record: detail.record })}
                            onActivity={(activity) => setEditor({ type: 'activity', activity })}
                            onActivityState={(activity, state) =>
                                void mutate(
                                    `/api/v1/crm/activities/${activity.id}/transition`,
                                    'POST',
                                    { status: state, revision: activity.revision },
                                ).catch(() => {})
                            }
                            onAction={(action, reason, target) =>
                                void mutate(
                                    `/api/v1/crm/${tab}/${detail.record.id}/transition`,
                                    'POST',
                                    {
                                        action,
                                        reason: reason || null,
                                        stage_id: target || null,
                                        revision: detail.record.revision,
                                    },
                                ).catch(() => {})
                            }
                        />
                    )}
                    {ready && tab === 'pipelines' && (
                        <div className="crm-pipeline-settings">
                            {!setup.pipelines.length && (
                                <section className="panel empty-state">
                                    <h2>No pipeline configured</h2>
                                    <p>
                                        {canAdmin
                                            ? 'Create a pipeline with stage names and explicit probabilities.'
                                            : 'Ask your administrator to configure a pipeline.'}
                                    </p>
                                </section>
                            )}
                            {setup.pipelines.map((p) => (
                                <section className="panel" key={p.id}>
                                    <div className="panel-heading">
                                        <div>
                                            <h2>{p.name}</h2>
                                            <p>
                                                {p.active ? 'Active' : 'Inactive'} ·{' '}
                                                {p.stages.length} ordered stages
                                            </p>
                                        </div>
                                        {canAdmin && (
                                            <div className="button-row">
                                                <button
                                                    className="text-button"
                                                    onClick={() =>
                                                        setEditor({ type: 'pipeline', pipeline: p })
                                                    }
                                                >
                                                    Edit pipeline
                                                </button>
                                                {p.active && (
                                                    <button
                                                        className="secondary-button"
                                                        onClick={() =>
                                                            setEditor({
                                                                type: 'stage',
                                                                pipeline: p,
                                                            })
                                                        }
                                                    >
                                                        Add stage
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                    <ol className="crm-stage-list">
                                        {p.stages.map((s) => (
                                            <li key={s.id}>
                                                <span>
                                                    {s.position}. {s.name}{' '}
                                                    {!s.active ? '(inactive)' : ''}
                                                </span>
                                                <span>{s.probability}% probability</span>
                                                {canAdmin && p.active && (
                                                    <button
                                                        className="text-button"
                                                        onClick={() =>
                                                            setEditor({
                                                                type: 'stage',
                                                                pipeline: p,
                                                                stage: s,
                                                            })
                                                        }
                                                    >
                                                        Edit stage
                                                    </button>
                                                )}
                                            </li>
                                        ))}
                                    </ol>
                                </section>
                            ))}
                        </div>
                    )}
                    {ready &&
                        tab !== 'pipelines' &&
                        (loading ? (
                            <p className="empty-state" role="status">
                                Loading CRM records…
                            </p>
                        ) : !records?.data.length && !error ? (
                            <section className="panel empty-state">
                                <h2>No {tab} found</h2>
                                <p>
                                    {search || status
                                        ? 'Try another search or state filter.'
                                        : canManage
                                          ? 'Add your first lead or opportunity for this company.'
                                          : 'No assigned records are available in this scope.'}
                                </p>
                            </section>
                        ) : tab === 'opportunities' && board ? (
                            <>
                                <p className="muted-label">
                                    Board shows the current page ({records?.data.length ?? 0} of{' '}
                                    {records?.total ?? 0} matching opportunities).
                                </p>
                                <div className="crm-board">
                                    {shownPipelines.flatMap((p) =>
                                        p.stages.map((s) => (
                                            <section className="crm-column" key={s.id}>
                                                <div className="crm-column-heading">
                                                    <h2>
                                                        {p.name} · {s.name}
                                                    </h2>
                                                    <span>
                                                        {records?.data.filter(
                                                            (r) => r.stage_id === s.id,
                                                        ).length ?? 0}
                                                    </span>
                                                </div>
                                                <p>
                                                    {s.probability}% configured probability
                                                    {!s.active ? ' · Inactive' : ''}
                                                </p>
                                                {records?.data
                                                    .filter((r) => r.stage_id === s.id)
                                                    .map((row) => (
                                                        <button
                                                            className="crm-card"
                                                            key={row.id}
                                                            onClick={() => select(row)}
                                                        >
                                                            <small>
                                                                {row.number} · {row.status}
                                                            </small>
                                                            <strong>{row.title}</strong>
                                                            <span>
                                                                {row.expected_amount === null
                                                                    ? 'Amount unknown'
                                                                    : `${row.currency} ${row.expected_amount}`}
                                                            </span>
                                                            <small>
                                                                {setup.assignees.find(
                                                                    (a) => a.id === row.assigned_to,
                                                                )?.name ?? 'Workspace member'}
                                                                {row.expected_close
                                                                    ? ' · ' + row.expected_close
                                                                    : ''}
                                                            </small>
                                                        </button>
                                                    ))}
                                            </section>
                                        )),
                                    )}
                                </div>
                            </>
                        ) : (
                            <section className="panel">
                                <div className="table-wrap">
                                    <table className="data-table">
                                        <thead>
                                            <tr>
                                                <th>Reference / title</th>
                                                <th>
                                                    {tab === 'leads'
                                                        ? 'Contact / email'
                                                        : 'Expected amount'}
                                                </th>
                                                <th>Assigned to</th>
                                                <th>State</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {records?.data.map((row) => (
                                                <tr key={row.id}>
                                                    <td>
                                                        <small>{row.number}</small>
                                                        <strong>{row.title}</strong>
                                                    </td>
                                                    <td>
                                                        {tab === 'leads' ? (
                                                            <>
                                                                {row.contact_name}
                                                                <small>{row.email}</small>
                                                            </>
                                                        ) : row.expected_amount === null ? (
                                                            'Unknown'
                                                        ) : (
                                                            `${row.currency} ${row.expected_amount}`
                                                        )}
                                                    </td>
                                                    <td>
                                                        {setup.assignees.find(
                                                            (a) => a.id === row.assigned_to,
                                                        )?.name ?? 'Workspace member'}
                                                    </td>
                                                    <td>
                                                        <span
                                                            className={`status-pill ${row.status === 'lost' ? 'unavailable' : 'ready'}`}
                                                        >
                                                            {row.status}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button
                                                            className="text-button"
                                                            onClick={() => select(row)}
                                                            aria-label={`Open ${row.title}`}
                                                        >
                                                            Open
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        ))}
                    {ready && tab !== 'pipelines' && records && (
                        <div className="pagination">
                            <span>
                                Page {records.current_page} · {records.total} matching records
                            </span>
                            <button
                                className="secondary-button"
                                disabled={!records.prev_page_url || loading}
                                onClick={() => setPage((p) => p - 1)}
                            >
                                Previous
                            </button>
                            <button
                                className="secondary-button"
                                disabled={!records.next_page_url || loading}
                                onClick={() => setPage((p) => p + 1)}
                            >
                                Next
                            </button>
                        </div>
                    )}
                </>
            )}
        </WorkspaceLayout>
    );
}
