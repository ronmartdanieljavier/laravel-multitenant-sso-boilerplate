import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import TenantsPage from './Index.vue';
import * as inertia from '@inertiajs/vue3';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        Head: { template: '<slot />' },
        Link: { template: '<a><slot /></a>', props: ['href'] },
        router: { post: vi.fn(), get: vi.fn(), patch: vi.fn() },
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
        is_active: true,
        is_maintenance: false,
        health_status: 'healthy',
        last_report_at: null,
        user_count: 3,
        logged_in_count: 2,
        pending_reports: 0,
        failed_reports: 0,
        unresolved_errors: 0,
        migrations_behind: 0,
    },
    {
        id: 2,
        name: 'Beta Inc',
        slug: 'beta',
        is_active: true,
        is_maintenance: true,
        health_status: 'warning',
        last_report_at: null,
        user_count: 1,
        logged_in_count: 0,
        pending_reports: 1,
        failed_reports: 0,
        unresolved_errors: 0,
        migrations_behind: 0,
    },
];

const sampleSummary = { total: 2, healthy: 1, warning: 1, critical: 0, maintenance: 1 };

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

    describe('maintenance mode', () => {
        beforeEach(() => {
            vi.clearAllMocks();
        });

        it('shows the In Maintenance summary card', () => {
            const wrapper = mountPage();
            expect(wrapper.text()).toContain('In Maintenance');
        });

        it('shows the maintenance count from summary', () => {
            const wrapper = mountPage();
            // summary has maintenance: 1
            expect(wrapper.text()).toContain('1');
        });

        it('shows a Maintenance badge for a tenant with is_maintenance true', () => {
            const wrapper = mountPage();
            expect(wrapper.text()).toContain('Maintenance');
        });

        it('shows End Maint. button for a tenant in maintenance', () => {
            const wrapper = mountPage();
            const btn = wrapper.findAll('button').find(b => b.text() === 'End Maint.');
            expect(btn).toBeDefined();
        });

        it('shows Maintenance toggle button for an active tenant not in maintenance', () => {
            const wrapper = mountPage();
            const btn = wrapper.findAll('button').find(b => b.text() === 'Maintenance');
            expect(btn).toBeDefined();
        });

        it('shows Maintenance: All On and Maintenance: All Off buttons in header', () => {
            const wrapper = mountPage();
            const allOn = wrapper.findAll('button').find(b => b.text().includes('Maintenance: All On'));
            const allOff = wrapper.findAll('button').find(b => b.text().includes('Maintenance: All Off'));
            expect(allOn).toBeDefined();
            expect(allOff).toBeDefined();
        });

        it('calls router.patch with is_maintenance true when Maintenance: All On is confirmed', async () => {
            vi.spyOn(window, 'confirm').mockReturnValue(true);
            const wrapper = mountPage();
            const allOnBtn = wrapper.findAll('button').find(b => b.text().includes('Maintenance: All On'));
            await allOnBtn.trigger('click');
            expect(inertia.router.patch).toHaveBeenCalledWith(
                '/admin/tenants/maintenance/all',
                { is_maintenance: true },
            );
        });

        it('calls router.patch with is_maintenance false when Maintenance: All Off is confirmed', async () => {
            vi.spyOn(window, 'confirm').mockReturnValue(true);
            const wrapper = mountPage();
            const allOffBtn = wrapper.findAll('button').find(b => b.text().includes('Maintenance: All Off'));
            await allOffBtn.trigger('click');
            expect(inertia.router.patch).toHaveBeenCalledWith(
                '/admin/tenants/maintenance/all',
                { is_maintenance: false },
            );
        });

        it('does not call router.patch when Maintenance: All On is cancelled', async () => {
            vi.spyOn(window, 'confirm').mockReturnValue(false);
            const wrapper = mountPage();
            const allOnBtn = wrapper.findAll('button').find(b => b.text().includes('Maintenance: All On'));
            await allOnBtn.trigger('click');
            expect(inertia.router.patch).not.toHaveBeenCalled();
        });

        it('calls router.patch to toggle maintenance on for a specific tenant when confirmed', async () => {
            vi.spyOn(window, 'confirm').mockReturnValue(true);
            const wrapper = mountPage();
            // Acme Corp (id:1) is not in maintenance — button text is "Maintenance"
            const btn = wrapper.findAll('button').find(b => b.text() === 'Maintenance');
            await btn.trigger('click');
            expect(inertia.router.patch).toHaveBeenCalledWith(
                '/admin/tenants/1/maintenance',
                { is_maintenance: true },
            );
        });

        it('calls router.patch to end maintenance for a specific tenant when confirmed', async () => {
            vi.spyOn(window, 'confirm').mockReturnValue(true);
            const wrapper = mountPage();
            // Beta Inc (id:2) is in maintenance — button text is "End Maint."
            const btn = wrapper.findAll('button').find(b => b.text() === 'End Maint.');
            await btn.trigger('click');
            expect(inertia.router.patch).toHaveBeenCalledWith(
                '/admin/tenants/2/maintenance',
                { is_maintenance: false },
            );
        });
    });

    describe('users online display', () => {
        it('shows total user count for each tenant', () => {
            const wrapper = mountPage();
            expect(wrapper.text()).toContain('3');
            expect(wrapper.text()).toContain('1');
        });

        it('shows online count in green when users are logged in', () => {
            const wrapper = mountPage();
            // Acme Corp has logged_in_count: 2
            const onlineEl = wrapper.findAll('span').find(s => s.text() === '2 online');
            expect(onlineEl).toBeDefined();
            expect(onlineEl.classes()).toContain('text-emerald-400');
        });

        it('shows 0 online in muted colour when no users are logged in', () => {
            const wrapper = mountPage();
            // Beta Inc has logged_in_count: 0
            const onlineEl = wrapper.findAll('span').find(s => s.text() === '0 online');
            expect(onlineEl).toBeDefined();
            expect(onlineEl.classes()).toContain('text-slate-600');
        });
    });

    describe('compact two-row actions layout', () => {
        it('shows navigation links (Edit, Settings, Users, Reports, Errors) for each tenant', () => {
            const wrapper = mountPage();
            const text = wrapper.text();
            expect(text).toContain('Edit');
            expect(text).toContain('Settings');
            expect(text).toContain('Users');
            expect(text).toContain('Reports');
            expect(text).toContain('Errors');
        });

        it('shows admin action buttons (Migrate, Deactivate/Activate, Delete) for each tenant', () => {
            const wrapper = mountPage();
            const text = wrapper.text();
            expect(text).toContain('Migrate');
            expect(text).toContain('Delete');
        });

        it('shows Deactivate for an active tenant', () => {
            const wrapper = mountPage();
            const btn = wrapper.findAll('button').find(b => b.text() === 'Deactivate');
            expect(btn).toBeDefined();
        });

        it('renders a Users link for each tenant', () => {
            const wrapper = mountPage();
            // The Link mock renders <a> without forwarding href; verify the text is present in the row
            const rows = wrapper.findAll('tbody tr');
            const acmeRow = rows.find(r => r.text().includes('Acme Corp'));
            expect(acmeRow.text()).toContain('Users');
        });
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
