import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import TenantDashboardPage from './Index.vue';

vi.mock('../../Layouts/TenantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

vi.mock('../Partials/TourButton.vue', () => ({
    default: { template: '<button class="tour-btn" />' },
}));

vi.mock('../../composables/useTour', () => ({
    useTour: () => ({ startTour: vi.fn() }),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    usePage: () => ({
        props: {
            auth: { user: { name: 'Bob Smith', email: 'bob@example.com' } },
            tenant: { id: 1, name: 'Acme Corp', slug: 'acme' },
            flash: {},
        },
        url: '/tenant',
    }),
}));

// ── Fixtures ────────────────────────────────────────────────────────────────

const defaultStats = {
    pending_reports: 0,
    processing_reports: 0,
    failed_reports: 0,
    success_reports_this_month: 4,
    unresolved_errors: 0,
    critical_errors: 0,
    total_documents: 0,
};

const pendingReport = {
    id: 'r-001',
    type: 'user_activity',
    format: 'screen',
    status: 'pending',
    created_at: '2026-06-25T10:00:00Z',
};

const failedReport = {
    id: 'r-002',
    type: 'sales_summary',
    format: 'pdf',
    status: 'failed',
    created_at: '2026-06-25T09:00:00Z',
};

const successReport = {
    id: 'r-003',
    type: 'inventory',
    format: 'excel',
    status: 'success',
    created_at: '2026-06-24T08:00:00Z',
};

const openError = {
    id: 10,
    error_code: 'E-ACME-OPEN0001',
    exception_class: 'App\\Services\\SomeService',
    message: 'Something went wrong',
    severity: 'error',
    created_at: '2026-06-25T10:00:00Z',
};

const criticalError = {
    id: 11,
    error_code: 'E-ACME-CRIT0001',
    exception_class: 'App\\Jobs\\SomeJob',
    message: 'Critical system failure',
    severity: 'critical',
    created_at: '2026-06-25T09:00:00Z',
};

const docPdf = {
    id: 1,
    title: 'Annual Report 2026',
    file_name: 'annual_report.pdf',
    file_size: 204800,
    mime_type: 'application/pdf',
    created_at: '2026-06-25T10:00:00Z',
};

const docExcel = {
    id: 2,
    title: 'Sales Data Q2',
    file_name: 'sales_q2.xlsx',
    file_size: 51200,
    mime_type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    created_at: '2026-06-24T08:00:00Z',
};

function mountPage(overrides = {}) {
    return mount(TenantDashboardPage, {
        props: {
            stats: defaultStats,
            recentReports: [],
            recentErrors: [],
            recentDocuments: [],
            ...overrides,
        },
        attachTo: document.body,
    });
}

// ── Tests ────────────────────────────────────────────────────────────────────

describe('Tenant/Index — greeting', () => {
    afterEach(() => { document.body.innerHTML = ''; });

    it('greets with the user first name', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toMatch(/Good (morning|afternoon|evening), Bob/);
    });

    it('shows the subtitle', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain("Here's what's happening");
    });
});

describe('Tenant/Index — stats cards', () => {
    afterEach(() => { document.body.innerHTML = ''; });

    it('renders four stat cards', () => {
        const wrapper = mountPage();
        expect(wrapper.findAll('#stat-reports-active, #stat-reports-failed, #stat-errors, #stat-documents')).toHaveLength(4);
    });

    it('shows active reports as sum of pending and processing', () => {
        const wrapper = mountPage({
            stats: { ...defaultStats, pending_reports: 3, processing_reports: 2 },
        });
        expect(wrapper.find('#stat-reports-active').text()).toContain('5');
    });

    it('shows failed report count', () => {
        const wrapper = mountPage({
            stats: { ...defaultStats, failed_reports: 7 },
        });
        expect(wrapper.find('#stat-reports-failed').text()).toContain('7');
    });

    it('shows unresolved error count', () => {
        const wrapper = mountPage({
            stats: { ...defaultStats, unresolved_errors: 3 },
        });
        expect(wrapper.find('#stat-errors').text()).toContain('3');
    });

    it('shows total document count', () => {
        const wrapper = mountPage({
            stats: { ...defaultStats, total_documents: 12 },
        });
        expect(wrapper.find('#stat-documents').text()).toContain('12');
    });

    it('shows critical error sub-label when critical_errors > 0', () => {
        const wrapper = mountPage({
            stats: { ...defaultStats, unresolved_errors: 2, critical_errors: 1 },
        });
        expect(wrapper.find('#stat-errors').text()).toContain('1 critical');
    });

    it('shows "All clear" sub-label when failed_reports is 0', () => {
        const wrapper = mountPage({ stats: { ...defaultStats, failed_reports: 0 } });
        expect(wrapper.find('#stat-reports-failed').text()).toContain('All clear');
    });

    it('shows completed-this-month count in active reports sub-label', () => {
        const wrapper = mountPage({
            stats: { ...defaultStats, success_reports_this_month: 9 },
        });
        expect(wrapper.find('#stat-reports-active').text()).toContain('9 completed this month');
    });
});

