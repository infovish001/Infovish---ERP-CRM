import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import Workspace from './Workspace';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        children,
        href,
        className,
    }: {
        children: ReactNode;
        href: string;
        className?: string;
    }) => (
        <a href={href} className={className}>
            {children}
        </a>
    ),
    router: { post: vi.fn(), put: vi.fn() },
    useForm: (data: Record<string, unknown>) => ({
        data,
        errors: {},
        processing: false,
        setData: vi.fn(),
        post: vi.fn(),
        reset: vi.fn(),
    }),
}));
afterEach(cleanup);

const identity = {
    tenant: { id: 'tenant-fixture', name: 'Test workspace' },
    user: { name: 'Test member', email: 'member@example.test', role: 'member' },
};
describe('workspace permissions and empty states', () => {
    it('gives an ungranted member an empty organization without administration controls', () => {
        render(
            <Workspace
                {...identity}
                permissions={{
                    manageOrganization: false,
                    manageMemberships: false,
                    viewAudit: false,
                }}
                companies={[]}
                branches={[]}
                departments={[]}
                scope={{ companyId: null, branchId: null }}
            />,
        );
        expect(screen.getByText('No companies are available.', { exact: false })).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Members and access' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Audit history' })).toBeNull();
        expect(screen.queryByText('Create an organization record')).toBeNull();
        expect(screen.getByLabelText('Active company').hasAttribute('disabled')).toBe(true);
    });
    it('offers company creation to an owner and retains accessible theme controls', () => {
        render(
            <Workspace
                {...identity}
                permissions={{ manageOrganization: true, manageMemberships: true, viewAudit: true }}
                companies={[]}
                branches={[]}
                departments={[]}
                scope={{ companyId: null, branchId: null }}
            />,
        );
        expect(screen.getByRole('button', { name: 'Create company' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Members and access' })).toBeTruthy();
        const theme = screen.getByRole('button', { name: 'Toggle dark theme' });
        fireEvent.click(theme);
        expect(theme.getAttribute('aria-pressed')).toBe('true');
    });
});
