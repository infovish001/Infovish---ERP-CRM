import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { FoundationShell } from './Foundation';
vi.mock('@inertiajs/react', () => ({ router: { reload: vi.fn() }, Head: () => null }));
afterEach(cleanup);
describe('foundation shell', () => {
    it('presents dependency failures without claiming readiness', () => {
        render(<FoundationShell readiness={{ status: 'unavailable', checks: { database: 'unavailable', redis: 'ready' } }} />);
        expect(screen.getByText('Some services need attention')).toBeTruthy();
        expect(screen.getByText('Unavailable')).toBeTruthy();
        expect(screen.getByText('Reachable')).toBeTruthy();
        expect(screen.queryByText('Total revenue')).toBeNull();
    });
    it('supports theme and mobile navigation through accessible controls', () => {
        render(<FoundationShell readiness={{ status: 'ready', checks: {} }} />);
        const theme = screen.getByRole('button', { name: 'Toggle dark theme' });
        fireEvent.click(theme);
        expect(theme.getAttribute('aria-pressed')).toBe('true');
        const menu = screen.getByRole('button', { name: 'Toggle navigation' });
        fireEvent.click(menu);
        expect(menu.getAttribute('aria-expanded')).toBe('true');
    });
});