describe('Tenant/Index — quick actions', () => {
    afterEach(() => { document.body.innerHTML = ''; });

    it('renders the Queue Report action linking to /tenant/reports', () => {
        const wrapper = mountPage();
        const link = wrapper.findAll('a').find(a => a.text().includes('Queue Report'));
        expect(link?.attributes('href')).toBe('/tenant/reports');
    });

    it('renders the Browse Documents action linking to /documents', () => {
        const wrapper = mountPage();
        const link = wrapper.findAll('a').find(a => a.attributes('href') === '/documents');
        expect(link?.exists()).toBe(true);
    });

    it('renders the Review Errors action linking to /tenant/errors', () => {
        const wrapper = mountPage();
        const link = wrapper.findAll('a').find(a => a.attributes('href') === '/tenant/errors');
        expect(link?.exists()).toBe(true);
    });
});

describe('Tenant/Index — recent reports', () => {
    afterEach(() => { document.body.innerHTML = ''; });

    it('shows empty state when no reports', () => {
        const wrapper = mountPage({ recentReports: [] });
        const section = wrapper.find('#tour-recent-reports');
        expect(section.text()).toContain('No reports yet');
        expect(section.find('table').exists()).toBe(false);
    });

    it('renders a row per report', () => {
        const wrapper = mountPage({ recentReports: [pendingReport, failedReport] });
        expect(wrapper.find('#tour-recent-reports').findAll('tbody tr')).toHaveLength(2);
    });

    it('shows report type in each row', () => {
        const wrapper = mountPage({ recentReports: [pendingReport] });
        expect(wrapper.find('#tour-recent-reports').text()).toContain('user_activity');
    });

    it('shows format in each row', () => {
        const wrapper = mountPage({ recentReports: [pendingReport] });
        expect(wrapper.find('#tour-recent-reports').text()).toContain('screen');
    });

    it('shows Pending badge for pending reports', () => {
        const wrapper = mountPage({ recentReports: [pendingReport] });
        expect(wrapper.find('#tour-recent-reports').text()).toContain('Pending');
    });

    it('shows Failed badge for failed reports', () => {
        const wrapper = mountPage({ recentReports: [failedReport] });
        expect(wrapper.find('#tour-recent-reports').text()).toContain('Failed');
    });

    it('shows Done badge for successful reports', () => {
        const wrapper = mountPage({ recentReports: [successReport] });
        expect(wrapper.find('#tour-recent-reports').text()).toContain('Done');
    });

    it('shows View all link to /tenant/reports', () => {
        const wrapper = mountPage({ recentReports: [pendingReport] });
        const link = wrapper.find('#tour-recent-reports').findAll('a').find(a => a.text().includes('View all'));
        expect(link?.attributes('href')).toBe('/tenant/reports');
    });
});

