export type Company = { id: string; name: string; base_currency: string };
export type Profile = {
    revision: number;
    country: string;
    seller_name: string;
    seller_address: string;
    vat_registered: boolean;
    tin: string | null;
    vat_registration: string | null;
    vat_effective_from: string | null;
};
export type Customer = { id: string; name: string; number: string };
export type Product = {
    id: string;
    sku: string;
    name: string;
    unit: string;
    listPrice: string | null;
};
export type Opportunity = { id: string; number: string; title: string };
export type Line = {
    variant_id: string;
    quantity: string;
    unit_price: string;
    discount_percent: string;
    tax_code: string;
    tax_basis: string;
    sku?: string;
    name?: string;
    unit?: string;
    net?: string;
    vat_rate?: string;
};
export type Setup = {
    profile: Profile | null;
    customers: Customer[];
    products: Product[];
    opportunities: Opportunity[];
    rule_version: string;
};
export type Document = {
    id: string;
    number: string;
    title: string;
    company_id: string;
    customer_id: string;
    opportunity_id: string | null;
    status: string;
    currency: string;
    net: string;
    vat: string;
    total: string;
    revision?: number;
    issue_date: string;
    valid_until: string;
    terms: string | null;
    order_id: string | null;
    quote_id?: string;
    snapshot: {
        seller: Profile;
        customer: Customer;
        opportunity: Opportunity | null;
        pricing: { lines: Line[]; standard_base: string; rule_version: string };
        quotation_number?: string;
        issue_date: string;
        valid_until: string;
        terms: string | null;
    };
};
export type Detail = {
    record: Document;
    history: { id: string; action: string; reason: string | null; occurred_at: string }[];
};
export type Page = {
    data: Document[];
    total: number;
    current_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};
