import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { requestJson } from '../lib/request';

type Company = {
    id: string;
    code: string;
    name: string;
    active: boolean;
    base_currency: string;
    timezone: string;
};
type Branch = { id: string; company_id: string; code: string; name: string; active: boolean };
type Department = Branch & { branch_id: string | null };
type Member = {
    name: string;
    email: string;
    role: string;
    active: boolean;
    company_ids: string;
    branch_ids: string;
};
type AuditEvent = { id: string; action: string; resource_id: string; occurred_at: string };
type Props = {
    tenant: { id: string; name: string };
    user: { name: string; email: string; role: string };
    permissions: { manageOrganization: boolean; manageMemberships: boolean; viewAudit: boolean };
    companies: Company[];
    branches: Branch[];
    departments: Department[];
    scope: { companyId: string | null; branchId: string | null };
};

export default function Workspace({
    tenant,
    user,
    permissions,
    companies,
    branches,
    departments,
    scope,
}: Props) {
    const [dark, setDark] = useState(false);
    const [menu, setMenu] = useState(false);
    const [tab, setTab] = useState<'organization' | 'members' | 'audit'>('organization');
    const [kind, setKind] = useState<'company' | 'branch' | 'department'>('company');
    const [editing, setEditing] = useState<string | null>(null);
    const [members, setMembers] = useState<Member[]>([]);
    const [audit, setAudit] = useState<AuditEvent[]>([]);
    const [auditPage, setAuditPage] = useState(1);
    const [moreAudit, setMoreAudit] = useState(false);
    const [loadError, setLoadError] = useState('');
    const [loading, setLoading] = useState(false);
    const form = useForm({
        name: '',
        code: '',
        base_currency: 'LKR',
        timezone: 'Asia/Colombo',
        company_id: scope.companyId ?? '',
        branch_id: '',
        active: true,
    });
    const member = useForm({
        email: '',
        role: 'member',
        active: true,
        company_ids: [] as string[],
        branch_ids: [] as string[],
    });
    useEffect(() => {
        if (tab === 'organization') return;
        let current = true;
        setLoading(true);
        setLoadError('');
        const load =
            tab === 'members'
                ? requestJson<Member[]>('/workspace/memberships').then((data) => {
                      if (current) setMembers(data);
                  })
                : requestJson<{ data: AuditEvent[]; next_page_url: string | null }>(
                      `/workspace/audit?page=${auditPage}`,
                  ).then((data) => {
                      if (current) {
                          setAudit(data.data);
                          setMoreAudit(Boolean(data.next_page_url));
                      }
                  });
        void load
            .catch((error) => {
                if (current)
                    setLoadError(
                        error instanceof Error ? error.message : 'Unable to load records.',
                    );
            })
            .finally(() => {
                if (current) setLoading(false);
            });
        return () => {
            current = false;
        };
    }, [tab, auditPage]);
    function switchCompany(id: string) {
        router.post('/workspace/scope', { company_id: id, branch_id: null });
    }
    function editRecord(type: typeof kind, row: Company | Branch | Department) {
        setKind(type);
        setEditing(row.id);
        form.clearErrors();
        form.setData({
            name: row.name,
            code: row.code,
            active: row.active,
            base_currency: 'base_currency' in row ? row.base_currency : 'LKR',
            timezone: 'timezone' in row ? row.timezone : 'Asia/Colombo',
            company_id: 'company_id' in row ? row.company_id : '',
            branch_id: 'branch_id' in row ? (row.branch_id ?? '') : '',
        });
    }
    function toggleGrant(key: 'company_ids' | 'branch_ids', id: string) {
        const selected = member.data[key].includes(id);
        member.setData((data) => ({
            ...data,
            [key]: selected ? data[key].filter((value) => value !== id) : [...data[key], id],
            branch_ids:
                key === 'company_ids' && selected
                    ? data.branch_ids.filter(
                          (value) =>
                              !branches.some(
                                  (branch) => branch.id === value && branch.company_id === id,
                              ),
                      )
                    : key === 'branch_ids'
                      ? selected
                          ? data.branch_ids.filter((value) => value !== id)
                          : [...data.branch_ids, id]
                      : data.branch_ids,
        }));
    }
    const visibleBranches = branches.filter((branch) => branch.company_id === scope.companyId);
    const visibleDepartments = departments.filter(
        (department) =>
            department.company_id === scope.companyId &&
            (!scope.branchId ||
                department.branch_id === null ||
                department.branch_id === scope.branchId),
    );
    return (
        <div className={`workspace ${dark ? 'dark' : ''}`}>
            <Head
                title={
                    tab === 'organization'
                        ? 'Organization'
                        : tab === 'members'
                          ? 'Workspace members'
                          : 'Audit history'
                }
            />
            <a href="#main" className="skip-link">
                Skip to content
            </a>
            <aside className={`sidebar ${menu ? 'is-open' : ''}`} aria-label="Workspace navigation">
                <Link className="brand" href="/workspace">
                    <span className="brand-mark" aria-hidden="true">
                        ▥
                    </span>{' '}
                    infovish <small>ERP</small>
                </Link>
                <div className="workspace-label">
                    <span className="workspace-avatar">
                        {tenant.name.slice(0, 2).toUpperCase()}
                    </span>
                    <div>
                        {tenant.name}
                        <small>Workspace</small>
                    </div>
                </div>
                <p className="nav-label">ADMINISTRATION</p>
                <nav className="workspace-nav">
                    <Link href="/workspace/master-data">Contacts &amp; catalog</Link>
                    <Link href="/workspace/crm">CRM</Link>
                    <Link href="/workspace/sales">Sales</Link>
                    {['owner','administrator','auditor'].includes(user.role) && <Link href="/workspace/finance">Accounting &amp; finance</Link>}
                    <button
                        className={tab === 'organization' ? 'nav-active' : ''}
                        onClick={() => setTab('organization')}
                    >
                        Organization
                    </button>
                    {permissions.manageMemberships && (
                        <button
                            className={tab === 'members' ? 'nav-active' : ''}
                            onClick={() => setTab('members')}
                        >
                            Members and access
                        </button>
                    )}
                    {permissions.viewAudit && (
                        <button
                            className={tab === 'audit' ? 'nav-active' : ''}
                            onClick={() => setTab('audit')}
                        >
                            Audit history
                        </button>
                    )}
                </nav>
                <div className="sidebar-note">
                    <p>{user.name}</p>
                    <small>{user.role}</small>
                    <Link className="sidebar-account" href="/account/security">
                        Account security
                    </Link>
                    <button className="text-button" onClick={() => router.post('/logout')}>
                        Sign out
                    </button>
                </div>
            </aside>
            <div className="content">
                <header className="topbar">
                    <button
                        className="menu-toggle"
                        aria-label="Toggle navigation"
                        aria-expanded={menu}
                        onClick={() => setMenu(!menu)}
                    >
                        ☰
                    </button>
                    <div className="breadcrumb">
                        {tenant.name} <span>/</span>{' '}
                        <strong>
                            {tab === 'organization'
                                ? 'Organization'
                                : tab === 'members'
                                  ? 'Members'
                                  : 'Audit'}
                        </strong>
                    </div>
                    <div className="header-tools">
                        <label className="compact-label">
                            Company
                            <select
                                aria-label="Active company"
                                value={scope.companyId ?? ''}
                                onChange={(event) => switchCompany(event.target.value)}
                                disabled={!companies.some((company) => company.active)}
                            >
                                <option value="" disabled>
                                    Select company
                                </option>
                                {companies
                                    .filter((company) => company.active)
                                    .map((company) => (
                                        <option key={company.id} value={company.id}>
                                            {company.name}
                                        </option>
                                    ))}
                            </select>
                        </label>
                        <label className="compact-label">
                            Branch
                            <select
                                aria-label="Active branch"
                                value={scope.branchId ?? ''}
                                onChange={(event) =>
                                    router.post('/workspace/scope', {
                                        company_id: scope.companyId,
                                        branch_id: event.target.value || null,
                                    })
                                }
                            >
                                <option value="">All permitted branches</option>
                                {visibleBranches
                                    .filter((branch) => branch.active)
                                    .map((branch) => (
                                        <option key={branch.id} value={branch.id}>
                                            {branch.name}
                                        </option>
                                    ))}
                            </select>
                        </label>
                        <button
                            className="theme-button"
                            aria-label="Toggle dark theme"
                            aria-pressed={dark}
                            onClick={() => setDark(!dark)}
                        >
                            {dark ? 'Light' : 'Dark'}
                        </button>
                    </div>
                </header>
                <main id="main">
                    <p className="eyebrow">WORKSPACE ADMINISTRATION</p>
                    <h1>
                        {tab === 'organization'
                            ? 'Organization'
                            : tab === 'members'
                              ? 'Members and access'
                              : 'Audit history'}
                    </h1>
                    {tab === 'organization' && (
                        <>
                            <p className="page-description">
                                Your companies, branches and departments. Access follows your
                                workspace membership.
                            </p>
                            <section className="panel data-panel">
                                <h2>Companies</h2>
                                {companies.length === 0 ? (
                                    <p>
                                        No companies are available.{' '}
                                        {permissions.manageOrganization
                                            ? 'Create your first company below.'
                                            : 'Ask an owner to grant company access.'}
                                    </p>
                                ) : (
                                    <div className="table-scroll">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Company</th>
                                                    <th>Code</th>
                                                    <th>Currency</th>
                                                    <th>Timezone</th>
                                                    <th>Status</th>
                                                    {permissions.manageOrganization && (
                                                        <th>Actions</th>
                                                    )}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {companies.map((company) => (
                                                    <tr key={company.id}>
                                                        <td>{company.name}</td>
                                                        <td>{company.code}</td>
                                                        <td>{company.base_currency}</td>
                                                        <td>{company.timezone}</td>
                                                        <td>
                                                            {company.active ? 'Active' : 'Inactive'}
                                                        </td>
                                                        {permissions.manageOrganization && (
                                                            <td>
                                                                <button
                                                                    className="text-button"
                                                                    onClick={() =>
                                                                        editRecord(
                                                                            'company',
                                                                            company,
                                                                        )
                                                                    }
                                                                >
                                                                    Edit
                                                                </button>{' '}
                                                                <button
                                                                    className="text-button"
                                                                    onClick={() =>
                                                                        router.put(
                                                                            `/organization/company/${company.id}`,
                                                                            {
                                                                                name: company.name,
                                                                                active: !company.active,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    {company.active
                                                                        ? 'Deactivate'
                                                                        : 'Reactivate'}
                                                                </button>
                                                            </td>
                                                        )}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </section>
                            <div className="organization-grid">
                                {(['branch', 'department'] as const).map((type) => {
                                    const rows =
                                        type === 'branch' ? visibleBranches : visibleDepartments;
                                    return (
                                        <section className="panel data-panel" key={type}>
                                            <h2>
                                                {type === 'branch' ? 'Branches' : 'Departments'}
                                            </h2>
                                            {!scope.companyId ? (
                                                <p>Select a company to view its organization.</p>
                                            ) : rows.length === 0 ? (
                                                <p>
                                                    No{' '}
                                                    {type === 'branch' ? 'branches' : 'departments'}{' '}
                                                    in this scope.
                                                </p>
                                            ) : (
                                                <div className="table-scroll">
                                                    <table>
                                                        <thead>
                                                            <tr>
                                                                <th>Name</th>
                                                                <th>Code</th>
                                                                <th>Status</th>
                                                                {permissions.manageOrganization && (
                                                                    <th>Actions</th>
                                                                )}
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {rows.map((row) => (
                                                                <tr key={row.id}>
                                                                    <td>{row.name}</td>
                                                                    <td>{row.code}</td>
                                                                    <td>
                                                                        {row.active
                                                                            ? 'Active'
                                                                            : 'Inactive'}
                                                                    </td>
                                                                    {permissions.manageOrganization && (
                                                                        <td>
                                                                            <button
                                                                                className="text-button"
                                                                                onClick={() =>
                                                                                    editRecord(
                                                                                        type,
                                                                                        row,
                                                                                    )
                                                                                }
                                                                            >
                                                                                Edit
                                                                            </button>{' '}
                                                                            <button
                                                                                className="text-button"
                                                                                onClick={() =>
                                                                                    router.put(
                                                                                        `/organization/${type}/${row.id}`,
                                                                                        {
                                                                                            name: row.name,
                                                                                            active: !row.active,
                                                                                        },
                                                                                    )
                                                                                }
                                                                            >
                                                                                {row.active
                                                                                    ? 'Deactivate'
                                                                                    : 'Reactivate'}
                                                                            </button>
                                                                        </td>
                                                                    )}
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            )}
                                        </section>
                                    );
                                })}
                            </div>
                            {permissions.manageOrganization && (
                                <section className="panel data-panel">
                                    <h2>
                                        {editing
                                            ? 'Edit organization record'
                                            : 'Create an organization record'}
                                    </h2>
                                    <form
                                        className="enterprise-form"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            const options = {
                                                onSuccess: () => {
                                                    form.reset('name', 'code');
                                                    setEditing(null);
                                                },
                                            };
                                            if (editing)
                                                form.put(
                                                    `/organization/${kind}/${editing}`,
                                                    options,
                                                );
                                            else form.post(`/organization/${kind}`, options);
                                        }}
                                    >
                                        <label>
                                            Record type
                                            <select
                                                disabled={Boolean(editing)}
                                                value={kind}
                                                onChange={(event) =>
                                                    setKind(event.target.value as typeof kind)
                                                }
                                            >
                                                <option value="company">Company</option>
                                                <option value="branch">Branch</option>
                                                <option value="department">Department</option>
                                            </select>
                                        </label>
                                        <label>
                                            Name
                                            <input
                                                required
                                                maxLength={120}
                                                value={form.data.name}
                                                onChange={(event) =>
                                                    form.setData('name', event.target.value)
                                                }
                                            />
                                        </label>
                                        <label>
                                            Code
                                            <input
                                                disabled={Boolean(editing)}
                                                required
                                                maxLength={20}
                                                value={form.data.code}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'code',
                                                        event.target.value.toUpperCase(),
                                                    )
                                                }
                                            />
                                        </label>
                                        {kind === 'company' ? (
                                            <>
                                                <label>
                                                    Base currency code
                                                    <input
                                                        disabled={Boolean(editing)}
                                                        required
                                                        maxLength={3}
                                                        value={form.data.base_currency}
                                                        onChange={(event) =>
                                                            form.setData(
                                                                'base_currency',
                                                                event.target.value.toUpperCase(),
                                                            )
                                                        }
                                                    />
                                                </label>
                                                <label>
                                                    Timezone
                                                    <input
                                                        disabled={Boolean(editing)}
                                                        required
                                                        value={form.data.timezone}
                                                        onChange={(event) =>
                                                            form.setData(
                                                                'timezone',
                                                                event.target.value,
                                                            )
                                                        }
                                                    />
                                                </label>
                                            </>
                                        ) : (
                                            <label>
                                                Company
                                                <select
                                                    disabled={Boolean(editing)}
                                                    required
                                                    value={form.data.company_id}
                                                    onChange={(event) => {
                                                        form.setData(
                                                            'company_id',
                                                            event.target.value,
                                                        );
                                                        form.setData('branch_id', '');
                                                    }}
                                                >
                                                    <option value="">Select company</option>
                                                    {companies
                                                        .filter((company) => company.active)
                                                        .map((company) => (
                                                            <option
                                                                key={company.id}
                                                                value={company.id}
                                                            >
                                                                {company.name}
                                                            </option>
                                                        ))}
                                                </select>
                                            </label>
                                        )}
                                        {kind === 'department' && (
                                            <label>
                                                Branch
                                                <select
                                                    disabled={Boolean(editing)}
                                                    value={form.data.branch_id}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'branch_id',
                                                            event.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">Company-wide</option>
                                                    {branches
                                                        .filter(
                                                            (branch) =>
                                                                branch.active &&
                                                                branch.company_id ===
                                                                    form.data.company_id,
                                                        )
                                                        .map((branch) => (
                                                            <option
                                                                key={branch.id}
                                                                value={branch.id}
                                                            >
                                                                {branch.name}
                                                            </option>
                                                        ))}
                                                </select>
                                            </label>
                                        )}
                                        <div className="form-actions">
                                            {Object.values(form.errors).map((error) => (
                                                <p role="alert" className="form-error" key={error}>
                                                    {error}
                                                </p>
                                            ))}
                                            <button
                                                className="primary-button"
                                                disabled={form.processing}
                                            >
                                                {editing ? 'Save changes' : `Create ${kind}`}
                                            </button>
                                            {editing && (
                                                <button
                                                    type="button"
                                                    className="text-button"
                                                    onClick={() => {
                                                        setEditing(null);
                                                        form.reset();
                                                    }}
                                                >
                                                    Cancel editing
                                                </button>
                                            )}
                                        </div>
                                    </form>
                                </section>
                            )}
                        </>
                    )}
                    {tab === 'members' && (
                        <>
                            <p className="page-description">
                                Grant access to existing platform identities. Only workspace owners
                                can change memberships.
                            </p>
                            <section className="panel data-panel">
                                <h2>Workspace members</h2>
                                {loading ? (
                                    <p role="status">Loading members…</p>
                                ) : (
                                    <div className="table-scroll">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {members.map((row) => (
                                                    <tr key={row.email}>
                                                        <td>{row.name}</td>
                                                        <td>{row.email}</td>
                                                        <td>{row.role}</td>
                                                        <td>
                                                            {row.active ? 'Active' : 'Inactive'}
                                                        </td>
                                                        <td>
                                                            <button
                                                                className="text-button"
                                                                onClick={() =>
                                                                    member.setData({
                                                                        email: row.email,
                                                                        role: row.role,
                                                                        active: row.active,
                                                                        company_ids: JSON.parse(
                                                                            row.company_ids,
                                                                        ) as string[],
                                                                        branch_ids: JSON.parse(
                                                                            row.branch_ids,
                                                                        ) as string[],
                                                                    })
                                                                }
                                                            >
                                                                Edit access
                                                            </button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </section>
                            <section className="panel data-panel">
                                <h2>Grant or update access</h2>
                                <form
                                    className="enterprise-form"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        member.post('/workspace/memberships', {
                                            onSuccess: () => {
                                                setTab('organization');
                                                member.reset();
                                            },
                                        });
                                    }}
                                >
                                    <label>
                                        Email
                                        <input
                                            type="email"
                                            required
                                            value={member.data.email}
                                            onChange={(event) =>
                                                member.setData('email', event.target.value)
                                            }
                                        />
                                    </label>
                                    <label>
                                        Role
                                        <select
                                            value={member.data.role}
                                            onChange={(event) =>
                                                member.setData('role', event.target.value)
                                            }
                                        >
                                            {['owner', 'administrator', 'auditor', 'member'].map(
                                                (role) => (
                                                    <option key={role} value={role}>
                                                        {role}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </label>
                                    <label className="check-label">
                                        <input
                                            type="checkbox"
                                            checked={member.data.active}
                                            onChange={(event) =>
                                                member.setData('active', event.target.checked)
                                            }
                                        />
                                        Active membership
                                    </label>
                                    {!['owner', 'administrator'].includes(member.data.role) && (
                                        <fieldset>
                                            <legend>Permitted companies and branches</legend>
                                            {companies
                                                .filter((company) => company.active)
                                                .map((company) => (
                                                    <div key={company.id}>
                                                        <label className="check-label">
                                                            <input
                                                                type="checkbox"
                                                                checked={member.data.company_ids.includes(
                                                                    company.id,
                                                                )}
                                                                onChange={() =>
                                                                    toggleGrant(
                                                                        'company_ids',
                                                                        company.id,
                                                                    )
                                                                }
                                                            />
                                                            {company.name}
                                                        </label>
                                                        {branches
                                                            .filter(
                                                                (branch) =>
                                                                    branch.active &&
                                                                    branch.company_id ===
                                                                        company.id,
                                                            )
                                                            .map((branch) => (
                                                                <label
                                                                    className="check-label branch-grant"
                                                                    key={branch.id}
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        disabled={
                                                                            !member.data.company_ids.includes(
                                                                                company.id,
                                                                            )
                                                                        }
                                                                        checked={member.data.branch_ids.includes(
                                                                            branch.id,
                                                                        )}
                                                                        onChange={() =>
                                                                            toggleGrant(
                                                                                'branch_ids',
                                                                                branch.id,
                                                                            )
                                                                        }
                                                                    />
                                                                    {branch.name}
                                                                </label>
                                                            ))}
                                                    </div>
                                                ))}
                                        </fieldset>
                                    )}
                                    <div className="form-actions">
                                        {Object.values(member.errors).map((error) => (
                                            <p role="alert" className="form-error" key={error}>
                                                {error}
                                            </p>
                                        ))}
                                        <button
                                            className="primary-button"
                                            disabled={member.processing}
                                        >
                                            Save membership
                                        </button>
                                    </div>
                                </form>
                            </section>
                        </>
                    )}
                    {tab === 'audit' && (
                        <>
                            <p className="page-description">
                                Append-only history of changes in your permitted company scope.
                            </p>
                            <section className="panel data-panel">
                                <h2>Organization history</h2>
                                {loading ? (
                                    <p role="status">Loading audit history…</p>
                                ) : audit.length === 0 ? (
                                    <p>No audit events in this scope.</p>
                                ) : (
                                    <div className="table-scroll">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Time</th>
                                                    <th>Action</th>
                                                    <th>Record</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {audit.map((row) => (
                                                    <tr key={row.id}>
                                                        <td>
                                                            {new Date(
                                                                row.occurred_at,
                                                            ).toLocaleString()}
                                                        </td>
                                                        <td>{row.action}</td>
                                                        <td className="record-id">
                                                            {row.resource_id}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                                <div className="button-row">
                                    <button
                                        className="secondary-button"
                                        disabled={auditPage === 1 || loading}
                                        onClick={() => setAuditPage(auditPage - 1)}
                                    >
                                        Previous
                                    </button>
                                    <span>Page {auditPage}</span>
                                    <button
                                        className="secondary-button"
                                        disabled={!moreAudit || loading}
                                        onClick={() => setAuditPage(auditPage + 1)}
                                    >
                                        Next
                                    </button>
                                </div>
                            </section>
                        </>
                    )}
                    {loadError && (
                        <p role="alert" className="form-error">
                            {loadError}
                        </p>
                    )}
                </main>
            </div>
        </div>
    );
}
