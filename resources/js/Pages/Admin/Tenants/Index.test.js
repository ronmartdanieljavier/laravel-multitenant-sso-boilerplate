import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import TenantsPage from './Index.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        Head: { template: '<slot />' },
        Link: { template: '<a><slot /></a>', props: ['href'] },
        router: { post: vi.fn(), get: vi.fn() },
        usePage: () => ({
            props: {
                auth: { user: { name: 'Admin', email: 'admin@example.com', profile_picture_url: null } },
                missingRequiredSettings: [],
            },
        }),
        useForm: (initial) => {
            const data = reactive({ ...initial, errors: {}, processing: false });
            data.reset = () => Object.assign(data, { ...initial });
            data.clearErrors = () => {};
            data.post = vi.fn();
            data.put = vi.fn();
            return data;
        },
    };
});

const sampleTenants = [
    {
        id: 1,
        name: 'Acme Corp',
        slug: 'acme',
        health_status: 'healthy',
        last_report_at: null,
        pending_reports: 0,
        failed_reports: 0,
        unresolved_errors: 0,
        migrations_behind: 0,
    },
    {
        id: 2,
        name: 'Beta Inc',
        slug: 'beta',
        health_status: 'warning',
        last_report_at: null,
        pending_reports: 1,
        failed_reports: 0,
        unresolved_errors: 0,
        migrations_behind: 0,
    },
];

const sampleSummary = { total: 2, healthy: 1, warning: 1, critical: 0 };

function mountPage(tenants = sampleTenants, summary = sampleSummary) {
    return mount(TenantsPage, {
        props: { tenants, summary },
        attachTo: document.body,
    });
}

function bodyText() {
    return document.body.textContent ?? '';
}

describe('Admin/Tenants/Index', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        window.history.replaceState({}, '', window.location.pathname);
    });

    it('renders the page heading', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Tenants');
    });

    it('renders a row for each tenant', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Acme Corp');
        expect(wrapper.text()).toContain('Beta Inc');
    });

    it('shows the summary card totals', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Total Tenants');
    });

    it('shows the empty state when no tenants are provided', () => {
        const wrapper = mountPage([]);
        expect(wrapper.text()).toContain('No tenants found');
    });

    it('create modal is hidden by default', () => {
        mountPage();
        expect(bodyText()).not.toContain('Create Tenant');
    });

    it('opens the create modal when Add Tenant button is clicked', async () => {
        const wrapper = mountPage();
        const addBtn = wrapper.findAll('button').find(b => b.text().includes('Add Tenant'));
        await addBtn.trigger('click');

        expect(bodyText()).toContain('Create Tenant');
    });

    it('closes the create modal when Cancel is clicked', async () => {
        const wrapper = mountPage();
        const addBtn = wrapper.findAll('button').find(b => b.text().includes('Add Tenant'));
        await addBtn.trigger('click');
        expect(bodyText()).toContain('Create Tenant');

        const cancelBtn = Array.from(document.querySelectorAll('button[type="button"]'))
            .find(b => b.textContent.trim() === 'Cancel');
        cancelBtn.click();
        await wrapper.vm.$nextTick();

        expect(bodyText()).not.toContain('Create Tenant');
    });

    describe('?add query param deep-link', () => {
        it('opens the create modal when mounted with ?add param', async () => {
            window.history.replaceState({}, '', '?add=1');
            const wrapper = mountPage();
            await wrapper.vm.$nextTick();

            expect(bodyText()).toContain('Create Tenant');
        });

        it('strips the ?add param from the URL after mounting', async () => {
            window.history.replaceState({}, '', '?add=1');
            const wrapper = mountPage();
            await wrapper.vm.$nextTick();

            expect(window.location.search).not.toContain('add');
        });

        it('does not open the create modal when mounted without ?add param', async () => {
            const wrapper = mountPage();
            await wrapper.vm.$nextTick();

            expect(bodyText()).not.toContain('Create Tenant');
        });
    });
});
