import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import ErrorDetailPage from './ErrorDetail.vue';

vi.mock('../../../Layouts/AdminTenantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: {
        post: vi.fn(),
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

const errorLog = {
    id: 10,
    error_code: 'E-ACME-A3F9B12C',
    exception_class: 'RuntimeException',
    message: 'Something went wrong in the app',
    file: '/app/Services/OrderService.php',
    line: 42,
    severity: 'error',
    resolved: false,
    resolved_at: null,
    created_at: '2026-06-25T10:00:00Z',
    request_url: 'https://app.example.com/api/v1/tenant/orders',
    request_method: 'POST',
    request_params: { order_id: 123, amount: '99.99' },
    request_headers: { 'x-app': 'myapp', 'x-tenant': 'acme' },
    trace: [
        { function: 'handle', file: '/app/Http/Controllers/OrderController.php', line: 20 },
        { function: 'dispatch', file: '/app/Services/OrderService.php', line: 42 },
    ],
    context: { user_id: 5, tenant_id: 1 },
};

const resolvedLog = {
    ...errorLog,
    id: 11,
    error_code: 'E-ACME-RESOLVED',
    resolved: true,
    resolved_at: '2026-06-25T12:00:00Z',
};

function mountPage(log = errorLog) {
    return mount(ErrorDetailPage, {
        props: { tenant, log },
        attachTo: document.body,
    });
}

describe('Admin/Tenants/ErrorDetail', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('renders the error code prominently', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('E-ACME-A3F9B12C');
    });

    it('renders the exception class', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('RuntimeException');
    });

    it('renders the exception message', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Something went wrong in the app');
    });

    it('renders the file and line number', () => {
        const wrapper = mountPage();
        const text = wrapper.text();
        expect(text).toContain('OrderService.php');
        expect(text).toContain('42');
    });

    it('renders the request URL', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('/api/v1/tenant/orders');
    });

    it('renders the request method', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('POST');
    });

    it('renders stack trace frames', () => {
        const wrapper = mountPage();
        const text = wrapper.text();
        expect(text).toContain('OrderController.php');
    });

    it('shows resolved status for resolved logs', () => {
        const wrapper = mountPage(resolvedLog);
        expect(wrapper.text()).toContain('Resolved');
    });

    it('shows open status for unresolved logs', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Open');
    });

    it('shows resolve button for unresolved logs', () => {
        const wrapper = mountPage();
        const btn = wrapper.findAll('button').find(b => b.text().includes('Resolve'));
        expect(btn).toBeDefined();
    });

    it('shows reopen button for resolved logs', () => {
        const wrapper = mountPage(resolvedLog);
        const btn = wrapper.findAll('button').find(b => b.text().includes('Reopen'));
        expect(btn).toBeDefined();
    });

    it('calls router.patch on resolve', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mountPage();
        const btn = wrapper.findAll('button').find(b => b.text().includes('Resolve'));
        await btn.trigger('click');
        expect(router.patch).toHaveBeenCalledWith('/admin/tenants/1/errors/10/resolve');
    });

    it('renders breadcrumb back to error list', () => {
        const wrapper = mountPage();
        const links = wrapper.findAll('a');
        const backLink = links.find(l => l.attributes('href') === '/admin/tenants/1/errors');
        expect(backLink).toBeDefined();
    });
});
