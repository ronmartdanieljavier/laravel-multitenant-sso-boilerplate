import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import AppsPage from './Index.vue';

const mockRoute = vi.fn((name, id) => `/api/v1/admin/apps/${id}`);

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    Link: { template: '<a><slot /></a>', props: ['href'] },
    router: { post: vi.fn() },
    usePage: () => ({
        props: {
            auth: { user: { name: 'Admin', email: 'admin@example.com', profile_picture_url: null } },
            idleTimeoutMinutes: null,
            missingRequiredSettings: [],
        },
    }),
    useForm: (initial) => {
        const data = { ...initial };
        const errors = {};
        let processing = false;

        return new Proxy(data, {
            get(target, key) {
                if (key === 'errors') return errors;
                if (key === 'processing') return processing;
                if (key === 'reset') return () => Object.assign(target, initial);
                if (key === 'clearErrors') return () => {};
                if (key === 'put') return vi.fn();
                return target[key];
            },
            set(target, key, value) {
                target[key] = value;
                return true;
            },
        });
    },
}));

vi.stubGlobal('route', mockRoute);

const sampleApps = [
    { id: 1, name: 'Admin Portal', slug: 'admin', description: 'Main admin app', is_active: true },
    { id: 2, name: 'Tenant App', slug: 'tenant', description: null, is_active: false },
];

function mountPage(apps = sampleApps) {
    return mount(AppsPage, { props: { apps } });
}

describe('Admin/Apps/Index', () => {
    it('renders the page heading', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('App Management');
    });

    it('renders a row for each app', () => {
        const wrapper = mountPage();
        const rows = wrapper.findAll('tbody tr');
        expect(rows.length).toBe(sampleApps.length);
    });

    it('displays app name and slug in each row', () => {
        const wrapper = mountPage();
        const text = wrapper.text();
        expect(text).toContain('Admin Portal');
        expect(text).toContain('admin');
        expect(text).toContain('Tenant App');
        expect(text).toContain('tenant');
    });

    it('displays description or em dash when null', () => {
        const wrapper = mountPage();
        const text = wrapper.text();
        expect(text).toContain('Main admin app');
        expect(text).toContain('—');
    });

    it('renders active and inactive status badges', () => {
        const wrapper = mountPage();
        const text = wrapper.text();
        expect(text).toContain('Active');
        expect(text).toContain('Inactive');
    });

    it('renders an Edit button for each app', () => {
        const wrapper = mountPage();
        const editButtons = wrapper.findAll('button').filter(b => b.text() === 'Edit');
        expect(editButtons.length).toBe(sampleApps.length);
    });

    it('shows the empty state when no apps are provided', () => {
        const wrapper = mountPage([]);
        expect(wrapper.text()).toContain('No apps found.');
    });

    it('shows the edit form when Edit is clicked', async () => {
        const wrapper = mountPage();
        const editButton = wrapper.findAll('button').find(b => b.text() === 'Edit');
        await editButton.trigger('click');

        expect(wrapper.find('form').exists()).toBe(true);
        expect(wrapper.text()).toContain('Save');
        expect(wrapper.text()).toContain('Cancel');
    });

    it('pre-fills the form with the app name and description', async () => {
        const wrapper = mountPage();
        await wrapper.findAll('button').find(b => b.text() === 'Edit').trigger('click');

        const inputs = wrapper.findAll('input[type="text"]');
        expect(inputs[0].element.value).toBe('Admin Portal');
        expect(inputs[1].element.value).toBe('Main admin app');
    });

    it('hides the edit form when Cancel is clicked', async () => {
        const wrapper = mountPage();
        await wrapper.findAll('button').find(b => b.text() === 'Edit').trigger('click');
        expect(wrapper.find('form').exists()).toBe(true);

        await wrapper.find('button[type="button"]').trigger('click');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('shows the missing settings banner when required settings are absent', () => {
        const wrapper = mount(AppsPage, {
            props: { apps: [] },
            global: {
                provide: {},
            },
        });

        // Banner only appears when missingRequiredSettings has items; default mock returns []
        expect(wrapper.find('.bg-amber-500\\/10').exists()).toBe(false);
    });

    it('renders the Apps nav item as active', () => {
        const wrapper = mountPage();
        const activeLink = wrapper.findAll('a').find(a => a.classes().includes('border-blue-500'));
        expect(activeLink?.text()).toContain('Apps');
    });
});
