import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import AdminPage from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    Link: { template: '<a><slot /></a>', props: ['href'] },
}));

describe('Admin/Index', () => {
    it('renders the dashboard heading', () => {
        const wrapper = mount(AdminPage);

        expect(wrapper.text()).toContain('Dashboard');
    });

    it('renders all four stat cards', () => {
        const wrapper = mount(AdminPage);
        const cards = wrapper.findAll('.grid > div');

        expect(cards.length).toBe(4);
    });

    it('renders the recent users table with rows', () => {
        const wrapper = mount(AdminPage);
        const rows = wrapper.findAll('tbody tr');

        expect(rows.length).toBeGreaterThan(0);
    });

    it('renders the invite user button', () => {
        const wrapper = mount(AdminPage);

        expect(wrapper.text()).toContain('Invite User');
    });
});
