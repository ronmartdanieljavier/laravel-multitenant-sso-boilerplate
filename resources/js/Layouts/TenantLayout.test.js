import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import TenantLayout from './TenantLayout.vue';

vi.mock('../Pages/Partials/TenantSwitcher.vue', () => ({
    default: { template: '<div data-testid="tenant-switcher" />', props: ['tenants'] },
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: { post: vi.fn() },
    usePage: vi.fn(),
}));

vi.mock('../composables/useIdleTimeout', () => ({ useIdleTimeout: vi.fn() }));

const baseUser = { name: 'Bob Smith', email: 'bob@example.com', profile_picture_url: null };
const baseTenant = { id: 1, name: 'Acme Corp', slug: 'acme' };

function makePageMock({
    user = baseUser,
    tenant = baseTenant,
    url = '/tenant',
    availableTenants = null,
} = {}) {
    return {
        props: {
            auth: { user },
            tenant,
            availableTenants,
            flash: {},
            idleTimeoutMinutes: null,
        },
        url,
    };
}

async function mountLayout(options = {}) {
    const { usePage } = await import('@inertiajs/vue3');
    usePage.mockReturnValue(makePageMock(options));

    return mount(TenantLayout, {
        slots: { default: '<p>Page content</p>' },
        attachTo: document.body,
    });
}

describe('TenantLayout', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('renders the tenant name in the sidebar header', async () => {
        const wrapper = await mountLayout();
        expect(wrapper.text()).toContain('Acme Corp');
    });

    it('renders the tenant slug in the sidebar header', async () => {
        const wrapper = await mountLayout();
        expect(wrapper.text()).toContain('acme');
    });

    it('renders the Dashboard nav link', async () => {
        const wrapper = await mountLayout();
        const links = wrapper.findAll('a').map(a => a.attributes('href'));
        expect(links).toContain('/tenant');
    });

    it('renders the Report Queue nav link', async () => {
        const wrapper = await mountLayout();
        const links = wrapper.findAll('a').map(a => a.attributes('href'));
        expect(links).toContain('/tenant/reports');
    });

    it('renders slot content', async () => {
        const wrapper = await mountLayout();
        expect(wrapper.text()).toContain('Page content');
    });

    it('renders the profile link', async () => {
        const wrapper = await mountLayout();
        const profileLink = wrapper.findAll('a').find(a => a.attributes('href') === '/profile');
        expect(profileLink).toBeTruthy();
    });

    it('renders the user name in the profile section', async () => {
        const wrapper = await mountLayout();
        expect(wrapper.text()).toContain('Bob Smith');
    });

    it('renders the user email in the profile section', async () => {
        const wrapper = await mountLayout();
        expect(wrapper.text()).toContain('bob@example.com');
    });

    it('renders the user initial avatar when no profile picture', async () => {
        const wrapper = await mountLayout({ user: { ...baseUser, profile_picture_url: null } });
        expect(wrapper.text()).toContain('B');
    });

    it('renders profile picture img when provided', async () => {
        const wrapper = await mountLayout({
            user: { ...baseUser, profile_picture_url: 'https://example.com/photo.jpg' },
        });
        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('https://example.com/photo.jpg');
    });

    it('calls router.post /logout when sign-out icon button is clicked', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = await mountLayout();
        const logoutBtn = wrapper.find('button[title="Sign out"]');
        expect(logoutBtn.exists()).toBe(true);
        await logoutBtn.trigger('click');
        expect(router.post).toHaveBeenCalledWith('/logout');
    });

    it('highlights the Dashboard link when on the dashboard', async () => {
        const wrapper = await mountLayout({ url: '/tenant' });
        const dashLink = wrapper.findAll('a').find(a => a.attributes('href') === '/tenant');
        expect(dashLink?.classes().join(' ')).toContain('emerald');
    });

    it('highlights the Report Queue link when on the reports page', async () => {
        const wrapper = await mountLayout({ url: '/tenant/reports' });
        const reportsLink = wrapper.findAll('a').find(a => a.attributes('href') === '/tenant/reports');
        expect(reportsLink?.classes().join(' ')).toContain('emerald');
    });

    it('does not highlight Dashboard when on the reports page', async () => {
        const wrapper = await mountLayout({ url: '/tenant/reports' });
        const dashLink = wrapper.findAll('a').find(a => a.attributes('href') === '/tenant');
        expect(dashLink?.classes().join(' ')).not.toContain('emerald');
    });

    it('renders TenantSwitcher with available tenants', async () => {
        const tenants = [
            { id: 1, name: 'Acme', slug: 'acme', isCurrent: true },
            { id: 2, name: 'Beta Corp', slug: 'beta', isCurrent: false },
        ];
        const wrapper = await mountLayout({ availableTenants: tenants });
        const switcher = wrapper.find('[data-testid="tenant-switcher"]');
        expect(switcher.exists()).toBe(true);
    });

    it('renders TenantSwitcher with empty array when availableTenants is null', async () => {
        const wrapper = await mountLayout({ availableTenants: null });
        const switcher = wrapper.find('[data-testid="tenant-switcher"]');
        expect(switcher.exists()).toBe(true);
    });
});
