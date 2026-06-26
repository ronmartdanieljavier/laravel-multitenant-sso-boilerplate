import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import ReportQueuePage from './ReportQueue.vue';

vi.mock('../../Layouts/TenantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: { post: vi.fn(), visit: vi.fn(), put: vi.fn(), delete: vi.fn() },
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
    useForm: vi.fn((defaults) => {
        const form = {
            ...defaults,
            errors: {},
            processing: false,
            reset: vi.fn(),
            transform: vi.fn().mockReturnThis(),
            data: vi.fn(() => ({ ...defaults })),
            post: vi.fn((_url, opts) => { opts?.onSuccess?.(); }),
        };
        return form;
    }),
}));

vi.mock('../../composables/useTour', () => ({
    useTour: () => ({ startTour: vi.fn() }),
}));

vi.mock('../Partials/TourButton.vue', () => ({
    default: { template: '<button />' },
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

const activeSubscription = {
    id: 1,
    type: 'documents_summary',
    format: 'screen',
    frequency: 'daily',
    delivery: 'none',
    is_active: true,
    last_dispatched_at: null,
    created_at: '2026-06-25T09:00:00Z',
};

const pausedSubscription = {
    ...activeSubscription,
    id: 2,
    is_active: false,
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

function mountPage(reportData = [pendingReport], subscriptions = []) {
    return mount(ReportQueuePage, {
        props: { tenant, reports: makePaginator(reportData), subscriptions },
        attachTo: document.body,
    });
}

describe('Tenant/ReportQueue', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    // ── Existing report queue tests ──────────────────────────────────────────

    it('renders the page heading', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Report Queue');
    });

    it('renders a row for each report', () => {
        const wrapper = mountPage([pendingReport, processingReport]);
        const rows = wrapper.findAll('tbody tr');
        expect(rows.length).toBeGreaterThanOrEqual(2);
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
        const link = wrapper.find(`a[href="/tenant/reports/${successReport.id}/download"]`);
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

    // ── Generate Report button & modal ───────────────────────────────────────

    it('renders the Generate Report button', () => {
        const wrapper = mountPage();
        const btn = wrapper.find('button#tour-rq-generate');
        expect(btn.exists()).toBe(true);
        expect(btn.text()).toContain('Generate Report');
    });

    it('opens the generate report modal when button is clicked', async () => {
        const wrapper = mountPage();
        await wrapper.find('button#tour-rq-generate').trigger('click');
        expect(document.body.textContent).toContain('Dispatch Job');
    });

    it('closes the generate report modal on cancel', async () => {
        const wrapper = mountPage();
        await wrapper.find('button#tour-rq-generate').trigger('click');
        const cancel = Array.from(document.body.querySelectorAll('button')).find(b => b.textContent.includes('Cancel'));
        cancel.click();
        await wrapper.vm.$nextTick();
        expect(document.body.textContent).not.toContain('Dispatch Job');
    });

    // ── Subscriptions panel ──────────────────────────────────────────────────

    it('renders the Subscriptions section heading', () => {
        const wrapper = mountPage([], []);
        expect(wrapper.text()).toContain('Scheduled Subscriptions');
    });

    it('shows empty state when no subscriptions exist', () => {
        const wrapper = mountPage([], []);
        expect(wrapper.text()).toContain('No subscriptions yet');
    });

    it('renders a row for each subscription', () => {
        const wrapper = mountPage([], [activeSubscription, pausedSubscription]);
        expect(wrapper.text()).toContain('documents_summary');
    });

    it('shows Active badge for active subscription', () => {
        const wrapper = mountPage([], [activeSubscription]);
        expect(wrapper.text()).toContain('Active');
    });

    it('shows Paused badge for inactive subscription', () => {
        const wrapper = mountPage([], [pausedSubscription]);
        expect(wrapper.text()).toContain('Paused');
    });

    it('shows Pause action for active subscription', () => {
        const wrapper = mountPage([], [activeSubscription]);
        expect(wrapper.text()).toContain('Pause');
    });

    it('shows Resume action for paused subscription', () => {
        const wrapper = mountPage([], [pausedSubscription]);
        expect(wrapper.text()).toContain('Resume');
    });

    it('renders the New Subscription button', () => {
        const wrapper = mountPage([], []);
        expect(wrapper.text()).toContain('New Subscription');
    });

    it('opens the new subscription modal', async () => {
        const wrapper = mountPage([], []);
        const btn = wrapper.findAll('button').find(b => b.text().includes('New Subscription'));
        await btn.trigger('click');
        expect(document.body.textContent).toContain('Create Subscription');
    });

    it('calls router.put when toggle is clicked', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mountPage([], [activeSubscription]);
        const pauseBtn = wrapper.findAll('button').find(b => b.text() === 'Pause');
        await pauseBtn.trigger('click');
        expect(router.put).toHaveBeenCalled();
    });

    it('shows a Retry button for failed reports', () => {
        const wrapper = mountPage([failedReport]);
        const retryBtn = wrapper.findAll('button').find(b => b.text() === 'Retry');
        expect(retryBtn).toBeDefined();
    });

    it('does not show a Retry button for pending reports', () => {
        const wrapper = mountPage([pendingReport]);
        const retryBtn = wrapper.findAll('button').find(b => b.text() === 'Retry');
        expect(retryBtn).toBeUndefined();
    });

    it('calls router.post with retry URL when Retry is clicked', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mountPage([failedReport]);
        const retryBtn = wrapper.findAll('button').find(b => b.text() === 'Retry');
        await retryBtn.trigger('click');
        expect(router.post).toHaveBeenCalledWith(
            `/tenant/reports/${failedReport.id}/retry`,
            {},
            expect.objectContaining({ preserveScroll: true })
        );
    });

    it('calls router.delete when delete is clicked', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mountPage([], [activeSubscription]);
        const deleteBtn = wrapper.findAll('button').find(b => b.text() === 'Delete');
        await deleteBtn.trigger('click');
        expect(router.delete).toHaveBeenCalled();
    });
});
