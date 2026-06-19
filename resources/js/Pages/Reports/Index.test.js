import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import ReportsPage from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
}));

describe('Reports/Index', () => {
    it('renders the reports suite header', () => {
        const wrapper = mount(ReportsPage);

        expect(wrapper.text()).toContain('Reports Suite');
    });

    it('renders four summary cards', () => {
        const wrapper = mount(ReportsPage);
        const cards = wrapper.findAll('.grid > div');

        expect(cards.length).toBe(4);
    });

    it('renders all tabs', () => {
        const wrapper = mount(ReportsPage);
        const tabs = wrapper.findAll('button').filter(b => ['overview', 'users', 'sessions', 'exports'].includes(b.text()));

        expect(tabs.length).toBe(4);
    });

    it('renders report rows in the table', () => {
        const wrapper = mount(ReportsPage);
        const rows = wrapper.findAll('tbody tr');

        expect(rows.length).toBeGreaterThan(0);
    });

    it('switches active tab on click', async () => {
        const wrapper = mount(ReportsPage);
        const usersTab = wrapper.findAll('button').find(b => b.text() === 'users');

        await usersTab.trigger('click');

        expect(usersTab.classes()).toContain('border-blue-500');
    });
});
