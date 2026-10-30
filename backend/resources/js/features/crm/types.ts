export type Company = { id: string; name: string; base_currency: string };
export type Stage = {
    id: string;
    name: string;
    probability: number;
    position: number;
    active: boolean;
    revision: number;
    pipeline_id: string;
};
export type Pipeline = {
    id: string;
    name: string;
    active: boolean;
    revision: number;
    stages: Stage[];
};
export type Customer = { id: string; name: string; number: string };
export type Setup = {
    pipelines: Pipeline[];
    assignees: { id: string; name: string }[];
    customers: Customer[];
};
export type CrmRecord = {
    id: string;
    number: string;
    title: string;
    status: string;
    revision: number;
    assigned_to: string;
    contact_name?: string;
    email?: string | null;
    phone?: string | null;
    source?: string | null;
    contact_id?: string;
    opportunity_id?: string | null;
    pipeline_id?: string;
    stage_id?: string;
    expected_amount?: string | null;
    currency?: string;
    expected_close?: string | null;
};
export type Activity = {
    id: string;
    subject: string;
    type: string;
    note: string | null;
    due_at: string;
    status: string;
    revision: number;
};
export type History = {
    id: string;
    action: string;
    reason: string | null;
    occurred_at: string;
    from_stage: string | null;
    to_stage: string | null;
};
export type Detail = { record: CrmRecord; history: History[]; activities: Activity[] };
export type Page = {
    data: CrmRecord[];
    current_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    total: number;
};
export type Forecast = {
    unweighted: string;
    weighted: string;
    currency: string;
    open_count: number;
    known_count: number;
    unknown_count: number;
    label: string;
};
export type Save = (body: Record<string, unknown>, key: string) => Promise<void>;
