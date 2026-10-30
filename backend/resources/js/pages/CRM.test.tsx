import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import CRM from './CRM';

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
    tenant: { name: 'Test CRM workspace' },
    user: { id: 'actor', name: 'Sales member', role: 'member' },
    companies: [{ id: 'company', name: 'Test company', base_currency: 'LKR' }],
    initialCompany: 'company',
    available: true,
    canManage: true,
    canAdmin: false,
};
const setup = {
    pipelines: [
        {
            id: 'pipeline',
            name: 'Real pipeline',
            active: true,
            revision: 1,
            stages: [
                {
                    id: 'stage',
                    pipeline_id: 'pipeline',
                    name: 'Qualified',
                    probability: 25,
                    active: true,
                    position: 1,
                    revision: 1,
                },
            ],
        },
    ],
    assignees: [{ id: 'actor', name: 'Sales member' }],
    customers: [{ id: 'customer', name: 'Existing customer', number: 'CON-1' }],
};
const lead = {
    id: 'lead',
    number: 'LED-1',
    title: 'Actual prospect inquiry',
    contact_name: 'Test prospect',
    email: 'prospect@example.test',
    assigned_to: 'actor',
    status: 'qualified',
    revision: 2,
};
const page = { data: [lead], total: 1, current_page: 1, next_page_url: null, prev_page_url: null };
function response(data: unknown, status = 200) {
    return { ok: status < 400, status, json: () => Promise.resolve(data) };
}
function mockReads(url: string) {
    if (url.includes('/setup')) return setup;
    if (url.includes('/forecast'))
        return {
            unweighted: '99999999999999.123456',
            weighted: '24999999999999.780864',
            open_count: 1,
            known_count: 1,
            unknown_count: 0,
            currency: 'LKR',
            label: 'Estimates only; not recognized revenue.',
        };
    if (url.includes('/leads/lead')) return { record: lead, history: [], activities: [] };
    if (url.includes('/opportunities'))
        return {
            ...page,
            data: [
                {
                    ...lead,
                    id: 'opportunity',
                    title: 'Actual opportunity',
                    status: 'open',
                    pipeline_id: 'pipeline',
                    stage_id: 'stage',
                    currency: 'LKR',
                    expected_amount: '99999999999999.123456',
                },
            ],
        };
    return page;
}
describe('CRM workflows', () => {
    it('clears the previous estimate while a different pipeline estimate is loading', async () => {
        let finish: ((value: ReturnType<typeof response>) => void) | undefined;
        vi.stubGlobal(
            'fetch',
            vi.fn().mockImplementation((url: string) =>
                url.includes('/forecast') && url.includes('pipeline_id=pipeline')
                    ? new Promise((resolve) => {
                          finish = resolve;
                      })
                    : Promise.resolve(response(mockReads(url))),
            ),
        );
        render(<CRM {...props} />);
        fireEvent.click(screen.getByRole('tab', { name: 'Opportunities' }));
        await screen.findByText('24999999999999.780864');
        fireEvent.change(screen.getByLabelText('Pipeline'), { target: { value: 'pipeline' } });
        await screen.findByText('Loading estimates…');
        expect(screen.queryByText('24999999999999.780864')).toBeNull();
        finish?.(
            response({
                ...(mockReads('/forecast') as Record<string, unknown>),
                weighted: '8.000000',
            }),
        );
        await screen.findByText('8.000000');
    });
    it('offers assigned-record actions without privileged conversion or pipeline configuration', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockImplementation((url: string) => Promise.resolve(response(mockReads(url)))),
        );
        render(<CRM {...props} />);
        fireEvent.click(
            await screen.findByRole('button', { name: 'Open Actual prospect inquiry' }),
        );
        await screen.findByRole('region', { name: 'Record details' });
        expect(screen.queryByRole('button', { name: 'Convert to opportunity' })).toBeNull();
        expect(screen.getByRole('button', { name: 'Plan activity' })).toBeTruthy();
        fireEvent.click(screen.getByRole('tab', { name: 'Pipeline settings' }));
        expect(screen.queryByRole('button', { name: '+ New pipeline' })).toBeNull();
    });
    it('requires explicit existing-customer selection after a conversion collision and preserves decimal strings', async () => {
        let converts = 0;
        const fetcher = vi.fn().mockImplementation((url: string, options: { method: string }) => {
            if (options.method === 'POST' && url.endsWith('/convert')) {
                converts++;
                return Promise.resolve(
                    converts === 1
                        ? response(
                              {
                                  message: 'Select an existing customer.',
                                  errors: { contact_id: ['Explicit selection required.'] },
                              },
                              422,
                          )
                        : response({ id: 'opportunity' }),
                );
            }
            return Promise.resolve(response(mockReads(url)));
        });
        vi.stubGlobal('fetch', fetcher);
        render(<CRM {...props} canAdmin />);
        fireEvent.click(
            await screen.findByRole('button', { name: 'Open Actual prospect inquiry' }),
        );
        fireEvent.click(await screen.findByRole('button', { name: 'Convert to opportunity' }));
        fireEvent.change(screen.getByLabelText('Pipeline'), { target: { value: 'pipeline' } });
        fireEvent.change(screen.getByLabelText('Stage'), { target: { value: 'stage' } });
        fireEvent.change(screen.getByLabelText('Expected amount (LKR)'), {
            target: { value: '99999999999999.123456' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        await screen.findByText('Explicit selection required.');
        fireEvent.change(screen.getByLabelText('Customer action'), {
            target: { value: 'existing' },
        });
        fireEvent.change(screen.getByLabelText('Customer'), { target: { value: 'customer' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        await screen.findByText('CRM record saved.');
        const calls = fetcher.mock.calls.filter(([url]) => url.endsWith('/convert'));
        expect(calls).toHaveLength(2);
        const body = JSON.parse(calls[1][1].body);
        expect(body.customer_mode).toBe('existing');
        expect(body.contact_id).toBe('customer');
        expect(body.expected_amount).toBe('99999999999999.123456');
        expect(calls[1][1].headers['Idempotency-Key']).toBe(calls[0][1].headers['Idempotency-Key']);
    });
    it('retains a lead form and idempotency key after a network failure', async () => {
        let creates = 0;
        const fetcher = vi.fn().mockImplementation((url: string, options: { method: string }) => {
            if (options.method === 'POST') {
                creates++;
                return creates === 1
                    ? Promise.reject(new Error('Connection interrupted'))
                    : Promise.resolve(response({ id: 'lead' }, 201));
            }
            return Promise.resolve(response(mockReads(url)));
        });
        vi.stubGlobal('fetch', fetcher);
        render(<CRM {...props} />);
        fireEvent.click(screen.getByRole('button', { name: '+ New lead' }));
        fireEvent.change(screen.getByLabelText('Title'), { target: { value: 'New inquiry' } });
        fireEvent.change(screen.getByLabelText('Contact name'), { target: { value: 'Prospect' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        await screen.findByText('Connection interrupted');
        expect((screen.getByLabelText('Title') as HTMLInputElement).value).toBe('New inquiry');
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        await screen.findByText('CRM record saved.');
        const calls = fetcher.mock.calls.filter(([, options]) => options.method === 'POST');
        expect(calls[0][1].headers['Idempotency-Key']).toBe(calls[1][1].headers['Idempotency-Key']);
    });
    it('shows exact estimates and a real paginated stage board without illustrative KPIs', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockImplementation((url: string) => Promise.resolve(response(mockReads(url)))),
        );
        render(<CRM {...props} />);
        fireEvent.click(screen.getByRole('tab', { name: 'Opportunities' }));
        await screen.findByText('Actual opportunity');
        await screen.findByText('24999999999999.780864');
        expect(screen.getByText('Real pipeline · Qualified')).toBeTruthy();
        expect(screen.queryByText('Avg. sales cycle')).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'List' }));
        await waitFor(() =>
            expect(screen.getByRole('button', { name: 'Open Actual opportunity' })).toBeTruthy(),
        );
    });
    it('shows module setup guidance without requesting unauthorized CRM data', () => {
        const fetcher = vi.fn();
        vi.stubGlobal('fetch', fetcher);
        render(<CRM {...props} available={false} />);
        expect(screen.getByRole('heading', { name: 'CRM is not active' })).toBeTruthy();
        expect(fetcher).not.toHaveBeenCalled();
    });
});
