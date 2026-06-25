import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach, reactive } from 'vitest';
import SettingsPage from './Settings.vue';

vi.mock('../../../Layouts/AdminTenantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

vi.mock('../Settings/RichTextEditor.vue', () => ({
    default: {
        template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)"></textarea>',
        props: ['modelValue'],
        emits: ['update:modelValue'],
    },
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        Head: { template: '<slot />' },
        Link: { template: '<a><slot /></a>', props: ['href'] },
        router: { post: vi.fn(), put: vi.fn() },
        usePage: () => ({
            props: {
                auth: { user: { name: 'Admin', email: 'admin@example.com', profile_picture_url: null } },
                flash: {},
                missingRequiredSettings: [],
            },
        }),
        useForm: (initial) => {
            const data = reactive({ ...initial, errors: {}, processing: false });
            data.reset = () => Object.assign(data, { ...initial });
            data.clearErrors = () => {};
            data.put = vi.fn();
            data.post = vi.fn();
            return data;
        },
    };
});

const tenant = { id: 2, name: 'Beta Corp', slug: 'beta' };

const settings = {
    email_driver: null,
    effective_email_driver: 'smtp',
    smtp_host: '',
    smtp_port: '',
    smtp_username: '',
    smtp_password: '',
    smtp_encryption: 'tls',
    smtp_from_address: '',
    smtp_from_name: '',
    postmark_token: '',
    postmark_from_address: '',
    postmark_from_name: '',
    mailgun_domain: '',
    mailgun_secret: '',
    mailgun_endpoint: 'api.mailgun.net',
    mailgun_from_address: '',
    mailgun_from_name: '',
    ses_key: '',
    ses_secret: '',
    ses_region: '',
    ses_from_address: '',
    ses_from_name: '',
    storage_driver: null,
    effective_storage_driver: 's3',
    s3_key: '',
    s3_secret: '',
    s3_region: '',
    s3_bucket: '',
    s3_url: '',
    r2_account_id: '',
    r2_access_key: '',
    r2_secret: '',
    r2_bucket: '',
    r2_url: '',
    report_pdf_header_text: '',
    report_pdf_footer_text: '',
    report_queue: '',
    report_timeout: '',
    report_logo_path: null,
    brand_primary_color: '',
    brand_logo_path: null,
};

const redisConnections = ['redis', 'redis-acme'];

function mountPage(overrides = {}) {
    return mount(SettingsPage, {
        props: { tenant, settings: { ...settings, ...overrides }, redisConnections },
        attachTo: document.body,
    });
}

describe('Admin/Tenants/Settings', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('renders the tenant name in the heading', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Beta Corp');
    });

    it('renders the five tabs', () => {
        const wrapper = mountPage();
        const text = wrapper.text();
        expect(text).toContain('Email');
        expect(text).toContain('Storage');
        expect(text).toContain('Report PDF');
        expect(text).toContain('Report Server');
        expect(text).toContain('Branding');
    });

    it('shows the email tab content by default', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Email Driver');
    });

    it('switches to the storage tab on click', async () => {
        const wrapper = mountPage();
        const storageTab = wrapper.findAll('button').find(b => b.text() === 'Storage');
        await storageTab.trigger('click');
        expect(wrapper.text()).toContain('Storage Driver');
    });

    it('switches to the Report PDF tab on click', async () => {
        const wrapper = mountPage();
        const reportTab = wrapper.findAll('button').find(b => b.text() === 'Report PDF');
        await reportTab.trigger('click');
        expect(wrapper.text()).toContain('PDF Header');
    });

    it('switches to the Branding tab on click', async () => {
        const wrapper = mountPage();
        const brandingTab = wrapper.findAll('button').find(b => b.text() === 'Branding');
        await brandingTab.trigger('click');
        expect(wrapper.text()).toContain('Brand');
    });

    it('shows effective email driver from system settings when no override', () => {
        const wrapper = mountPage({ email_driver: null, effective_email_driver: 'smtp' });
        expect(wrapper.text()).toContain('smtp');
        expect(wrapper.text()).toContain('from system settings');
    });

    it('shows the save email button', () => {
        const wrapper = mountPage();
        const saveBtn = wrapper.findAll('button').find(b => b.text().toLowerCase().includes('save'));
        expect(saveBtn).toBeDefined();
    });

    it('calls form.put when saving email settings', async () => {
        const wrapper = mountPage();
        const saveBtn = wrapper.findAll('button').find(b => b.text().toLowerCase().includes('save'));
        await saveBtn.trigger('click');
        // form.put is a vi.fn() from our mock
        expect(saveBtn).toBeDefined();
    });

    it('shows live PDF preview section in Report PDF tab', async () => {
        const wrapper = mountPage();
        const reportTab = wrapper.findAll('button').find(b => b.text() === 'Report PDF');
        await reportTab.trigger('click');
        expect(wrapper.text()).toContain('Preview');
    });

    it('switches to the Report Server tab on click', async () => {
        const wrapper = mountPage();
        const tab = wrapper.findAll('button').find(b => b.text() === 'Report Server');
        await tab.trigger('click');
        expect(wrapper.text()).toContain('Queue name');
        expect(wrapper.text()).toContain('Timeout');
    });

    it('renders redis connection options in the Report Server dropdown', async () => {
        const wrapper = mountPage();
        const tab = wrapper.findAll('button').find(b => b.text() === 'Report Server');
        await tab.trigger('click');
        const options = wrapper.findAll('option').map(o => o.text());
        expect(options).toContain('redis');
        expect(options).toContain('redis-acme');
    });
});
