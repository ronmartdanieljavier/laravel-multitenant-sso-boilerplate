import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import ErrorLogsPage from './ErrorLogs.vue';

vi.mock('../../Layouts/TenantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

vi.mock('../Partials/TourButton.vue', () => ({
    default: { template: '<button />' },
}));

vi.mock('../../composables/useTour', () => ({
    useTour: () => ({ startTour: vi.fn() }),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: { get: vi.fn() },
    usePage: () => ({
        props: {
            auth: { user: { name: 'Alice', email: 'alice@example.com' } },
            tenant: { id: 1, name: 'Acme Corp', slug: 'acme' },
            flash: {},
            idleTimeoutMinutes: null,
        },
        url: '/tenant/errors',
    }),
}));

const openLog = {
    id: 10,
    error_code: 'E-ACME-A3F9B12C',
    exception_class: 'RuntimeException',
    message: 'Something went wrong',
    severity: 'error',
    resolved: false,
    resolved_at: null,
    created_at: '2026-06-25T10:00:00Z',
};

const resolvedLog = {
    id: 11,
    error_code: 'E-ACME-B4G1C23D',
    exception_class: 'LogicException',
    message: 'Fixed issue',
    severity: 'warning',
    resolved: true,
    resolved_at: '2026-06-25T12:00:00Z',
    created_at: '2026-06-25T09:00:00Z',
};

const criticalLog = {
    id: 12,
    error_code: 'E-ACME-CRITICAL1',
    exception_class: 'FatalException',
    message: 'System failure',
    severity: 'critical',
    resolved: false,
    resolved_at: null,
    created_at: '2026-06-25T08:00:00Z',
};

function mountPage(logs = [openLog], filters = {}) {
    return mount(ErrorLogsPage, {
        props: { logs, filters },
        attachTo: document.body,
    });
}

describe('Tenant/ErrorLogs', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('renders the page heading', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Error Logs');
    });

    it('renders stat cards with correct counts', () => {
        const wrapper = mountPage([openLog, resolvedLog, criticalLog]);
        const text = wrapper.text();
        expect(text).toContain('3'); // total
        expect(text).toContain('2'); // unresolved (openLog + criticalLog)
    });

    it('renders a row for each log', () => {
        const wrapper = mountPage([openLog, resolvedLog]);
        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
    });

    it('shows error codes as links in the table', () => {
        const wrapper = mountPage([openLog]);
        const link = wrapper.find(`a[href="/tenant/errors/${openLog.id}"]`);
        expect(link.exists()).toBe(true);
        expect(link.text()).toContain('E-ACME-A3F9B12C');
    });

    it('shows short exception class name', () => {
        const wrapper = mountPage([openLog]);
        expect(wrapper.text()).toContain('RuntimeException');
    });

    it('shows error severity badge', () => {
        const wrapper = mountPage([openLog]);
        expect(wrapper.text()).toContain('Error');
    });

    it('shows warning severity badge', () => {
        const wrapper = mountPage([resolvedLog]);
        expect(wrapper.text()).toContain('Warning');
    });

    it('shows critical severity badge', () => {
        const wrapper = mountPage([criticalLog]);
        expect(wrapper.text()).toContain('Critical');
    });

    it('shows resolved status badge for resolved logs', () => {
        const wrapper = mountPage([resolvedLog]);
        expect(wrapper.text()).toContain('Resolved');
    });

    it('shows open status badge for unresolved logs', () => {
        const wrapper = mountPage([openLog]);
        expect(wrapper.text()).toContain('Open');
    });

    it('shows empty state when no logs exist', () => {
        const wrapper = mountPage([]);
        expect(wrapper.text()).toContain('No error logs found');
    });

    it('does not show table when logs list is empty', () => {
        const wrapper = mountPage([]);
        expect(wrapper.find('table').exists()).toBe(false);
    });

    it('calls router.get when changing severity filter', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mountPage([openLog]);
        const select = wrapper.find('select');
        await select.setValue('error');
        expect(router.get).toHaveBeenCalledWith(
            '/tenant/errors',
            expect.objectContaining({ severity: 'error' }),
            expect.any(Object),
        );
    });

    it('calls router.get when toggling unresolved filter', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mountPage([openLog]);
        const checkbox = wrapper.find('input[type="checkbox"]');
        await checkbox.setValue(true);
        expect(router.get).toHaveBeenCalledWith(
            '/tenant/errors',
            expect.objectContaining({ unresolved: 1 }),
            expect.any(Object),
        );
    });

    it('does not include severity in filter request when cleared', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mountPage([openLog], { severity: 'error' });
        const select = wrapper.find('select');
        await select.setValue('');
        expect(router.get).toHaveBeenCalledWith(
            '/tenant/errors',
            expect.not.objectContaining({ severity: expect.anything() }),
            expect.any(Object),
        );
    });

    it('does not show resolve or delete action buttons (read-only view)', () => {
        const wrapper = mountPage([openLog, resolvedLog]);
        const buttonTexts = wrapper.findAll('button').map(b => b.text());
        expect(buttonTexts).not.toContain('Resolve');
        expect(buttonTexts).not.toContain('Reopen');
        expect(buttonTexts).not.toContain('Delete');
    });

    it('pre-selects severity filter from props', () => {
        const wrapper = mountPage([openLog], { severity: 'error' });
        const select = wrapper.find('select');
        expect(select.element.value).toBe('error');
    });

    it('pre-checks unresolved filter from props', () => {
        const wrapper = mountPage([openLog], { unresolved: true });
        const checkbox = wrapper.find('input[type="checkbox"]');
        expect(checkbox.element.checked).toBe(true);
    });

    it('shows the truncated exception message', () => {
        const wrapper = mountPage([openLog]);
        expect(wrapper.text()).toContain('Something went wrong');
    });

    it('applies reduced opacity to resolved rows', () => {
        const wrapper = mountPage([resolvedLog]);
        const row = wrapper.find('tbody tr');
        expect(row.classes()).toContain('opacity-50');
    });
});
