import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import ReportQueuePage from './ReportQueue.vue';

vi.mock('../../Layouts/TenantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: { post: vi.fn(), visit: vi.fn() },
    usePage: () => ({
        props: {
            auth: { user: { name: 'Alice', email: 'alice@example.com' } },
            tenant: { id: 1, name: 'Acme Corp', slug: 'acme' },
            flash: {},
            idleTimeoutMinutes: null,
        },
        url: '/tenant/reports',
    }),
    usePoll: vi.fn(() => ({ start: vi.fn(), stop: vi.fn() })),
}));

const tenant = { id: 1, name: 'Acme Corp', slug: 'acme' };

const pendingReport = {
    id: 'r-001',
    type: 'user_activity',
    format: 'screen',
    delivery: 'none',
    status: 'pending',
    user: { name: 'Alice' },
    created_at: '2026-06-25T10:00:00Z',
    started_at: null,
    completed_at: null,
    file_path: null,
    error_message: null,
};

const processingReport = {
    ...pendingReport,
    id: 'r-002',
    status: 'processing',
    started_at: '2026-06-25T10:01:00Z',
};

const successReport = {
    ...pendingReport,
    id: 'r-003',
    status: 'success',
    format: 'pdf',
    started_at: '2026-06-25T10:00:00Z',
    completed_at: '2026-06-25T10:00:05Z',
    file_path: 'reports/r-003.pdf',
};

const failedReport = {
    ...pendingReport,
    id: 'r-004',
    status: 'failed',
    error_message: 'Generator failed: out of memory',
};

function makePaginator(data = []) {
    return {
        data,
        total: data.length,
        current_page: 1,
        last_page: 1,
        per_page: 25,
        prev_page_url: null,
        next_page_url: null,
    };
}

function mountPage(data = [pendingReport]) {
    return mount(ReportQueuePage, {
        props: { tenant, reports: makePaginator(data) },
        attachTo: document.body,
    });
}

describe('Tenant/ReportQueue', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('renders the page heading', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Report Queue');
    });

    it('renders a row for each report', () => {
        const wrapper = mountPage([pendingReport, processingReport]);
        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
    });

    it('shows Pending status badge', () => {
        const wrapper = mountPage([pendingReport]);
        expect(wrapper.text()).toContain('Pending');
    });

    it('shows Processing status badge', () => {
        const wrapper = mountPage([processingReport]);
        expect(wrapper.text()).toContain('Processing');
    });

    it('shows Done status badge for successful reports', () => {
        const wrapper = mountPage([successReport]);
        expect(wrapper.text()).toContain('Done');
    });

    it('shows Failed status badge', () => {
        const wrapper = mountPage([failedReport]);
        expect(wrapper.text()).toContain('Failed');
    });

    it('shows the error message for failed reports', () => {
        const wrapper = mountPage([failedReport]);
        expect(wrapper.text()).toContain('Generator failed: out of memory');
    });

    it('shows a Download link for successful reports with a file', () => {
        const wrapper = mountPage([successReport]);
        const link = wrapper.find('a[href*="download"]');
        expect(link.exists()).toBe(true);
        expect(link.text()).toBe('Download');
    });

    it('does not show a Download link for pending reports', () => {
        const wrapper = mountPage([pendingReport]);
        const links = wrapper.findAll('a').filter(a => a.text() === 'Download');
        expect(links).toHaveLength(0);
    });

    it('shows the report type', () => {
        const wrapper = mountPage([pendingReport]);
        expect(wrapper.text()).toContain('user_activity');
    });

    it('shows the requesting user name', () => {
        const wrapper = mountPage([pendingReport]);
        expect(wrapper.text()).toContain('Alice');
    });

    it('shows stat cards', () => {
        const wrapper = mountPage([pendingReport, processingReport, successReport, failedReport]);
        const text = wrapper.text();
        expect(text).toContain('Total');
        expect(text).toContain('Pending');
        expect(text).toContain('Processing');
        expect(text).toContain('Failed');
    });

    it('shows the Live indicator when there are active jobs', () => {
        const wrapper = mountPage([pendingReport]);
        expect(wrapper.text()).toContain('Live');
    });

    it('does not show the Live indicator when all jobs are complete', () => {
        const wrapper = mountPage([successReport]);
        expect(wrapper.text()).not.toContain('Live');
    });

    it('renders empty state when no reports exist', () => {
        const wrapper = mountPage([]);
        expect(wrapper.text()).toContain('No report jobs found');
    });

    it('hides pagination when only one page', () => {
        const wrapper = mountPage([pendingReport]);
        expect(wrapper.find('button[disabled]').exists()).toBe(false);
    });

    it('starts polling via usePoll', async () => {
        const { usePoll } = await import('@inertiajs/vue3');
        mountPage([pendingReport]);
        expect(usePoll).toHaveBeenCalledWith(4000, { only: ['reports'] }, { autoStart: true });
    });
});