describe('Tenant/Index — recent errors', () => {
    afterEach(() => { document.body.innerHTML = ''; });

    it('shows all-clear message when no open errors', () => {
        const wrapper = mountPage({ recentErrors: [] });
        expect(wrapper.find('#tour-recent-errors').text()).toContain('No open errors');
        expect(wrapper.find('#tour-recent-errors').find('table').exists()).toBe(false);
    });

    it('renders a row per error', () => {
        const wrapper = mountPage({ recentErrors: [openError, criticalError] });
        expect(wrapper.find('#tour-recent-errors').findAll('tbody tr')).toHaveLength(2);
    });

    it('shows the error code as a link to the detail page', () => {
        const wrapper = mountPage({ recentErrors: [openError] });
        const link = wrapper.find(`a[href="/tenant/errors/${openError.id}"]`);
        expect(link.exists()).toBe(true);
        expect(link.text()).toBe(openError.error_code);
    });

    it('shows short exception class name', () => {
        const wrapper = mountPage({ recentErrors: [openError] });
        expect(wrapper.find('#tour-recent-errors').text()).toContain('SomeService');
    });

    it('shows the error message', () => {
        const wrapper = mountPage({ recentErrors: [openError] });
        expect(wrapper.find('#tour-recent-errors').text()).toContain('Something went wrong');
    });

    it('shows error severity badge', () => {
        const wrapper = mountPage({ recentErrors: [openError] });
        expect(wrapper.find('#tour-recent-errors').text()).toContain('Error');
    });

    it('shows critical severity badge', () => {
        const wrapper = mountPage({ recentErrors: [criticalError] });
        expect(wrapper.find('#tour-recent-errors').text()).toContain('Critical');
    });

    it('shows critical badge in section header when stats.critical_errors > 0', () => {
        const wrapper = mountPage({
            stats: { ...defaultStats, critical_errors: 2 },
            recentErrors: [criticalError],
        });
        const header = wrapper.find('#tour-recent-errors');
        expect(header.text()).toContain('2 critical');
    });

    it('does not show critical badge in header when no critical errors', () => {
        const wrapper = mountPage({ recentErrors: [openError] });
        expect(wrapper.find('#tour-recent-errors').text()).not.toContain('critical');
    });

    it('shows View all link to /tenant/errors', () => {
        const wrapper = mountPage({ recentErrors: [openError] });
        const link = wrapper.find('#tour-recent-errors').findAll('a').find(a => a.text().includes('View all'));
        expect(link?.attributes('href')).toBe('/tenant/errors');
    });
});

describe('Tenant/Index — recent documents', () => {
    afterEach(() => { document.body.innerHTML = ''; });

    it('shows empty state when no documents', () => {
        const wrapper = mountPage({ recentDocuments: [] });
        const section = wrapper.find('#tour-recent-documents');
        expect(section.text()).toContain('No documents yet');
    });

    it('renders a list item per document', () => {
        const wrapper = mountPage({ recentDocuments: [docPdf, docExcel] });
        expect(wrapper.find('#tour-recent-documents').findAll('li')).toHaveLength(2);
    });

    it('shows document title', () => {
        const wrapper = mountPage({ recentDocuments: [docPdf] });
        expect(wrapper.find('#tour-recent-documents').text()).toContain('Annual Report 2026');
    });

    it('shows formatted file size', () => {
        const wrapper = mountPage({ recentDocuments: [docPdf] });
        // 204800 bytes → 200.0 KB
        expect(wrapper.find('#tour-recent-documents').text()).toContain('200.0 KB');
    });

    it('shows download link for each document', () => {
        const wrapper = mountPage({ recentDocuments: [docPdf] });
        const link = wrapper.find(`a[href="/documents/${docPdf.id}/download"]`);
        expect(link.exists()).toBe(true);
    });

    it('shows View all link to /documents', () => {
        const wrapper = mountPage({ recentDocuments: [docPdf] });
        const link = wrapper.find('#tour-recent-documents').findAll('a').find(a => a.text().includes('View all'));
        expect(link?.attributes('href')).toBe('/documents');
    });
});

describe('Tenant/Index — tour', () => {
    afterEach(() => { document.body.innerHTML = ''; });

    it('renders the tour button', () => {
        const wrapper = mountPage();
        expect(wrapper.find('.tour-btn').exists()).toBe(true);
    });
});
