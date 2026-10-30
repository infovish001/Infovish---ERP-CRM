import { useState } from 'react';
import type { Activity, Detail, Setup } from './types';

export default function RecordDetail({
    kind,
    detail,
    setup,
    canManage,
    canAdmin,
    busy,
    onEdit,
    onConvert,
    onActivity,
    onActivityState,
    onAction,
    onClose,
}: {
    kind: 'lead' | 'opportunity';
    detail: Detail;
    setup: Setup;
    canManage: boolean;
    canAdmin: boolean;
    busy: boolean;
    onEdit: () => void;
    onConvert: () => void;
    onActivity: (activity?: Activity) => void;
    onActivityState: (activity: Activity, status: string) => void;
    onAction: (action: string, reason: string, stage: string) => void;
    onClose: () => void;
}) {
    const record = detail.record;
    const [reason, setReason] = useState('');
    const [stage, setStage] = useState(record.stage_id ?? '');
    const open = ['new', 'qualified', 'open'].includes(record.status);
    const pipeline = setup.pipelines.find((p) => p.id === record.pipeline_id);
    const stages = pipeline?.stages.filter((s) => s.active) ?? [];
    return (
        <section className="panel crm-detail" aria-label="Record details">
            <div className="panel-heading">
                <div>
                    <p>
                        {record.number} · {record.status}
                    </p>
                    <h2>{record.title}</h2>
                </div>
                <button className="text-button" onClick={onClose}>
                    Close details
                </button>
            </div>
            <div className="crm-detail-body">
                <dl className="crm-facts">
                    <div>
                        <dt>Assigned to</dt>
                        <dd>
                            {setup.assignees.find((a) => a.id === record.assigned_to)?.name ??
                                'Workspace member'}
                        </dd>
                    </div>
                    {kind === 'lead' ? (
                        <>
                            <div>
                                <dt>Contact</dt>
                                <dd>{record.contact_name}</dd>
                            </div>
                            <div>
                                <dt>Email</dt>
                                <dd>{record.email || 'Not provided'}</dd>
                            </div>
                            <div>
                                <dt>Phone / source</dt>
                                <dd>
                                    {record.phone || '—'} · {record.source || 'Not specified'}
                                </dd>
                            </div>
                        </>
                    ) : (
                        <>
                            <div>
                                <dt>Expected amount</dt>
                                <dd>
                                    {record.expected_amount === null
                                        ? 'Unknown'
                                        : `${record.currency} ${record.expected_amount}`}
                                </dd>
                            </div>
                            <div>
                                <dt>Pipeline / stage</dt>
                                <dd>
                                    {pipeline?.name} ·{' '}
                                    {pipeline?.stages.find((s) => s.id === record.stage_id)?.name}
                                </dd>
                            </div>
                            <div>
                                <dt>Expected close</dt>
                                <dd>{record.expected_close || 'Not set'}</dd>
                            </div>
                        </>
                    )}
                </dl>
                {kind === 'lead' && record.status === 'converted' && (
                    <p className="scope-note">
                        Converted. The customer and opportunity links are preserved; this lead is
                        read-only.
                    </p>
                )}
                {canManage && (
                    <div className="crm-actions">
                        {open && (
                            <button className="secondary-button" disabled={busy} onClick={onEdit}>
                                Edit record
                            </button>
                        )}
                        {kind === 'lead' && record.status === 'new' && (
                            <button
                                className="secondary-button"
                                disabled={busy}
                                onClick={() => onAction('qualify', reason, stage)}
                            >
                                Qualify lead
                            </button>
                        )}
                        {kind === 'lead' && record.status === 'qualified' && canAdmin && (
                            <button className="primary-button" disabled={busy} onClick={onConvert}>
                                Convert to opportunity
                            </button>
                        )}
                        {kind === 'opportunity' && open && (
                            <>
                                <label>
                                    Move to stage
                                    <select
                                        value={stage}
                                        onChange={(e) => setStage(e.target.value)}
                                    >
                                        <option value="">Choose stage</option>
                                        {stages.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.name} · {s.probability}%
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <button
                                    className="secondary-button"
                                    disabled={busy || !stage || !pipeline?.active}
                                    onClick={() => onAction('move', reason, stage)}
                                >
                                    Move stage
                                </button>
                                <button
                                    className="secondary-button"
                                    disabled={busy}
                                    onClick={() => onAction('won', reason, stage)}
                                >
                                    Mark won
                                </button>
                            </>
                        )}
                        {(open || (canAdmin && record.status !== 'converted')) && (
                            <label className="crm-reason">
                                Reason for loss or reopening
                                <textarea
                                    maxLength={500}
                                    value={reason}
                                    onChange={(e) => setReason(e.target.value)}
                                />
                            </label>
                        )}
                        {open && (
                            <button
                                className="secondary-button"
                                disabled={busy}
                                onClick={() => onAction('lost', reason, stage)}
                            >
                                Mark lost
                            </button>
                        )}
                        {canAdmin && !open && record.status !== 'converted' && (
                            <>
                                {kind === 'opportunity' && (
                                    <label>
                                        Reopen stage
                                        <select
                                            value={stage}
                                            onChange={(e) => setStage(e.target.value)}
                                        >
                                            <option value="">Choose stage</option>
                                            {stages.map((s) => (
                                                <option key={s.id} value={s.id}>
                                                    {s.name}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                )}
                                <button
                                    className="secondary-button"
                                    disabled={
                                        busy ||
                                        (kind === 'opportunity' && (!stage || !pipeline?.active))
                                    }
                                    onClick={() => onAction('reopen', reason, stage)}
                                >
                                    Reopen record
                                </button>
                            </>
                        )}
                    </div>
                )}
                <div className="crm-section-heading">
                    <h3>Activities</h3>
                    <p className="muted-label">Up to 100 planned and recent activities.</p>
                    {canManage && open && (
                        <button className="secondary-button" onClick={() => onActivity()}>
                            Plan activity
                        </button>
                    )}
                </div>
                {!detail.activities.length ? (
                    <p>No activities planned.</p>
                ) : (
                    <ul className="crm-activity-list">
                        {detail.activities.map((activity) => (
                            <li key={activity.id}>
                                <div>
                                    <strong>{activity.subject}</strong>
                                    <small>
                                        {activity.type} · {activity.status} · Due{' '}
                                        {new Date(activity.due_at).toLocaleString()}
                                    </small>
                                    {activity.note && <p>{activity.note}</p>}
                                </div>
                                {canManage && activity.status === 'planned' && (
                                    <div className="button-row">
                                        {open && (
                                            <button
                                                className="text-button"
                                                onClick={() => onActivity(activity)}
                                            >
                                                Edit activity
                                            </button>
                                        )}
                                        <button
                                            className="text-button"
                                            disabled={busy}
                                            onClick={() => onActivityState(activity, 'done')}
                                        >
                                            Complete
                                        </button>
                                        <button
                                            className="text-button"
                                            disabled={busy}
                                            onClick={() => onActivityState(activity, 'cancelled')}
                                        >
                                            Cancel activity
                                        </button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <h3>History</h3>
                <p className="muted-label">Latest 100 changes. Earlier history is retained.</p>
                {!detail.history.length ? (
                    <p>No history.</p>
                ) : (
                    <ol className="crm-history">
                        {detail.history.map((event) => (
                            <li key={event.id}>
                                <strong>{event.action.replaceAll('.', ' ')}</strong>
                                <time dateTime={event.occurred_at}>
                                    {new Date(event.occurred_at).toLocaleString()}
                                </time>
                                {event.from_stage && (
                                    <p>
                                        {pipeline?.stages.find((s) => s.id === event.from_stage)
                                            ?.name ?? 'Previous stage'}{' '}
                                        →{' '}
                                        {pipeline?.stages.find((s) => s.id === event.to_stage)
                                            ?.name ?? 'Current stage'}
                                    </p>
                                )}
                                {event.reason && <p>{event.reason}</p>}
                            </li>
                        ))}
                    </ol>
                )}
            </div>
        </section>
    );
}
