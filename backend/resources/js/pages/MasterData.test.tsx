import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import MasterData from './MasterData';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
}));
afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});
const props = {
    tenant: { name: 'Test workspace' },
    user: { name: 'Test member', role: 'member' },
    companies: [{ id: 'company-fixture', name: 'Test company', base_currency: 'LKR' }],
    initialCompany: 'company-fixture',
    modules: [
        {
            name: 'Parties',
            version: '1.0.0',
            installed_version: '1.0.0',
            enabled: true,
            entitled: true,
            available: true,
            dependencies: [],
        },
        {
            name: 'Catalog',
            version: '1.0.0',
            installed_version: '1.0.0',
            enabled: true,
            entitled: true,
            available: true,
            dependencies: [],
        },
    ],
    canManage: false,
};
const page = { data: [], current_page: 1, next_page_url: null, prev_page_url: null };
function respond(body: unknown, ok = true) {
    return { ok, status: ok ? 200 : 409, json: () => Promise.resolve(body) };
}
describe('master data workflows', () => {
    it('shows read-only records without edit or activation controls', async () => {
        vi.stubGlobal(
            'fetch',
            vi
                .fn()
                .mockResolvedValue(
                    respond({
                        ...page,
                        data: [
                            {
                                id: 'contact',
                                number: 'CON-000000001',
                                name: 'Actual contact',
                                email: null,
                                phone: null,
                                customer: true,
                                supplier: false,
                                active: true,
                                revision: 1,
                            },
                        ],
                    }),
                ),
        );
        render(<MasterData {...props} />);
        await screen.findByText('Actual contact');
        expect(screen.queryByRole('button', { name: 'Edit Actual contact' })).toBeNull();
        expect(screen.queryByRole('button', { name: /New contact/ })).toBeNull();
        fireEvent.click(screen.getByRole('tab', { name: 'Modules' }));
        expect(screen.queryByRole('button', { name: 'Deactivate' })).toBeNull();
    });
    it('sends one stable idempotency key across network retries and retains form values', async () => {
        let posts = 0;
        const fetcher = vi.fn().mockImplementation((_url: string, options: { method: string }) => {
            if (options.method === 'POST') {
                posts++;
                return posts === 1
                    ? Promise.reject(new Error('Network interrupted'))
                    : Promise.resolve(respond({ id: 'saved' }));
            }
            return Promise.resolve(respond(page));
        });
        vi.stubGlobal('fetch', fetcher);
        render(<MasterData {...props} canManage />);
        fireEvent.click(await screen.findByRole('button', { name: '+ New contact' }));
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'New customer' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save record' }));
        await screen.findByText('Network interrupted');
        expect((screen.getByLabelText('Name') as HTMLInputElement).value).toBe('New customer');
        fireEvent.click(screen.getByRole('button', { name: 'Save record' }));
        await screen.findByText('Record saved.');
        const mutations = fetcher.mock.calls.filter(([, options]) => options.method === 'POST');
        expect(mutations).toHaveLength(2);
        expect(mutations[0][1].headers['Idempotency-Key']).toBe(
            mutations[1][1].headers['Idempotency-Key'],
        );
        expect(mutations[0][1].credentials).toBe('same-origin');
    });
    it('handles unavailable modules and activates through the module action', async () => {
        const disabled = props.modules.map((m) => ({ ...m, enabled: false, available: false }));
        const fetcher = vi.fn().mockResolvedValue(respond({ data: props.modules }));
        vi.stubGlobal('fetch', fetcher);
        render(<MasterData {...props} modules={disabled} canManage />);
        expect(screen.getByRole('heading', { name: 'Parties is unavailable' })).toBeTruthy();
        expect(fetcher).not.toHaveBeenCalled();
        fireEvent.click(screen.getByRole('button', { name: 'View modules' }));
        fireEvent.click(screen.getAllByRole('button', { name: 'Activate' })[0]);
        await screen.findByText('Parties activated. Existing records are preserved.');
        expect(fetcher.mock.calls[0][0]).toBe('/api/v1/modules/Parties');
    });
    it('preserves decimal prices as text and supports search pagination', async () => {
        const fetcher = vi
            .fn()
            .mockImplementation((url: string) =>
                Promise.resolve(
                    respond(
                        url.includes('/products')
                            ? {
                                  ...page,
                                  next_page_url: 'page2',
                                  data: [
                                      {
                                          id: 'product',
                                          number: 'PRD-1',
                                          name: 'Service',
                                          type: 'service',
                                          unit: 'hour',
                                          active: true,
                                          revision: 1,
                                          variants: [
                                              {
                                                  id: 'variant',
                                                  sku: 'SVC',
                                                  name: 'Standard',
                                                  attributes: {},
                                                  list_price: '12345678901234.123456',
                                                  currency: 'LKR',
                                                  active: true,
                                                  revision: 1,
                                              },
                                          ],
                                      },
                                  ],
                              }
                            : page,
                    ),
                ),
            );
        vi.stubGlobal('fetch', fetcher);
        render(<MasterData {...props} />);
        fireEvent.click(screen.getByRole('tab', { name: 'Products & services' }));
        await screen.findByText('LKR 12345678901234.123456');
        fireEvent.change(screen.getByLabelText('Search records'), { target: { value: 'SVC' } });
        fireEvent.click(screen.getByRole('button', { name: 'Search' }));
        await waitFor(() =>
            expect(fetcher.mock.calls.some(([url]) => url.includes('q=SVC'))).toBe(true),
        );
        await waitFor(() =>
            expect(
                (screen.getByRole('button', { name: 'Next' }) as HTMLButtonElement).disabled,
            ).toBe(false),
        );
        fireEvent.click(screen.getByRole('button', { name: 'Next' }));
        await waitFor(() =>
            expect(fetcher.mock.calls.some(([url]) => url.includes('page=2'))).toBe(true),
        );
    });
});
