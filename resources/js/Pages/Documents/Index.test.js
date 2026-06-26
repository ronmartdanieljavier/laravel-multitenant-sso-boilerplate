import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import DocumentsIndexPage from './Index.vue';

vi.mock('../../Layouts/TenantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: { delete: vi.fn(), visit: vi.fn() },
    usePage: () => ({
        props: {
            auth: { user: { name: 'Alice', email: 'alice@example.com' } },
            flash: {},
        },
        url: '/documents',
    }),
    useForm: vi.fn((defaults) => ({
        ...defaults,
        errors: {},
        processing: false,
        reset: vi.fn(),
        post: vi.fn((_url, opts) => { opts?.onSuccess?.(); }),
    })),
}));

const defaultConstraints = {
    allowedTypes: ['pdf', 'doc', 'text', 'excel', 'image', 'csv'],
    maxSizes: { pdf: 5, doc: 5, text: 5, excel: 5, image: 5, csv: 5 },
};

const uploadDoc = {
    id: 1,
    title: 'Contract 2026',
    description: null,
    file_name: 'contract.pdf',
    file_size: 204800,
    mime_type: 'application/pdf',
    uploaded_by_name: 'Alice',
    source: 'upload',
    created_at: '2026-06-01T10:00:00Z',
};

const reportDoc = {
    id: 2,
    title: 'Documents Summary Report',
    description: null,
    file_name: 'report_abc.pdf',
    file_size: 512000,
    mime_type: 'application/pdf',
    uploaded_by_name: 'Bob',
    source: 'report',
    created_at: '2026-06-10T12:00:00Z',
};

const subscriptionDoc = {
    id: 3,
    title: 'Weekly Report',
    description: null,
    file_name: 'weekly_report.xlsx',
    file_size: 102400,
    mime_type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    uploaded_by_name: 'System',
    source: 'subscription',
    created_at: '2026-06-15T08:00:00Z',
};

const wordDoc = {
    id: 4,
    title: 'Meeting Notes',
    file_name: 'notes.docx',
    file_size: 30720,
    mime_type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    uploaded_by_name: 'Alice',
    source: 'upload',
    created_at: '2026-06-20T09:00:00Z',
};

const csvDoc = {
    id: 5,
    title: 'Export',
    file_name: 'export.csv',
    file_size: 8192,
    mime_type: 'text/csv',
    uploaded_by_name: 'Alice',
    source: 'upload',
    created_at: '2026-06-20T10:00:00Z',
};

const imageDoc = {
    id: 6,
    title: 'Logo',
    file_name: 'logo.png',
    file_size: 15360,
    mime_type: 'image/png',
    uploaded_by_name: 'Alice',
    source: 'upload',
    created_at: '2026-06-20T11:00:00Z',
};

const textDoc = {
    id: 7,
    title: 'Readme',
    file_name: 'readme.txt',
    file_size: 1024,
    mime_type: 'text/plain',
    uploaded_by_name: 'Alice',
    source: 'upload',
    created_at: '2026-06-20T12:00:00Z',
};

function buildDocuments(data = [], total = null) {
    return {
        data,
        total: total ?? data.length,
        per_page: 20,
        current_page: 1,
        last_page: 1,
        prev_page_url: null,
        next_page_url: null,
    };
}

function mountPage(documents = buildDocuments(), constraints = defaultConstraints) {
    return mount(DocumentsIndexPage, {
        props: {
            documents,
            tenant: { id: 1, name: 'Acme Corp', slug: 'acme' },
            uploadConstraints: constraints,
        },
        global: {
            stubs: { teleport: true },
            mocks: {
                $page: { props: { flash: {} } },
            },
        },
    });
}

afterEach(() => { vi.clearAllMocks(); });

