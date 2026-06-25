import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import TenantPage from './Index.vue';

vi.mock('../../Layouts/TenantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: { post: vi.fn() },
    usePage: () => ({
        props: {
            auth: { user: { name: 'Bob Smith', email: 'bob@example.com', profile_picture_url: null } },
            tenant: { id: 1, name: 'Acme Corp', slug: 'acme' },
            flash: {},
            idleTimeoutMinutes: null,
        },
        url: '/tenant',
    }),
    useIdleTimeout: vi.fn(),
}));

vi.mock('../../composables/useIdleTimeout', () => ({ useIdleTimeout: vi.fn() }));

describe('Tenant/Index', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('renders the welcome message with the user first name', () => {
        const wrapper = mount(TenantPage, { attachTo: document.body });
        expect(wrapper.text()).toMatch(/Good (morning|afternoon|evening), Bob/);
    });

    it('renders the Reports Suite app card linking to the report queue', () => {
        const wrapper = mount(TenantPage, { attachTo: document.body });
        const link = wrapper.findAll('a').find(a => a.text().includes('Reports Suite'));
        expect(link?.attributes('href')).toBe('/tenant/reports');
    });

    it('renders the recent activity section', () => {
        const wrapper = mount(TenantPage, { attachTo: document.body });
        expect(wrapper.text()).toContain('Recent Activity');
    });

    it('renders four app cards', () => {
        const wrapper = mount(TenantPage, { attachTo: document.body });
        const cards = wrapper.findAll('a').filter(a => ['Admin Portal', 'Reports Suite', 'Tenant Hub', 'Billing'].some(n => a.text().includes(n)));
        expect(cards).toHaveLength(4);
    });
});
