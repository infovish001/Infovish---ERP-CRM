import { useRef, useState, type FormEvent, type ReactNode } from 'react';
import type { Activity, CrmRecord, Pipeline, Save, Setup, Stage } from './types';

function Form({
    title,
    children,
    submit,
    onCancel,
}: {
    title: string;
    children: ReactNode;
    submit: () => Promise<void>;
    onCancel: () => void;
}) {
    const [busy, setBusy] = useState(false);
    async function send(event: FormEvent) {
        event.preventDefault();
        setBusy(true);
        try {
            await submit();
        } catch {
            /* The parent retains and displays the API errors. */
        } finally {
            setBusy(false);
        }
    }
    return (
        <section className="panel master-editor">
            <div className="panel-heading">
                <h2>{title}</h2>
                <button className="text-button" onClick={onCancel} disabled={busy}>
                    Cancel
                </button>
            </div>
            <form className="master-form" onSubmit={(e) => void send(e)}>
                {children}
                <div className="form-actions">
                    <button className="primary-button" disabled={busy}>
                        {busy ? 'Saving…' : 'Save'}
                    </button>
                    <button
                        className="secondary-button"
                        type="button"
                        disabled={busy}
                        onClick={onCancel}
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </section>
    );
}
function PipelineFields({
    setup,
    pipeline,
    setPipeline,
    stage,
    setStage,
}: {
    setup: Setup;
    pipeline: string;
    setPipeline: (id: string) => void;
    stage: string;
    setStage: (id: string) => void;
}) {
    const stages =
        setup.pipelines.find((p) => p.id === pipeline)?.stages.filter((s) => s.active) ?? [];
    return (
        <>
            <label>
                Pipeline
                <select
                    required
                    value={pipeline}
                    onChange={(e) => {
                        setPipeline(e.target.value);
                        setStage('');
                    }}
                >
                    <option value="">Choose pipeline</option>
                    {setup.pipelines
                        .filter((p) => p.active)
                        .map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                </select>
            </label>
            <label>
                Stage
                <select required value={stage} onChange={(e) => setStage(e.target.value)}>
                    <option value="">Choose stage</option>
                    {stages.map((s) => (
                        <option key={s.id} value={s.id}>
                            {s.name} · {s.probability}%
                        </option>
                    ))}
                </select>
            </label>
        </>
    );
}
export function RecordForm({
    kind,
    record,
    setup,
    currency,
    onSave,
    onCancel,
    searchCustomers,
}: {
    kind: 'lead' | 'opportunity';
    record?: CrmRecord;
    setup: Setup;
    currency: string;
    onSave: Save;
    onCancel: () => void;
    searchCustomers: (q: string) => void;
}) {
    const key = useRef(crypto.randomUUID());
    const [title, setTitle] = useState(record?.title ?? '');
    const [name, setName] = useState(record?.contact_name ?? '');
    const [email, setEmail] = useState(record?.email ?? '');
    const [phone, setPhone] = useState(record?.phone ?? '');
    const [source, setSource] = useState(record?.source ?? '');
    const [assigned, setAssigned] = useState(record?.assigned_to ?? '');
    const [amount, setAmount] = useState(record?.expected_amount ?? '');
    const [close, setClose] = useState(record?.expected_close ?? '');
    const [customer, setCustomer] = useState('');
    const [pipeline, setPipeline] = useState('');
    const [stage, setStage] = useState('');
    const [query, setQuery] = useState('');
    return (
        <Form
            title={`${record ? 'Edit' : 'New'} ${kind}`}
            onCancel={onCancel}
            submit={() =>
                onSave(
                    {
                        title,
                        assigned_to: assigned || null,
                        revision: record?.revision,
                        ...(kind === 'lead'
                            ? {
                                  contact_name: name,
                                  email: email || null,
                                  phone: phone || null,
                                  source: source || null,
                              }
                            : {
                                  expected_amount: amount || null,
                                  expected_close: close || null,
                                  ...(!record
                                      ? {
                                            contact_id: customer,
                                            pipeline_id: pipeline,
                                            stage_id: stage,
                                        }
                                      : {}),
                              }),
                    },
                    key.current,
                )
            }
        >
            <label>
                Title
                <input
                    required
                    maxLength={160}
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                />
            </label>
            <label>
                Assigned to
                <select value={assigned} onChange={(e) => setAssigned(e.target.value)}>
                    <option value="">Me</option>
                    {setup.assignees.map((a) => (
                        <option key={a.id} value={a.id}>
                            {a.name}
                        </option>
                    ))}
                </select>
            </label>
            {kind === 'lead' ? (
                <>
                    <label>
                        Contact name
                        <input
                            required
                            maxLength={160}
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                        />
                    </label>
                    <label>
                        Email
                        <input
                            type="email"
                            maxLength={254}
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                        />
                    </label>
                    <label>
                        Phone
                        <input
                            maxLength={40}
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                        />
                    </label>
                    <label>
                        Source
                        <input
                            maxLength={80}
                            value={source}
                            onChange={(e) => setSource(e.target.value)}
                        />
                    </label>
                </>
            ) : (
                <>
                    {!record && (
                        <>
                            <div className="customer-search">
                                <label>
                                    Find customer
                                    <input
                                        value={query}
                                        onChange={(e) => setQuery(e.target.value)}
                                        placeholder="Search name, email or reference"
                                    />
                                </label>
                                <button
                                    type="button"
                                    className="secondary-button"
                                    onClick={() => searchCustomers(query)}
                                >
                                    Find
                                </button>
                            </div>
                            <label>
                                Customer
                                <select
                                    required
                                    value={customer}
                                    onChange={(e) => setCustomer(e.target.value)}
                                >
                                    <option value="">Choose active customer</option>
                                    {setup.customers.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.number} · {c.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <PipelineFields
                                setup={setup}
                                pipeline={pipeline}
                                setPipeline={setPipeline}
                                stage={stage}
                                setStage={setStage}
                            />
                        </>
                    )}
                    <label>
                        Expected amount ({currency})
                        <input
                            inputMode="decimal"
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                            placeholder="Unknown if blank"
                        />
                        <small>Estimate only. Up to six decimal places.</small>
                    </label>
                    <label>
                        Expected close date
                        <input
                            type="date"
                            value={close}
                            onChange={(e) => setClose(e.target.value)}
                        />
                    </label>
                </>
            )}
        </Form>
    );
}
export function ConvertForm({
    record,
    setup,
    currency,
    onSave,
    onCancel,
    searchCustomers,
}: {
    record: CrmRecord;
    setup: Setup;
    currency: string;
    onSave: Save;
    onCancel: () => void;
    searchCustomers: (q: string) => void;
}) {
    const key = useRef(crypto.randomUUID());
    const [mode, setMode] = useState('new');
    const [customer, setCustomer] = useState('');
    const [pipeline, setPipeline] = useState('');
    const [stage, setStage] = useState('');
    const [amount, setAmount] = useState('');
    const [close, setClose] = useState('');
    const [query, setQuery] = useState(record.email ?? '');
    return (
        <Form
            title={`Convert ${record.number}`}
            onCancel={onCancel}
            submit={() =>
                onSave(
                    {
                        revision: record.revision,
                        customer_mode: mode,
                        contact_id: mode === 'existing' ? customer : null,
                        pipeline_id: pipeline,
                        stage_id: stage,
                        expected_amount: amount || null,
                        expected_close: close || null,
                    },
                    key.current,
                )
            }
        >
            <p className="form-wide">
                Creates one opportunity for {record.contact_name}. Customer reuse is an explicit
                choice.
            </p>
            <label>
                Customer action
                <select value={mode} onChange={(e) => setMode(e.target.value)}>
                    <option value="new">Create a new customer</option>
                    <option value="existing">Use an existing customer</option>
                </select>
            </label>
            {mode === 'existing' && (
                <>
                    <div className="customer-search">
                        <label>
                            Find customer
                            <input value={query} onChange={(e) => setQuery(e.target.value)} />
                        </label>
                        <button
                            type="button"
                            className="secondary-button"
                            onClick={() => searchCustomers(query)}
                        >
                            Find
                        </button>
                    </div>
                    <label>
                        Customer
                        <select
                            required
                            value={customer}
                            onChange={(e) => setCustomer(e.target.value)}
                        >
                            <option value="">Choose active customer</option>
                            {setup.customers.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.number} · {c.name}
                                </option>
                            ))}
                        </select>
                    </label>
                </>
            )}
            <PipelineFields
                setup={setup}
                pipeline={pipeline}
                setPipeline={setPipeline}
                stage={stage}
                setStage={setStage}
            />
            <label>
                Expected amount ({currency})
                <input
                    inputMode="decimal"
                    value={amount}
                    onChange={(e) => setAmount(e.target.value)}
                    placeholder="Unknown if blank"
                />
            </label>
            <label>
                Expected close date
                <input type="date" value={close} onChange={(e) => setClose(e.target.value)} />
            </label>
        </Form>
    );
}
export function PipelineForm({
    pipeline,
    onSave,
    onCancel,
}: {
    pipeline?: Pipeline;
    onSave: Save;
    onCancel: () => void;
}) {
    const key = useRef(crypto.randomUUID());
    const [name, setName] = useState(pipeline?.name ?? '');
    const [active, setActive] = useState(pipeline?.active ?? true);
    const [stages, setStages] = useState([{ name: '', probability: '' }]);
    return (
        <Form
            title={pipeline ? 'Edit pipeline' : 'New pipeline'}
            onCancel={onCancel}
            submit={() =>
                onSave(
                    {
                        name,
                        active,
                        revision: pipeline?.revision,
                        ...(!pipeline
                            ? {
                                  stages: stages.map((s) => ({
                                      name: s.name,
                                      probability: Number(s.probability),
                                  })),
                              }
                            : {}),
                    },
                    key.current,
                )
            }
        >
            <label>
                Pipeline name
                <input
                    required
                    maxLength={120}
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                />
            </label>
            <label className="checkbox-label">
                <input
                    type="checkbox"
                    checked={active}
                    onChange={(e) => setActive(e.target.checked)}
                />
                Active
            </label>
            {!pipeline && (
                <fieldset className="attribute-editor">
                    <legend>Ordered stages and explicit probabilities</legend>
                    {stages.map((stage, index) => (
                        <div className="crm-stage-input" key={index}>
                            <span>{index + 1}</span>
                            <label>
                                Stage name
                                <input
                                    required
                                    maxLength={80}
                                    value={stage.name}
                                    onChange={(e) =>
                                        setStages((rows) =>
                                            rows.map((row, i) =>
                                                i === index
                                                    ? { ...row, name: e.target.value }
                                                    : row,
                                            ),
                                        )
                                    }
                                />
                            </label>
                            <label>
                                Probability %
                                <input
                                    required
                                    type="number"
                                    min={0}
                                    max={100}
                                    step={1}
                                    value={stage.probability}
                                    onChange={(e) =>
                                        setStages((rows) =>
                                            rows.map((row, i) =>
                                                i === index
                                                    ? { ...row, probability: e.target.value }
                                                    : row,
                                            ),
                                        )
                                    }
                                />
                            </label>
                            <button
                                type="button"
                                className="text-button"
                                disabled={stages.length === 1}
                                onClick={() =>
                                    setStages((rows) => rows.filter((_, i) => i !== index))
                                }
                            >
                                Remove
                            </button>
                        </div>
                    ))}
                    <button
                        type="button"
                        className="secondary-button"
                        disabled={stages.length >= 12}
                        onClick={() =>
                            setStages((rows) => [...rows, { name: '', probability: '' }])
                        }
                    >
                        Add stage
                    </button>
                </fieldset>
            )}
        </Form>
    );
}
export function StageForm({
    stage,
    onSave,
    onCancel,
}: {
    stage?: Stage;
    onSave: Save;
    onCancel: () => void;
}) {
    const key = useRef(crypto.randomUUID());
    const [name, setName] = useState(stage?.name ?? '');
    const [probability, setProbability] = useState(stage ? String(stage.probability) : '');
    const [active, setActive] = useState(stage?.active ?? true);
    return (
        <Form
            title={stage ? 'Edit stage' : 'Add stage'}
            onCancel={onCancel}
            submit={() =>
                onSave(
                    { name, probability: Number(probability), active, revision: stage?.revision },
                    key.current,
                )
            }
        >
            <label>
                Stage name
                <input
                    required
                    maxLength={80}
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                />
            </label>
            <label>
                Probability %
                <input
                    required
                    type="number"
                    min={0}
                    max={100}
                    step={1}
                    value={probability}
                    onChange={(e) => setProbability(e.target.value)}
                />
            </label>
            <label className="checkbox-label">
                <input
                    type="checkbox"
                    checked={active}
                    onChange={(e) => setActive(e.target.checked)}
                />
                Active
            </label>
        </Form>
    );
}
export function ActivityForm({
    activity,
    onSave,
    onCancel,
}: {
    activity?: Activity;
    onSave: Save;
    onCancel: () => void;
}) {
    const key = useRef(crypto.randomUUID());
    const [subject, setSubject] = useState(activity?.subject ?? '');
    const [type, setType] = useState(activity?.type ?? 'task');
    const [note, setNote] = useState(activity?.note ?? '');
    const [due, setDue] = useState(
        activity
            ? new Date(
                  new Date(activity.due_at).getTime() -
                      new Date(activity.due_at).getTimezoneOffset() * 60000,
              )
                  .toISOString()
                  .slice(0, 16)
            : '',
    );
    return (
        <Form
            title={activity ? 'Edit activity' : 'Plan activity'}
            onCancel={onCancel}
            submit={() =>
                onSave(
                    {
                        subject,
                        type,
                        note: note || null,
                        due_at: new Date(due).toISOString(),
                        revision: activity?.revision,
                    },
                    key.current,
                )
            }
        >
            <label>
                Subject
                <input
                    required
                    maxLength={160}
                    value={subject}
                    onChange={(e) => setSubject(e.target.value)}
                />
            </label>
            <label>
                Type
                <select value={type} onChange={(e) => setType(e.target.value)}>
                    <option value="task">Task</option>
                    <option value="call">Call</option>
                    <option value="meeting">Meeting</option>
                </select>
            </label>
            <label>
                Due time (your local timezone)
                <input
                    required
                    type="datetime-local"
                    value={due}
                    onChange={(e) => setDue(e.target.value)}
                />
            </label>
            <label>
                Note
                <textarea maxLength={2000} value={note} onChange={(e) => setNote(e.target.value)} />
            </label>
        </Form>
    );
}
