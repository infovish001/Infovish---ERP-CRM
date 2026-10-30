import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import Finance from './Finance';
import JournalForm from '../features/finance/JournalForm';
import DocumentForm from '../features/finance/DocumentForm';
import type { Setup } from '../features/finance/types';
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
    tenant: { name: 'Finance test' },
    user: { name: 'Test accountant', role: 'owner' },
    companies: [{ id: 'company', name: 'Test company', base_currency: 'LKR' }],
    initialCompany: 'company',
    available: true,
    canManage: true,
};
const accounts = [
    {
        id: 'bank',
        code: 'BANK',
        name: 'Test bank',
        type: 'asset',
        bank: true,
        active: true,
        revision: 1,
    },
    {
        id: 'equity',
        code: 'EQUITY',
        name: 'Test equity',
        type: 'equity',
        bank: false,
        active: true,
        revision: 1,
    },
];
const setup: Setup = {
    accounts,
    periods: [],
    settings: {
        revision: 1,
        seller_name: 'Test seller',
        seller_address: 'Test address',
        vat_registered: true,
        tin: '123456789',
        vat_registration: '123456789-7000',
        vat_effective_from: '2024-01-01',
        issuer_code: 'TEST1',
        receivable: 'ar',
        payable: 'ap',
        revenue: 'income',
        expense: 'expense',
        vat_input: 'input',
        vat_output: 'output',
    },
    orders: [{ id: 'order', number: 'SO-1', total: '327.78', customerId: 'buyer' }],
    suppliers: [],
};
const draft = {
    id: 'draft',
    number: 'JDR-1',
    state: 'draft',
    revision: 1,
    date: '2026-10-31',
    narration: 'Actual test entry',
    lines: [
        { account_id: 'bank', debit: '100.25', credit: '0.00' },
        { account_id: 'equity', debit: '0.00', credit: '100.25' },
    ],
};
function response(data: unknown, status = 200) {
    return { ok: status < 400, status, json: () => Promise.resolve(data) };
}
function reads(url: string) {
    if (url.includes('/setup')) return setup;
    if (url.includes('/drafts/draft')) return { record: draft };
    if (url.includes('/drafts'))
        return {
            data: [draft],
            total: 1,
            current_page: 1,
            next_page_url: null,
            prev_page_url: null,
        };
    return { data: [], total: 0, current_page: 1, next_page_url: null, prev_page_url: null };
}
describe('Finance controls', () => {
    it('shows a module boundary without invented balances', () => {
        render(<Finance {...props} available={false} />);
        expect(screen.getByText('Finance is not active')).toBeTruthy();
        expect(screen.queryByText('100.25')).toBeNull();
    });
    it('preserves exact debit strings and a stable draft retry key', () => {
        const save = vi.fn();
        render(<JournalForm setup={setup} busy={false} save={save} close={() => {}} />);
        fireEvent.change(screen.getByLabelText('Narration'), {
            target: { value: 'Reviewed contribution' },
        });
        fireEvent.change(screen.getByLabelText('Account 1'), { target: { value: 'bank' } });
        fireEvent.change(screen.getByLabelText('Account 2'), { target: { value: 'equity' } });
        fireEvent.change(screen.getByLabelText('Debit 1'), {
            target: { value: '999999999999999.25' },
        });
        fireEvent.change(screen.getByLabelText('Credit 2'), {
            target: { value: '999999999999999.25' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Save journal draft' }));
        fireEvent.click(screen.getByRole('button', { name: 'Save journal draft' }));
        expect(save.mock.calls[0][0].lines[0].debit).toBe('999999999999999.25');
        expect(save.mock.calls[0][1]).toBe(save.mock.calls[1][1]);
    });
    it('gives auditors read-only controls', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockImplementation((url: string) => Promise.resolve(response(reads(url)))),
        );
        render(
            <Finance
                {...props}
                user={{ name: 'Test auditor', role: 'auditor' }}
                canManage={false}
            />,
        );
        fireEvent.click(screen.getByRole('tab', { name: 'Journal drafts' }));
        fireEvent.click(await screen.findByRole('button', { name: 'Open JDR-1' }));
        await screen.findByRole('region', { name: 'Finance record details' });
        expect(screen.queryByRole('button', { name: 'Post balanced journal' })).toBeNull();
        expect(screen.queryByRole('button', { name: '+ New journal draft' })).toBeNull();
    });
    it('blocks posting when settings are absent and surfaces the setup action', async () => {
        vi.stubGlobal(
            'fetch',
            vi
                .fn()
                .mockImplementation((url: string) =>
                    Promise.resolve(
                        response(
                            url.includes('/setup') ? { ...setup, settings: null } : reads(url),
                        ),
                    ),
                ),
        );
        render(<Finance {...props} />);
        await screen.findByText('Finance setup is required before posting');
        expect(screen.getByRole('button', { name: 'Review Finance setup' })).toBeTruthy();
    });
    it('shows a period-lock conflict while retaining the draft', async () => {
        vi.stubGlobal(
            'fetch',
            vi
                .fn()
                .mockImplementation((url: string, init: { method: string }) =>
                    Promise.resolve(
                        init.method === 'POST'
                            ? response(
                                  { message: 'No open accounting period covers this date.' },
                                  409,
                              )
                            : response(reads(url)),
                    ),
                ),
        );
        render(<Finance {...props} />);
        fireEvent.click(screen.getByRole('tab', { name: 'Journal drafts' }));
        fireEvent.click(await screen.findByRole('button', { name: 'Open JDR-1' }));
        fireEvent.click(await screen.findByRole('button', { name: 'Post balanced journal' }));
        await waitFor(() =>
            expect(screen.getByRole('alert').textContent).toContain('No open accounting period'),
        );
        expect(screen.getByRole('button', { name: 'Edit draft' })).toBeTruthy();
    });
    it('requires explicit purchaser and supply details for invoice drafts', () => {
        const save = vi.fn();
        render(
            <DocumentForm
                company="company"
                setup={setup}
                kind="invoice"
                busy={false}
                save={save}
                close={() => {}}
                error={() => {}}
            />,
        );
        fireEvent.change(screen.getByLabelText('Confirmed Sales order'), {
            target: { value: 'order' },
        });
        fireEvent.change(screen.getByLabelText('Purchaser registered name'), {
            target: { value: 'Buyer legal name' },
        });
        fireEvent.change(screen.getByLabelText('Purchaser address'), {
            target: { value: 'Registered address' },
        });
        fireEvent.change(screen.getByLabelText('Purchaser TIN'), {
            target: { value: '987654321' },
        });
        fireEvent.change(screen.getByLabelText('Actual date of supply'), {
            target: { value: '2026-10-31' },
        });
        fireEvent.change(screen.getByLabelText('Due date'), { target: { value: '2026-11-30' } });
        fireEvent.change(screen.getByLabelText('Timely written request / registration evidence'), {
            target: { value: 'Verified timely written request' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Save document draft' }));
        expect(save).toHaveBeenCalledOnce();
        expect(save.mock.calls[0][0]).toMatchObject({
            order_id: 'order',
            tax_invoice: true,
            buyer_tin: '987654321',
            supply_date: '2026-10-31',
        });
        expect(save.mock.calls[0][0].total).toBeUndefined();
    });
});
