import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import ErrorsPage from './Errors.vue';

vi.mock('../../../Layouts/AdminTenantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    Link: { template: '<a><slot /></a>', props: ['href'] },
    router: {
        post: vi.fn(),
        get: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
    usePage: () => ({
        props: {
            auth: { user: { name: 'Admin', email: 'admin@example.com', profile_picture_url: null } },
            flash: {},
            missingRequiredSettings: [],
        },
    }),
}));

const tenant = { id: 1, name: 'Acme Corp', slug: 'acme' };

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

function mountPage(logs = [openLog, resolvedLog], filters = {}) {
    return mount(ErrorsPage, {
        props: { tenant, logs, filters },
        attachTo: document.body,
    });
}

describe('Admin/Tenants/Errors', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('renders the tenant name in the page heading', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Acme Corp');
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

    it('shows error codes in the table', () => {
        const wrapper = mountPage([openLog]);
        expect(wrapper.text()).toContain('E-ACME-A3F9B12C');
    });

    it('shows severity badge for each log', () => {
        const wrapper = mountPage([openLog]);
        expect(wrapper.text()).toContain('Error');
    });

    it('shows critical severity badge', () => {
        const wrapper = mountPage([criticalLog]);
        expect(wrapper.text()).toContain('Critical');
    });

    it('shows resolved status for resolved logs', () => {
        const wrapper = mountPage([resolvedLog]);
        expect(wrapper.text()).toContain('Resolved');
    });

    it('shows open status for unresolved logs', () => {
        const wrapper = mountPage([openLog]);
        expect(wrapper.text()).toContain('Open');
    });

    it('calls router.patch to resolve a log', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mountPage([openLog]);
        const resolveBtn = wrapper.findAll('button').find(b => b.text() === 'Resolve');
        await resolveBtn.trigger('click');
        expect(router.patch).toHaveBeenCalledWith('/admin/tenants/1/errors/10/resolve');
    });

    it('calls router.patch to unresolve a resolved log', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mountPage([resolvedLog]);
        const reopenBtn = wrapper.findAll('button').find(b => b.text() === 'Reopen');
        await reopenBtn.trigger('click');
        expect(router.patch).toHaveBeenCalledWith('/admin/tenants/1/errors/11/unresolve');
    });

    it('calls router.get when changing severity filter', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mountPage([openLog]);
        const select = wrapper.find('select');
        await select.setValue('error');
        expect(router.get).toHaveBeenCalled();
    });

    it('renders empty state when no logs exist', () => {
        const wrapper = mountPage([]);
        expect(wrapper.text()).toContain('No error logs found');
    });
});