describe('Documents/Index', () => {
    it('renders the page header', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Documents');
    });

    it('shows empty state when no documents exist', () => {
        const wrapper = mountPage(buildDocuments([]));
        expect(wrapper.text()).toContain('No documents yet');
    });

    it('renders a row for each document', () => {
        const wrapper = mountPage(buildDocuments([uploadDoc, reportDoc]));
        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
    });

    it('shows "User Upload" badge for upload source', () => {
        const wrapper = mountPage(buildDocuments([uploadDoc]));
        expect(wrapper.text()).toContain('User Upload');
    });

    it('shows "Report" badge for report source', () => {
        const wrapper = mountPage(buildDocuments([reportDoc]));
        expect(wrapper.text()).toContain('Report');
    });

    it('shows "Subscription" badge for subscription source', () => {
        const wrapper = mountPage(buildDocuments([subscriptionDoc]));
        expect(wrapper.text()).toContain('Subscription');
    });

    it('applies correct css class for report source badge', () => {
        const wrapper = mountPage(buildDocuments([reportDoc]));
        const badge = wrapper.find('span.bg-blue-500\\/15');
        expect(badge.exists()).toBe(true);
        expect(badge.text()).toBe('Report');
    });

    it('applies correct css class for subscription source badge', () => {
        const wrapper = mountPage(buildDocuments([subscriptionDoc]));
        const badge = wrapper.find('span.bg-purple-500\\/15');
        expect(badge.exists()).toBe(true);
        expect(badge.text()).toBe('Subscription');
    });

    it('applies correct css class for upload source badge', () => {
        const wrapper = mountPage(buildDocuments([uploadDoc]));
        const badge = wrapper.find('span.bg-slate-700');
        expect(badge.exists()).toBe(true);
        expect(badge.text()).toBe('User Upload');
    });

    it('renders download link with correct href', () => {
        const wrapper = mountPage(buildDocuments([uploadDoc]));
        const link = wrapper.find(`a[href="/documents/${uploadDoc.id}/download"]`);
        expect(link.exists()).toBe(true);
    });

    it('renders download link for report document', () => {
        const wrapper = mountPage(buildDocuments([reportDoc]));
        const link = wrapper.find(`a[href="/documents/${reportDoc.id}/download"]`);
        expect(link.exists()).toBe(true);
    });

    it('calls router.delete when delete is confirmed', async () => {
        const { router } = await import('@inertiajs/vue3');
        vi.spyOn(window, 'confirm').mockReturnValue(true);

        const wrapper = mountPage(buildDocuments([uploadDoc]));
        await wrapper.find('button[class*="red"]').trigger('click');

        expect(router.delete).toHaveBeenCalledWith(`/documents/${uploadDoc.id}`, expect.any(Object));
    });

    it('does not call router.delete when delete is cancelled', async () => {
        const { router } = await import('@inertiajs/vue3');
        vi.spyOn(window, 'confirm').mockReturnValue(false);

        const wrapper = mountPage(buildDocuments([uploadDoc]));
        await wrapper.find('button[class*="red"]').trigger('click');

        expect(router.delete).not.toHaveBeenCalled();
    });

    it('shows document file name in table', () => {
        const wrapper = mountPage(buildDocuments([uploadDoc]));
        expect(wrapper.text()).toContain('contract.pdf');
    });

    it('shows document title in table', () => {
        const wrapper = mountPage(buildDocuments([uploadDoc]));
        expect(wrapper.text()).toContain('Contract 2026');
    });

    it('shows formatted file size', () => {
        const wrapper = mountPage(buildDocuments([uploadDoc]));
        expect(wrapper.text()).toContain('200.0 KB');
    });

    it('shows total document count', () => {
        const wrapper = mountPage(buildDocuments([uploadDoc, reportDoc], 2));
        expect(wrapper.text()).toContain('2 files');
    });

    it('shows pagination controls when multiple pages exist', () => {
        const docs = buildDocuments([uploadDoc]);
        docs.last_page = 3;
        docs.total = 60;
        const wrapper = mountPage(docs);
        expect(wrapper.text()).toContain('Previous');
        expect(wrapper.text()).toContain('Next');
    });

    it('does not show pagination when only one page', () => {
        const wrapper = mountPage(buildDocuments([uploadDoc]));
        expect(wrapper.text()).not.toContain('Previous');
    });

    it('shows all three source types simultaneously', () => {
        const wrapper = mountPage(buildDocuments([uploadDoc, reportDoc, subscriptionDoc]));
        expect(wrapper.text()).toContain('User Upload');
        expect(wrapper.text()).toContain('Report');
        expect(wrapper.text()).toContain('Subscription');
    });

    describe('Upload modal', () => {
        it('does not show the modal by default', () => {
            const wrapper = mountPage();
            expect(wrapper.find('form').exists()).toBe(false);
        });

        it('opens the modal when Upload Document button is clicked', async () => {
            const wrapper = mountPage();
            await wrapper.find('button.bg-emerald-600').trigger('click');
            expect(wrapper.find('form').exists()).toBe(true);
        });

        it('closes the modal when Cancel is clicked', async () => {
            const wrapper = mountPage();
            await wrapper.find('button.bg-emerald-600').trigger('click');
            expect(wrapper.find('form').exists()).toBe(true);
            const cancelBtn = wrapper.findAll('button').find(b => b.text() === 'Cancel');
            await cancelBtn.trigger('click');
            expect(wrapper.find('form').exists()).toBe(false);
        });

        it('closes the modal after successful upload', async () => {
            const wrapper = mountPage();
            await wrapper.find('button.bg-emerald-600').trigger('click');
            expect(wrapper.find('form').exists()).toBe(true);
            await wrapper.find('form').trigger('submit');
            expect(wrapper.find('form').exists()).toBe(false);
        });
    });

    describe('Checkbox selection', () => {
        it('renders a checkbox for each document row', () => {
            const wrapper = mountPage(buildDocuments([uploadDoc, reportDoc]));
            const checkboxes = wrapper.findAll('tbody input[type="checkbox"]');
            expect(checkboxes).toHaveLength(2);
        });

        it('renders a select-all checkbox in the table header', () => {
            const wrapper = mountPage(buildDocuments([uploadDoc]));
            const headerCheckbox = wrapper.find('thead input[type="checkbox"]');
            expect(headerCheckbox.exists()).toBe(true);
        });

        it('does not show "Download Selected" button when nothing is selected', () => {
            const wrapper = mountPage(buildDocuments([uploadDoc]));
            expect(wrapper.text()).not.toContain('Download Selected');
        });

        it('shows "Download Selected (1)" after checking one document', async () => {
            const wrapper = mountPage(buildDocuments([uploadDoc]));
            await wrapper.find('tbody input[type="checkbox"]').setValue(true);
            expect(wrapper.text()).toContain('Download Selected (1)');
        });

        it('shows correct count when multiple documents are selected', async () => {
            const wrapper = mountPage(buildDocuments([uploadDoc, reportDoc]));
            const checkboxes = wrapper.findAll('tbody input[type="checkbox"]');
            await checkboxes[0].setValue(true);
            await checkboxes[1].setValue(true);
            expect(wrapper.text()).toContain('Download Selected (2)');
        });

        it('selects all rows when select-all checkbox is clicked', async () => {
            const wrapper = mountPage(buildDocuments([uploadDoc, reportDoc]));
            await wrapper.find('thead input[type="checkbox"]').setValue(true);
            expect(wrapper.text()).toContain('Download Selected (2)');
        });

        it('deselects all rows when select-all is unchecked', async () => {
            const wrapper = mountPage(buildDocuments([uploadDoc, reportDoc]));
            await wrapper.find('thead input[type="checkbox"]').setValue(true);
            expect(wrapper.text()).toContain('Download Selected (2)');
            await wrapper.find('thead input[type="checkbox"]').setValue(false);
            expect(wrapper.text()).not.toContain('Download Selected');
        });

        it('hides "Download Selected" button after deselecting', async () => {
            const wrapper = mountPage(buildDocuments([uploadDoc]));
            const checkbox = wrapper.find('tbody input[type="checkbox"]');
            await checkbox.setValue(true);
            expect(wrapper.text()).toContain('Download Selected (1)');
            await checkbox.setValue(false);
            expect(wrapper.text()).not.toContain('Download Selected');
        });

        it('submits a form POST to /documents/download-zip when Download Selected is clicked', async () => {
            const submitSpy = vi.fn();
            const originalCreateElement = document.createElement.bind(document);
            const createElementSpy = vi.spyOn(document, 'createElement').mockImplementation((tag) => {
                const el = originalCreateElement(tag);
                if (tag === 'form') { el.submit = submitSpy; }
                return el;
            });

            const wrapper = mountPage(buildDocuments([uploadDoc]));
            await wrapper.find('tbody input[type="checkbox"]').setValue(true);
            await wrapper.find('button.bg-slate-700').trigger('click');

            expect(submitSpy).toHaveBeenCalled();
            createElementSpy.mockRestore();
        });
    });

    describe('File type icons', () => {
        it('applies red icon color for PDF files', () => {
            const wrapper = mountPage(buildDocuments([uploadDoc]));
            const icon = wrapper.find('svg.text-red-400');
            expect(icon.exists()).toBe(true);
        });

        it('applies blue icon color for Word documents', () => {
            const wrapper = mountPage(buildDocuments([wordDoc]));
            const icon = wrapper.find('svg.text-blue-400');
            expect(icon.exists()).toBe(true);
        });

        it('applies emerald icon color for Excel spreadsheets', () => {
            const wrapper = mountPage(buildDocuments([subscriptionDoc]));
            const icon = wrapper.find('svg.text-emerald-400');
            expect(icon.exists()).toBe(true);
        });

        it('applies amber icon color for CSV files', () => {
            const wrapper = mountPage(buildDocuments([csvDoc]));
            const icon = wrapper.find('svg.text-amber-400');
            expect(icon.exists()).toBe(true);
        });

        it('applies purple icon color for image files', () => {
            const wrapper = mountPage(buildDocuments([imageDoc]));
            const icon = wrapper.find('svg.text-purple-400');
            expect(icon.exists()).toBe(true);
        });

        it('applies light slate icon color for text files', () => {
            const wrapper = mountPage(buildDocuments([textDoc]));
            const icon = wrapper.find('svg.text-slate-300');
            expect(icon.exists()).toBe(true);
        });

        it('applies correct background for PDF icon', () => {
            const wrapper = mountPage(buildDocuments([uploadDoc]));
            const bg = wrapper.find('div.bg-red-500\\/10');
            expect(bg.exists()).toBe(true);
        });

        it('applies correct background for image icon', () => {
            const wrapper = mountPage(buildDocuments([imageDoc]));
            const bg = wrapper.find('div.bg-purple-500\\/10');
            expect(bg.exists()).toBe(true);
        });

        it('shows distinct icons for multiple file types in the same table', () => {
            const wrapper = mountPage(buildDocuments([uploadDoc, wordDoc, imageDoc, csvDoc]));
            expect(wrapper.find('svg.text-red-400').exists()).toBe(true);
            expect(wrapper.find('svg.text-blue-400').exists()).toBe(true);
            expect(wrapper.find('svg.text-purple-400').exists()).toBe(true);
            expect(wrapper.find('svg.text-amber-400').exists()).toBe(true);
        });
    });
});
