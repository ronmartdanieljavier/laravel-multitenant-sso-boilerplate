import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import AdminPage from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: { post: vi.fn() },
    usePage: () => ({ props: { auth: { user: { name: 'Admin', email: 'admin@example.com', profile_picture_url: null } }, idleTimeoutMinutes: null } }),
}));

const defaultStats = {
    total_users: 42,
    active_users: 38,
    pending_invitation_users: 4,
    active_apps: 5,
    active_tenants: 12,
    active_sso_sessions: 99,
};

const defaultHealthSummary = {
    total: 12,
    healthy: 8,
    warning: 3,
    critical: 1,
};

const defaultRecentUsers = [
    { id: 1, name: 'Alice Reyes', email: 'alice@acme.com', is_active: true },
    { id: 2, name: 'Bob Santos', email: 'bob@globex.com', is_active: false },
];

const defaultPendingUsers = [
    { id: 3, name: 'Carol Tan', email: 'carol@initech.com', is_active: false, invitation_sent_at: '2026-06-20T10:00:00Z' },
];

const defaultUnresolvedErrors = {
    total: 5,
    error: 2,
    warning: 2,
    critical: 1,
    by_tenant: [
        { tenant_id: 10, tenant_name: 'Acme Corp', tenant_slug: 'acme', total: 3, error: 2, warning: 1, critical: 0 },
        { tenant_id: 11, tenant_name: 'Globex', tenant_slug: 'globex', total: 2, error: 0, warning: 1, critical: 1 },
    ],
};

const defaultReportQueue = {
    pending: 4,
    processing: 1,
    failed: 2,
    by_tenant: [
        { tenant_id: 20, tenant_name: 'Initech', tenant_slug: 'initech', pending: 3, processing: 1, failed: 2 },
        { tenant_id: 21, tenant_name: 'Umbrella', tenant_slug: 'umbrella', pending: 1, processing: 0, failed: 0 },
    ],
};

const defaultMigrationCompliance = {
    total: 3,
    up_to_date: 2,
    behind_count: 1,
    available_migrations: 10,
    behind: [
        { id: 30, name: 'Stale Corp', slug: 'stale', applied: 8, available: 10 },
    ],
};

const defaultProps = {
    stats: defaultStats,
    healthSummary: defaultHealthSummary,
    recentUsers: defaultRecentUsers,
    pendingUsers: defaultPendingUsers,
    unresolvedErrors: defaultUnresolvedErrors,
    reportQueue: defaultReportQueue,
    migrationCompliance: defaultMigrationCompliance,
};

