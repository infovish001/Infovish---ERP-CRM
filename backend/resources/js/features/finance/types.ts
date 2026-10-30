export type Account = {
    id: string;
    code: string;
    name: string;
    type: string;
    bank: boolean;
    active: boolean;
    revision: number;
};
export type Period = {
    id: string;
    name: string;
    starts_on: string;
    ends_on: string;
    locked: boolean;
    revision: number;
    reason: string | null;
};
export type Settings = {
    revision: number;
    seller_name: string;
    seller_address: string;
    vat_registered: boolean;
    tin: string | null;
    vat_registration: string | null;
    vat_effective_from: string | null;
    issuer_code: string;
    receivable: string;
    payable: string;
    revenue: string;
    expense: string;
    vat_input: string;
    vat_output: string;
};
export type Setup = {
    accounts: Account[];
    periods: Period[];
    settings: Settings | null;
    orders: { id: string; number: string; total: string; customerId: string }[];
    suppliers: { id: string; number: string; name: string }[];
};
export type Line = {
    account_id?: string;
    debit?: string;
    credit?: string;
    description?: string | null;
    account_code?: string;
    account_name?: string;
    date?: string;
    amount?: string;
    line_id?: string | null;
    reference?: string;
    id?: string;
};
export type Entry = {
    id: string;
    number: string;
    company_id: string;
    date?: string;
    due_date?: string;
    state?: string;
    kind?: string;
    narration?: string;
    source_kind?: string;
    source_id?: string;
    revision?: number;
    journal_id?: string | null;
    order_id?: string | null;
    party_id?: string;
    supplier_reference?: string | null;
    net?: string;
    vat?: string;
    total?: string;
    outstanding?: string | null;
    bank_account_id?: string;
    reference?: string;
    amount?: string;
    starts_on?: string;
    ends_on?: string;
    opening?: string;
    closing?: string;
    opening_cleared_confirmed?: boolean;
    lines?: Line[];
    summary?: {
        book_balance: string;
        outstanding_net: string;
        adjusted_book: string;
        statement_closing: string;
    };
    snapshot?: {
        seller: Settings;
        party: { id: string; name: string; address?: string; tin?: string | null };
        tax_invoice?: boolean;
        supply_date?: string;
        request_evidence?: string | null;
        description?: string;
        vat_evidence?: string | null;
        order_number?: string;
    };
    void_id?: string | null;
    account_code?: string;
    account_name?: string;
    debit?: string;
    credit?: string;
};
export type Kind = 'drafts' | 'journals' | 'invoices' | 'bills' | 'payments' | 'statements';
export type Page = {
    data: Entry[];
    total: number;
    current_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};
export type Detail = {
    record: Entry;
    lines?: Line[];
    reversal_id?: string | null;
    outstanding?: string | null;
    payments?: Entry[];
    prints?: { id: string; kind: string; reason: string | null; created_at: string }[];
    void?: { id: string; reason: string; journal_id: string } | null;
};
export type Report = {
    trial: {
        from: string;
        to: string;
        accounts: {
            id: string;
            code: string;
            name: string;
            type: string;
            opening: string;
            debit: string;
            credit: string;
            closing: string;
        }[];
        period_debit: string;
        period_credit: string;
        period_difference: string;
        income: string;
        expense: string;
        period_result: string;
        assets: string;
        liabilities: string;
        equity: string;
        cumulative_unclosed_result: string;
        position_difference: string;
        label: string;
    };
    aging: {
        as_of: string;
        buckets: Record<string, Record<string, string>>;
        documents: {
            id: string;
            number: string;
            kind: string;
            party_name: string;
            due_date: string;
            outstanding: string;
            bucket: string;
        }[];
        open_count: number;
    };
};
export function today() {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Colombo' }).format(new Date());
}
