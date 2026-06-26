import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import TenantSwitcher from './TenantSwitcher.vue';

vi.mock('@inertiajs/vue3', () => ({
    router: { post: vi.fn() },
}));

const twoTenants = [
    { id: 1, name: 'Acme Corp', slug: 'acme', isCurrent: true },
    { id: 2, name: 'Beta Corp', slug: 'beta', isCurrent: false },
];

const threeTenants = [
    ...twoTenants,
    { id: 3, name: 'Gamma Inc', slug: 'gamma', isCurrent: false },
];

function mountSwitcher(tenants = twoTenants) {
    return mount(TenantSwitcher, {
        props: { tenants },
        attachTo: document.body,
    });
}

describe('TenantSwitcher', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('renders nothing when only one tenant is provided', () => {
        const wrapper = mount(TenantSwitcher, {
            props: { tenants: [twoTenants[0]] },
            attachTo: document.body,
        });
        expect(wrapper.find('button').exists()).toBe(false);
    });

    it('renders nothing when tenants array is empty', () => {
        const wrapper = mountSwitcher([]);
        expect(wrapper.find('button').exists()).toBe(false);
    });

    it('renders the toggle button showing the current tenant name', () => {
        const wrapper = mountSwitcher();
        expect(wrapper.find('button').text()).toContain('Acme Corp');
    });

    it('dropdown is hidden by default', () => {
        const wrapper = mountSwitcher();
        expect(wrapper.text()).not.toContain('Beta Corp');
    });

    it('opens the dropdown when the toggle button is clicked', async () => {
        const wrapper = mountSwitcher();
        await wrapper.find('button').trigger('click');
        expect(wrapper.text()).toContain('Beta Corp');
    });

    it('shows the current tenant with a checkmark indicator', async () => {
        const wrapper = mountSwitcher();
        await wrapper.find('button').trigger('click');
        const currentRow = wrapper.findAll('div').find(d => d.text().includes('Acme Corp') && d.find('svg').exists());
        expect(currentRow).toBeTruthy();
    });

    it('shows non-current tenants as clickable buttons', async () => {
        const wrapper = mountSwitcher();
        await wrapper.find('button').trigger('click');
        const buttons = wrapper.findAll('button').filter(b => b.text().includes('Beta Corp'));
        expect(buttons.length).toBe(1);
    });

    it('closes the dropdown and posts to /tenant/switch when a tenant is selected', async () => {
        const wrapper = mountSwitcher();
        await wrapper.find('button').trigger('click');

        const betaBtn = wrapper.findAll('button').find(b => b.text().includes('Beta Corp'));
        await betaBtn.trigger('click');

        const { router } = await import('@inertiajs/vue3');
        expect(router.post).toHaveBeenCalledWith('/tenant/switch', { tenant_slug: 'beta' });
        expect(wrapper.text()).not.toContain('Beta Corp');
    });

    it('lists all tenants in the dropdown', async () => {
        const wrapper = mountSwitcher(threeTenants);
        await wrapper.find('button').trigger('click');
        expect(wrapper.text()).toContain('Acme Corp');
        expect(wrapper.text()).toContain('Beta Corp');
        expect(wrapper.text()).toContain('Gamma Inc');
    });

    it('closes the dropdown when the backdrop is clicked', async () => {
        const wrapper = mountSwitcher();
        await wrapper.find('button').trigger('click');
        expect(wrapper.text()).toContain('Beta Corp');

        const backdrop = wrapper.find('.fixed.inset-0');
        await backdrop.trigger('click');
        expect(wrapper.text()).not.toContain('Beta Corp');
    });

    it('toggles closed when toggle button is clicked while open', async () => {
        const wrapper = mountSwitcher();
        const toggle = wrapper.find('button');
        await toggle.trigger('click');
        expect(wrapper.text()).toContain('Beta Corp');
        await toggle.trigger('click');
        expect(wrapper.text()).not.toContain('Beta Corp');
    });
});