describe('Admin/Index', () => {
    it('renders the dashboard heading', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });

        expect(wrapper.text()).toContain('Dashboard');
    });

    it('renders all four stat cards', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const cards = wrapper.findAll('.grid > div');

        expect(cards.length).toBeGreaterThanOrEqual(4);
    });

    it('renders live stat values from props', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });

        expect(wrapper.text()).toContain('42');
        expect(wrapper.text()).toContain('38 active · 4 pending');
    });

    it('renders the invite user button linking to /admin/users?invite=1', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const hrefs = wrapper.findAll('a').map(l => l.attributes('href'));

        expect(hrefs).toContain('/admin/users?invite=1');
    });

    it('renders the add tenant button linking to /admin/tenants?add=1', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const hrefs = wrapper.findAll('a').map(l => l.attributes('href'));

        expect(hrefs).toContain('/admin/tenants?add=1');
    });

    it('renders the settings shortcut button linking to /admin/settings', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const hrefs = wrapper.findAll('a').map(l => l.attributes('href'));

        expect(hrefs).toContain('/admin/settings');
    });

    it('renders the tenant health summary bar', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });

        expect(wrapper.text()).toContain('Tenant Health');
    });

    it('renders the three health status badges with correct counts', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const text = wrapper.text();

        expect(text).toContain('8 Healthy');
        expect(text).toContain('3 Warning');
        expect(text).toContain('1 Critical');
    });

    it('renders recent users table with real names', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });

        expect(wrapper.text()).toContain('Alice Reyes');
        expect(wrapper.text()).toContain('bob@globex.com');
    });

    it('shows Active badge for active users and Pending Invitation for inactive', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const text = wrapper.text();

        expect(text).toContain('Active');
        expect(text).toContain('Pending Invitation');
    });

    it('edit link navigates to /admin/users?edit=<id>', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const hrefs = wrapper.findAll('a').map(l => l.attributes('href'));

        expect(hrefs).toContain('/admin/users?edit=1');
        expect(hrefs).toContain('/admin/users?edit=2');
    });

    it('renders pending invitations section when there are pending users', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });

        expect(wrapper.text()).toContain('Pending Invitations');
        expect(wrapper.text()).toContain('Carol Tan');
        expect(wrapper.text()).toContain('carol@initech.com');
    });

    it('does not render pending invitations section when list is empty', () => {
        const wrapper = mount(AdminPage, { props: { ...defaultProps, pendingUsers: [] } });

        expect(wrapper.text()).not.toContain('Pending Invitations');
    });

    it('renders Resend button for each pending user', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });

        expect(wrapper.text()).toContain('Resend');
    });

    it('highlights the Total Users stat card when pending count is greater than zero', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const cards = wrapper.findAll('.grid > div');
        const totalCard = cards[0];

        expect(totalCard.classes().join(' ')).toContain('amber');
    });

    it('Total Users stat card has no amber highlight when pending count is zero', () => {
        const nopending = { ...defaultStats, pending_invitation_users: 0 };
        const wrapper = mount(AdminPage, { props: { ...defaultProps, stats: nopending, pendingUsers: [] } });
        const cards = wrapper.findAll('.grid > div');
        const totalCard = cards[0];

        expect(totalCard.classes().join(' ')).not.toContain('amber');
    });

    it('renders unresolved error logs section with total count', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });

        expect(wrapper.text()).toContain('Unresolved Error Logs');
        expect(wrapper.text()).toContain('5');
    });

    it('renders severity breakdown pills in the header', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const text = wrapper.text();

        expect(text).toContain('1 critical');
        expect(text).toContain('2 error');
        expect(text).toContain('2 warning');
    });

    it('renders per-tenant rows with tenant names', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const text = wrapper.text();

        expect(text).toContain('Acme Corp');
        expect(text).toContain('Globex');
    });

    it('links each tenant row to its error list', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const hrefs = wrapper.findAll('a').map(l => l.attributes('href'));

        expect(hrefs).toContain('/admin/tenants/10/errors');
        expect(hrefs).toContain('/admin/tenants/11/errors');
    });

    it('shows "All clear" when no unresolved errors', () => {
        const noErrors = { total: 0, error: 0, warning: 0, critical: 0, by_tenant: [] };
        const wrapper = mount(AdminPage, { props: { ...defaultProps, unresolvedErrors: noErrors } });

        expect(wrapper.text()).toContain('All clear');
    });

    it('renders report queue widget with status pills', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const text = wrapper.text();

        expect(text).toContain('Report Queue');
        expect(text).toContain('4 pending');
        expect(text).toContain('1 processing');
        expect(text).toContain('2 failed');
    });

    it('renders per-tenant rows in the report queue', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const text = wrapper.text();

        expect(text).toContain('Initech');
        expect(text).toContain('Umbrella');
    });

    it('links each tenant report row to its report queue page', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const hrefs = wrapper.findAll('a').map(l => l.attributes('href'));

        expect(hrefs).toContain('/admin/tenants/20/reports');
        expect(hrefs).toContain('/admin/tenants/21/reports');
    });

    it('shows "Queue empty" when all counts are zero', () => {
        const empty = { pending: 0, processing: 0, failed: 0, by_tenant: [] };
        const wrapper = mount(AdminPage, { props: { ...defaultProps, reportQueue: empty } });

        expect(wrapper.text()).toContain('Queue empty');
    });

    it('failed count has red styling', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const failedSpan = wrapper.findAll('span').find(s => s.text().includes('2 failed'));

        expect(failedSpan).toBeTruthy();
        expect(failedSpan.classes().join(' ')).toContain('red');
    });

    it('health badges link to tenants page with health filter', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const hrefs = wrapper.findAll('a').map(l => l.attributes('href'));

        expect(hrefs).toContain('/admin/tenants?health=healthy');
        expect(hrefs).toContain('/admin/tenants?health=warning');
        expect(hrefs).toContain('/admin/tenants?health=critical');
    });

    it('renders migration compliance widget with up-to-date and behind counts', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const text = wrapper.text();

        expect(text).toContain('Migration Compliance');
        expect(text).toContain('2 up-to-date');
        expect(text).toContain('1 behind');
    });

    it('renders behind tenant rows with name and migration counts', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });
        const text = wrapper.text();

        expect(text).toContain('Stale Corp');
        expect(text).toContain('Run migrations');
    });

    it('shows "All tenants up-to-date" when behind_count is zero', () => {
        const allGood = { total: 3, up_to_date: 3, behind_count: 0, available_migrations: 10, behind: [] };
        const wrapper = mount(AdminPage, { props: { ...defaultProps, migrationCompliance: allGood } });

        expect(wrapper.text()).toContain('All tenants up-to-date');
    });

    it('shows "behind by" count for each behind tenant', () => {
        const wrapper = mount(AdminPage, { props: defaultProps });

        expect(wrapper.text()).toContain('2');
    });
});
