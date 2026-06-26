import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import ErrorLogPage from './ErrorLog.vue';

vi.mock('../../Layouts/TenantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    usePage: () => ({
        props: {
            auth: { user: { name: 'Alice', email: 'alice@example.com' } },
            tenant: { id: 1, name: 'Acme Corp', slug: 'acme' },
            flash: {},
            idleTimeoutMinutes: null,
        },
        url: '/tenant/errors/10',
    }),
}));

const errorLog = {
    id: 10,
    error_code: 'E-ACME-A3F9B12C',
    exception_class: 'App\\Services\\OrderService',
    message: 'Something went wrong in the app',
    severity: 'error',
    resolved: false,
    resolved_at: null,
    created_at: '2026-06-25T10:00:00Z',
    request_url: 'https://app.example.com/api/v1/tenant/orders',
    request_method: 'POST',
    request_params: { order_id: 123, amount: '99.99' },
    context: { ip: '192.168.1.1', user_agent: 'Mozilla/5.0' },
};

const warningLog = {
    ...errorLog,
    id: 11,
    error_code: 'E-ACME-WARN0001',
    severity: 'warning',
    message: 'Deprecated method called',
};

const resolvedLog = {
    ...errorLog,
    id: 12,
    error_code: 'E-ACME-RESOLVED',
    resolved: true,
    resolved_at: '2026-06-25T12:00:00Z',
};

const criticalLog = {
    ...errorLog,
    id: 13,
    error_code: 'E-ACME-CRITICAL',
    severity: 'critical',
    message: 'Database connection lost',
};

function mountPage(log = errorLog) {
    return mount(ErrorLogPage, {
        props: { log },
        attachTo: document.body,
    });
}

describe('Tenant/ErrorLog', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('renders the error code prominently', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('E-ACME-A3F9B12C');
    });

    it('shows the short exception class name', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('OrderService');
    });

    it('does not show fully-qualified class namespace', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).not.toContain('App\\Services\\OrderService');
    });

    it('renders the exception message', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Something went wrong in the app');
    });

    it('shows the request URL', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('/api/v1/tenant/orders');
    });

    it('shows the request method badge', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('POST');
    });

    it('renders request params as formatted JSON', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('order_id');
    });

    it('renders context fields', () => {
        const wrapper = mountPage();
        const text = wrapper.text();
        expect(text).toContain('ip');
        expect(text).toContain('192.168.1.1');
    });

    it('shows error severity badge', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Error');
    });

    it('shows warning severity badge', () => {
        const wrapper = mountPage(warningLog);
        expect(wrapper.text()).toContain('Warning');
    });

    it('shows critical severity badge', () => {
        const wrapper = mountPage(criticalLog);
        expect(wrapper.text()).toContain('Critical');
    });

    it('shows open status for unresolved log', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Open');
    });

    it('shows resolved status for resolved log', () => {
        const wrapper = mountPage(resolvedLog);
        expect(wrapper.text()).toContain('Resolved');
    });

    it('renders breadcrumb link back to error logs list', () => {
        const wrapper = mountPage();
        const link = wrapper.find('a[href="/tenant/errors"]');
        expect(link.exists()).toBe(true);
        expect(link.text()).toContain('Error Logs');
    });

    it('shows the support note with the error code', () => {
        const wrapper = mountPage();
        const text = wrapper.text();
        expect(text).toContain('contacting support');
        expect(text).toContain('E-ACME-A3F9B12C');
    });

    it('shows a copy button for the error code', () => {
        const wrapper = mountPage();
        const btn = wrapper.findAll('button').find(b => b.text() === 'Copy');
        expect(btn).toBeDefined();
    });

    it('does not expose resolve, reopen, or delete actions (read-only view)', () => {
        const wrapper = mountPage();
        const buttonTexts = wrapper.findAll('button').map(b => b.text());
        expect(buttonTexts).not.toContain('Mark Resolved');
        expect(buttonTexts).not.toContain('Reopen');
        expect(buttonTexts).not.toContain('Delete');
    });

    it('does not render request params section when params are empty', () => {
        const log = { ...errorLog, request_params: {} };
        const wrapper = mountPage(log);
        expect(wrapper.text()).not.toContain('Parameters');
    });

    it('does not render context section when context is empty', () => {
        const log = { ...errorLog, context: {} };
        const wrapper = mountPage(log);
        expect(wrapper.text()).not.toContain('Context');
    });

    it('does not render context section when context is null', () => {
        const log = { ...errorLog, context: null };
        const wrapper = mountPage(log);
        expect(wrapper.text()).not.toContain('Context');
    });

    it('copies the error code to clipboard on button click', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText },
            writable: true,
            configurable: true,
        });

        const wrapper = mountPage();
        const btn = wrapper.findAll('button').find(b => b.text() === 'Copy');
        await btn.trigger('click');
        expect(writeText).toHaveBeenCalledWith('E-ACME-A3F9B12C');
    });

    it('does not crash when request_params is null', () => {
        const log = { ...errorLog, request_params: null };
        expect(() => mountPage(log)).not.toThrow();
    });
});
