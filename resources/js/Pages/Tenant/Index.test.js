import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import TenantPage from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
}));

describe('Tenant/Index', () => {
    it('renders the welcome message', () => {
        const wrapper = mount(TenantPage);

        expect(wrapper.text()).toContain('Good morning');
    });

    it('renders four app cards', () => {
        const wrapper = mount(TenantPage);
        const cards = wrapper.findAll('a[href="#"]').filter(a => a.text().trim().length > 0);

        expect(cards.length).toBeGreaterThanOrEqual(4);
    });

    it('renders the recent activity section', () => {
        const wrapper = mount(TenantPage);

        expect(wrapper.text()).toContain('Recent Activity');
    });

    it('renders the portal header', () => {
        const wrapper = mount(TenantPage);

        expect(wrapper.text()).toContain('Tenant Portal');
    });
});
