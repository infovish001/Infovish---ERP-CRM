import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import Sales from './Sales';
import QuoteEditor from '../features/sales/QuoteEditor';
import type { Setup } from '../features/sales/types';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children }: { href: string; children: ReactNode }) => (
        <a href={href}>{children}</a>
    ),
}));
afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});
const props = {
    tenant: { name: 'Test Sales' },
    user: { name: 'Test operator' },
    companies: [{ id: 'company', name: 'Test company', base_currency: 'LKR' }],
    initialCompany: 'company',
    available: true,
    canManage: true,
};
const setup: Setup = {
    profile: {
        revision: 1,
        country: 'LK',
        seller_name: 'Test seller',
        seller_address: 'Test address',
        vat_registered: true,
        tin: '123456789',
        vat_registration: '123456789-7000',
        vat_effective_from: '2024-01-01',
    },
    customers: [{ id: 'customer', name: 'Test buyer', number: 'CON-1' }],
    products: [
        { id: 'variant', sku: 'SKU-1', name: 'Test service', unit: 'hour', listPrice: '99.123456' },
    ],
    opportunities: [],
    rule_version: 'LK-VAT-2026-10-30',
};
const record = {
    id: 'quote',
    company_id: 'company',
    number: 'QTN-1',
    title: 'Test proposal',
    status: 'issued',
    customer_id: 'customer',
    opportunity_id: null,
    currency: 'LKR',
    net: '277.78',
    vat: '50.00',
    total: '327.78',
    revision: 2,
    issue_date: '2026-10-30',
    valid_until: '2026-12-31',
    terms: null,
    order_id: null,
    snapshot: {
        seller: setup.profile,
        customer: setup.customers[0],
        opportunity: null,
        pricing: {
            lines: [
                {
                    variant_id: 'variant',
                    quantity: '2.500000',
                    unit_price: '123.456789',
                    discount_percent: '10.000000',
                    tax_code: 'standard',
                    tax_basis: null,
                    net: '277.78',
                    vat_rate: '18.000000',
                    sku: 'SKU-1',
                    name: 'Test service',
                    unit: 'hour',
                },
            ],
            standard_base: '277.78',
            rule_version: setup.rule_version,
        },
        issue_date: '2026-10-30',
        valid_until: '2026-12-31',
        terms: null,
    },
};
function response(data: unknown, status = 200) {
    return { ok: status < 400, status, json: () => Promise.resolve(data) };
}
function reads(url: string) {
    if (url.includes('/setup')) return setup;
    if (url.includes('/quotations/quote')) return { record, history: [] };
    if (url.includes('/orders/order'))
        return {
            record: {
                ...record,
                id: 'order',
                number: 'SO-1',
                status: 'confirmed',
                quote_id: 'quote',
            },
            history: [],
        };
    return { data: [record], total: 1, current_page: 1, next_page_url: null, prev_page_url: null };
}
describe('Sales workflows', () => {
    it('shows module setup without fabricating Sales records', () => {
        render(<Sales {...props} available={false} />);
        expect(screen.getByText('Sales is not active')).toBeTruthy();
        expect(screen.queryByText('QTN-1')).toBeNull();
    });
    it('requires the seller profile before creating a quotation', async () => {
        vi.stubGlobal(
            'fetch',
            vi
                .fn()
                .mockImplementation((url: string) =>
                    Promise.resolve(
                        response(
                            url.includes('/setup')
                                ? { ...setup, profile: null }
                                : { data: [], total: 0 },
                        ),
                    ),
                ),
        );
        render(<Sales {...props} />);
        await screen.findByText('Set up your Sales profile');
        expect(
            (screen.getByRole('button', { name: '+ New quotation' }) as HTMLButtonElement).disabled,
        ).toBe(true);
        fireEvent.click(screen.getByRole('button', { name: 'Configure Sales profile' }));
        expect(screen.getByLabelText('Seller is VAT registered')).toBeTruthy();
    });
    it('keeps six-place input strings and a stable create key without silently using list prices', async () => {
        const save = vi.fn();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response(setup)));
        render(
            <QuoteEditor
                company="company"
                setup={setup}
                busy={false}
                onSave={save}
                onClose={() => {}}
                onError={() => {}}
            />,
        );
        fireEvent.change(screen.getByLabelText('Quotation title'), {
            target: { value: 'Proposal' },
        });
        fireEvent.change(screen.getByLabelText('Customer'), { target: { value: 'customer' } });
        fireEvent.change(screen.getByLabelText('Product or service 1'), {
            target: { value: 'variant' },
        });
        expect(
            (screen.getByLabelText('Unit price excluding VAT 1') as HTMLInputElement).value,
        ).toBe('');
        fireEvent.change(screen.getByLabelText('Unit price excluding VAT 1'), {
            target: { value: '123.456789' },
        });
        fireEvent.change(screen.getByLabelText('Quantity 1'), { target: { value: '2.500000' } });
        fireEvent.change(screen.getByLabelText('Valid until'), { target: { value: '2026-12-31' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save draft quotation' }));
        fireEvent.click(screen.getByRole('button', { name: 'Save draft quotation' }));
        expect(save).toHaveBeenCalledTimes(2);
        expect(save.mock.calls[0][0].lines[0].unit_price).toBe('123.456789');
        expect(save.mock.calls[0][0].lines[0].quantity).toBe('2.500000');
        expect(save.mock.calls[0][1]).toBe(save.mock.calls[1][1]);
    });
    it('confirms only an issued quotation and opens the resulting immutable order', async () => {
        const fetcher = vi
            .fn()
            .mockImplementation((url: string, init: { method: string }) =>
                Promise.resolve(response(init.method === 'POST' ? { id: 'order' } : reads(url))),
            );
        vi.stubGlobal('fetch', fetcher);
        render(<Sales {...props} />);
        fireEvent.click(await screen.findByRole('button', { name: 'Open QTN-1' }));
        const confirm = await screen.findByRole('button', { name: 'Confirm order from quotation' });
        expect(screen.queryByRole('button', { name: 'Edit draft' })).toBeNull();
        fireEvent.click(confirm);
        await screen.findByText('Order confirmed. Quotation contents and prices are preserved.');
        await screen.findByRole('link', { name: 'Download order PDF' });
        const write = fetcher.mock.calls.find(([url]) => String(url).endsWith('/convert'));
        expect(JSON.parse(write![1].body)).toEqual({ company_id: 'company', revision: 2 });
        expect(write![1].headers['Idempotency-Key']).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Confirm order from quotation' })).toBeNull();
    });
    it('keeps members read-only and surfaces server revision errors', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockImplementation((url: string) => Promise.resolve(response(reads(url)))),
        );
        render(<Sales {...props} canManage={false} />);
        fireEvent.click(await screen.findByRole('button', { name: 'Open QTN-1' }));
        await screen.findByRole('link', { name: 'Download quotation PDF' });
        expect(screen.queryByRole('button', { name: 'Confirm order from quotation' })).toBeNull();
        expect(screen.queryByRole('tab', { name: 'Sales tax profile' })).toBeNull();
    });
    it('leaves the quotation visible when conversion fails', async () => {
        vi.stubGlobal(
            'fetch',
            vi
                .fn()
                .mockImplementation((url: string, init: { method: string }) =>
                    Promise.resolve(
                        init.method === 'POST'
                            ? response(
                                  { message: 'Tax profile changed; cancel this quotation.' },
                                  409,
                              )
                            : response(reads(url)),
                    ),
                ),
        );
        render(<Sales {...props} />);
        fireEvent.click(await screen.findByRole('button', { name: 'Open QTN-1' }));
        fireEvent.click(
            await screen.findByRole('button', { name: 'Confirm order from quotation' }),
        );
        await waitFor(() =>
            expect(screen.getByRole('alert').textContent).toContain('Tax profile changed'),
        );
        expect(screen.getByRole('link', { name: 'Download quotation PDF' })).toBeTruthy();
    });
});
