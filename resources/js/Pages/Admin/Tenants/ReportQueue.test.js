import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import ReportQueuePage from './ReportQueue.vue';

vi.mock('../../../Layouts/AdminTenantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: { post: vi.fn(), visit: vi.fn() },
    usePage: () => ({
        props: {
            auth: { user: { name: 'Admin', email: 'admin@example.com' } },
            flash: {},
            missingRequiredSettings: [],
        },
    }),
    usePoll: vi.fn(() => ({ start: vi.fn(), stop: vi.fn() })),
}));

const tenant = { id: 3, name: 'Gamma Ltd', slug: 'gamma' };

const pending = {
    id: 'r-001', type: 'user_activity', format: 'screen', delivery: 'none',
    status: 'pending', user: { name: 'Alice' },
    created_at: '2026-06-25T10:00:00Z', started_at: null, completed_at: null,
    file_path: null, error_message: null,
};
const processing = { ...pending, id: 'r-002', status: 'processing', started_at: '2026-06-25T10:01:00Z' };
const success = {
    ...pending, id: 'r-003', status: 'success', format: 'pdf',
    started_at: '2026-06-25T10:00:00Z', completed_at: '2026-06-25T10:00:05Z', file_path: 'r.pdf',
};
const failed = { ...pending, id: 'r-004', status: 'failed', error_message: 'Out of memory' };

function makePaginator(data = []) {
    return { data, total: data.length, current_page: 1, last_page: 1, per_page: 25, prev_page_url: null, next_page_url: null };
}

function mountPage(data = [pending]) {
    return mount(ReportQueuePage, {
        props: { tenant, reports: makePaginator(data) },
        attachTo: document.body,
    });
}

describe('Admin/Tenants/ReportQueue', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('renders the tenant name in the heading', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Gamma Ltd');
    });

    it('renders stat cards', () => {
        const wrapper = mountPage([pending, processing, success, failed]);
        const text = wrapper.text();
        expect(text).toContain('Total');
        expect(text).toContain('Pending');
        expect(text).toContain('Processing');
        expect(text).toContain('Failed');
    });

    it('renders a row for each report', () => {
        const wrapper = mountPage([pending, processing]);
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
    });

    it('shows Pending badge', () => {
        expect(mountPage([pending]).text()).toContain('Pending');
    });

    it('shows Processing badge', () => {
        expect(mountPage([processing]).text()).toContain('Processing');
    });

    it('shows Done badge for success', () => {
        expect(mountPage([success]).text()).toContain('Done');
    });

    it('shows Failed badge', () => {
        expect(mountPage([failed]).text()).toContain('Failed');
    });

    it('shows error message for failed reports', () => {
        expect(mountPage([failed]).text()).toContain('Out of memory');
    });

    it('shows the requesting user name', () => {
        expect(mountPage([pending]).text()).toContain('Alice');
    });

    it('shows Live indicator when active jobs exist', () => {
        expect(mountPage([pending]).text()).toContain('Live');
    });

    it('does not show Live indicator when all jobs are complete', () => {
        expect(mountPage([success]).text()).not.toContain('Live');
    });

    it('renders empty state when no reports', () => {
        expect(mountPage([]).text()).toContain('No report jobs found');
    });

    it('renders breadcrumb link back to tenants list', () => {
        const wrapper = mountPage();
        const link = wrapper.findAll('a').find(a => a.attributes('href') === '/admin/tenants');
        expect(link).toBeDefined();
    });

    it('starts polling via usePoll', async () => {
        const { usePoll } = await import('@inertiajs/vue3');
        mountPage([pending]);
        expect(usePoll).toHaveBeenCalledWith(4000, { only: ['reports'] });
    });
});
