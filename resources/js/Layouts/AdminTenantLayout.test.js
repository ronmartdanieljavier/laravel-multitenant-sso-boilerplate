import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import AdminTenantLayout from './AdminTenantLayout.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: { post: vi.fn() },
    usePage: vi.fn(),
}));

function makePageMock(url = '/admin/tenants/5/settings', tenant = { id: 5, name: 'Acme Corp', slug: 'acme' }) {
    return {
        props: { tenant, auth: { user: { name: 'Admin' } }, flash: {} },
        url,
    };
}

async function mountLayout(url = '/admin/tenants/5/settings', tenant = { id: 5, name: 'Acme Corp', slug: 'acme' }) {
    const { usePage } = await import('@inertiajs/vue3');
    usePage.mockReturnValue(makePageMock(url, tenant));

    return mount(AdminTenantLayout, {
        slots: { default: '<p>Page content</p>' },
        attachTo: document.body,
    });
}

describe('AdminTenantLayout', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('renders the SSO Admin brand in the sidebar', async () => {
        const wrapper = await mountLayout();
        expect(wrapper.text()).toContain('SSO Admin');
    });

    it('renders the five main nav links', async () => {
        const wrapper = await mountLayout();
        const text = wrapper.text();
        expect(text).toContain('Dashboard');
        expect(text).toContain('Users');
        expect(text).toContain('Apps');
        expect(text).toContain('Tenants');
        expect(text).toContain('Settings');
    });

    it('renders slot content', async () => {
        const wrapper = await mountLayout();
        expect(wrapper.text()).toContain('Page content');
    });

    it('renders the tenant sub-nav when tenant prop is present', async () => {
        const wrapper = await mountLayout();
        const text = wrapper.text();
        expect(text).toContain('Settings');
        expect(text).toContain('Users');
        expect(text).toContain('Reports');
        expect(text).toContain('Errors');
    });

    it('builds tenant sub-nav links using the tenant id', async () => {
        const wrapper = await mountLayout('/admin/tenants/5/settings', { id: 5, name: 'Acme', slug: 'acme' });
        const links = wrapper.findAll('a').map(a => a.attributes('href'));
        expect(links).toContain('/admin/tenants/5/settings');
        expect(links).toContain('/admin/tenants/5/users');
        expect(links).toContain('/admin/tenants/5/reports');
        expect(links).toContain('/admin/tenants/5/errors');
    });

    it('shows the tenant name in the sub-nav bar', async () => {
        const wrapper = await mountLayout('/admin/tenants/5/settings', { id: 5, name: 'Acme Corp', slug: 'acme' });
        expect(wrapper.text()).toContain('Acme Corp');
    });

    it('highlights the Settings link when on the settings page', async () => {
        const wrapper = await mountLayout('/admin/tenants/5/settings');
        const settingsLink = wrapper.findAll('a').find(a => a.attributes('href') === '/admin/tenants/5/settings');
        expect(settingsLink?.classes().join(' ')).toContain('blue-500');
    });

    it('highlights the Reports link when on the reports page', async () => {
        const wrapper = await mountLayout('/admin/tenants/5/reports');
        const reportsLink = wrapper.findAll('a').find(a => a.attributes('href') === '/admin/tenants/5/reports');
        expect(reportsLink?.classes().join(' ')).toContain('blue');
    });

    it('highlights the Errors link when on the errors page', async () => {
        const wrapper = await mountLayout('/admin/tenants/5/errors');
        const errorsLink = wrapper.findAll('a').find(a => a.attributes('href') === '/admin/tenants/5/errors');
        expect(errorsLink?.classes().join(' ')).toContain('blue-500');
    });

    it('highlights the Errors link when on an error detail page', async () => {
        const wrapper = await mountLayout('/admin/tenants/5/errors/abc-123');
        const errorsLink = wrapper.findAll('a').find(a => a.attributes('href') === '/admin/tenants/5/errors');
        expect(errorsLink?.classes().join(' ')).toContain('blue-500');
    });

    it('renders a sign out button that posts to /logout', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = await mountLayout();
        const btn = wrapper.find('button');
        expect(btn.text()).toMatch(/sign out/i);
        await btn.trigger('click');
        expect(router.post).toHaveBeenCalledWith('/logout');
    });

    it('does not render sub-nav when tenant is null', async () => {
        const { usePage } = await import('@inertiajs/vue3');
        usePage.mockReturnValue({ props: { tenant: null, auth: {}, flash: {} }, url: '/admin' });

        const wrapper = mount(AdminTenantLayout, {
            slots: { default: '<p>content</p>' },
            attachTo: document.body,
        });

        const links = wrapper.findAll('a').map(a => a.attributes('href'));
        expect(links.some(h => h?.includes('/tenants/') && h?.includes('/settings'))).toBe(false);
        expect(links.some(h => h?.includes('/tenants/') && h?.includes('/reports'))).toBe(false);
    });

    it('strips query strings when computing active state', async () => {
        const wrapper = await mountLayout('/admin/tenants/5/settings?tab=email');
        const settingsLink = wrapper.findAll('a').find(a => a.attributes('href') === '/admin/tenants/5/settings');
        expect(settingsLink?.classes().join(' ')).toContain('blue-500');
    });

    it('renders the App Selection link pointing to /apps', async () => {
        const wrapper = await mountLayout();
        const links = wrapper.findAll('a').map(a => a.attributes('href'));
        expect(links).toContain('/apps');
    });

    it('App Selection link displays correct label', async () => {
        const wrapper = await mountLayout();
        const appLink = wrapper.findAll('a').find(a => a.attributes('href') === '/apps');
        expect(appLink?.text()).toMatch(/app selection/i);
    });
});
