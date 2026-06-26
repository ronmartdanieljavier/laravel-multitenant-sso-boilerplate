<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import RichTextEditor from '../Settings/RichTextEditor.vue';
import AdminTenantLayout from '../../../Layouts/AdminTenantLayout.vue';
import TourButton from '../../Partials/TourButton.vue';
import { useTour } from '../../../composables/useTour';

defineOptions({ layout: AdminTenantLayout });

const page = usePage();

const props = defineProps({
    tenant: Object,
    settings: Object,
    redisConnections: Array,
});

const s = props.settings;

const activeTab = ref('email');

const { startTour } = useTour('admin-tenant-settings', [
    {
        element: '#tour-settings-tabs',
        title: 'Settings Tabs',
        description: 'Tenant settings are grouped into tabs: Email, Storage, Report PDF, Report Server, and Branding. Click a tab to configure that section.',
    },
    {
        element: '#tour-settings-content',
        title: 'Override Global Settings',
        description: 'Each setting here overrides the global default configured in Admin → Settings. Leave a field blank to inherit the system-wide default.',
        side: 'top',
    },
]);

const tabs = [
    { key: 'email', label: 'Email' },
    { key: 'storage', label: 'Storage' },
    { key: 'report', label: 'Report PDF' },
    { key: 'report_server', label: 'Report Server' },
    { key: 'branding', label: 'Branding' },
];

// ── Email ─────────────────────────────────────────────────────────────────────

const emailForm = useForm({
    email_driver: s.email_driver ?? null,
    smtp_host: s.smtp_host ?? '',
    smtp_port: s.smtp_port ?? '',
    smtp_username: s.smtp_username ?? '',
    smtp_password: s.smtp_password ?? '',
    smtp_encryption: s.smtp_encryption ?? 'tls',
    smtp_from_address: s.smtp_from_address ?? '',
    smtp_from_name: s.smtp_from_name ?? '',
    postmark_token: s.postmark_token ?? '',
    postmark_from_address: s.postmark_from_address ?? '',
    postmark_from_name: s.postmark_from_name ?? '',
    mailgun_domain: s.mailgun_domain ?? '',
    mailgun_secret: s.mailgun_secret ?? '',
    mailgun_endpoint: s.mailgun_endpoint ?? 'api.mailgun.net',
    mailgun_from_address: s.mailgun_from_address ?? '',
    mailgun_from_name: s.mailgun_from_name ?? '',
    ses_key: s.ses_key ?? '',
    ses_secret: s.ses_secret ?? '',
    ses_region: s.ses_region ?? '',
    ses_from_address: s.ses_from_address ?? '',
    ses_from_name: s.ses_from_name ?? '',
});

function saveEmail() {
    emailForm.put(`/admin/tenants/${props.tenant.id}/settings`);
}

// ── Storage ───────────────────────────────────────────────────────────────────

const FILE_TYPE_GROUPS = [
    { key: 'pdf',   label: 'PDF' },
    { key: 'doc',   label: 'Word (DOC/DOCX)' },
    { key: 'text',  label: 'Plain Text' },
    { key: 'excel', label: 'Excel (XLS/XLSX)' },
    { key: 'image', label: 'Images (JPG/PNG/GIF/WebP)' },
    { key: 'csv',   label: 'CSV' },
];

const ALL_TYPE_KEYS = FILE_TYPE_GROUPS.map(g => g.key);

function parseAllowedTypes(raw) {
    if (!raw) { return null; }
    return new Set(raw.split(',').map(x => x.trim()).filter(Boolean));
}

const tenantAllowedTypeSet = ref(parseAllowedTypes(s.upload_allowed_types));

function toggleTenantType(key) {
    if (tenantAllowedTypeSet.value === null) {
        tenantAllowedTypeSet.value = new Set(ALL_TYPE_KEYS);
    }
    if (tenantAllowedTypeSet.value.has(key)) {
        tenantAllowedTypeSet.value.delete(key);
    } else {
        tenantAllowedTypeSet.value.add(key);
    }
}

const storageForm = useForm({
    storage_driver: s.storage_driver ?? null,
    s3_key: s.s3_key ?? '',
    s3_secret: s.s3_secret ?? '',
    s3_region: s.s3_region ?? '',
    s3_bucket: s.s3_bucket ?? '',
    s3_url: s.s3_url ?? '',
    r2_account_id: s.r2_account_id ?? '',
    r2_access_key: s.r2_access_key ?? '',
    r2_secret: s.r2_secret ?? '',
    r2_bucket: s.r2_bucket ?? '',
    r2_url: s.r2_url ?? '',
    upload_allowed_types: s.upload_allowed_types ?? null,
    upload_max_size_pdf: s.upload_max_size_pdf ?? '',
    upload_max_size_doc: s.upload_max_size_doc ?? '',
    upload_max_size_text: s.upload_max_size_text ?? '',
    upload_max_size_excel: s.upload_max_size_excel ?? '',
    upload_max_size_image: s.upload_max_size_image ?? '',
    upload_max_size_csv: s.upload_max_size_csv ?? '',
});

watch(tenantAllowedTypeSet, (set) => {
    storageForm.upload_allowed_types = set ? [...set].join(',') : null;
}, { deep: true });

function saveStorage() {
    storageForm.upload_allowed_types = tenantAllowedTypeSet.value ? [...tenantAllowedTypeSet.value].join(',') : null;
    storageForm.put(`/admin/tenants/${props.tenant.id}/settings`);
}

// ── Report PDF ────────────────────────────────────────────────────────────────

const reportForm = useForm({
    report_pdf_header_text: s.report_pdf_header_text ?? '',
    report_pdf_footer_text: s.report_pdf_footer_text ?? '',
});

function saveReport() {
    reportForm.put(`/admin/tenants/${props.tenant.id}/settings`);
}

// ── Report Server ─────────────────────────────────────────────────────────────

const reportServerForm = useForm({
    report_queue: s.report_queue ?? '',
    report_timeout: s.report_timeout ?? '',
    report_connection: s.report_connection ?? '',
});

function saveReportServer() {
    reportServerForm.put(`/admin/tenants/${props.tenant.id}/settings`);
}

// Logo upload
const logoPreviewUrl = ref(
    s.report_logo_path ? `/storage/${s.report_logo_path}` : null
);

const logoFile = ref(null);

function onLogoChange(e) {
    const file = e.target.files[0];
    if (!file) { return; }
    logoFile.value = file;
    logoPreviewUrl.value = URL.createObjectURL(file);
}

const logoUploadForm = useForm({ logo: null });

function uploadLogo() {
    logoUploadForm.logo = logoFile.value;
    logoUploadForm.post(`/admin/tenants/${props.tenant.id}/settings/logo`, {
        forceFormData: true,
    });
}

function removeLogo() {
    router.delete(`/admin/tenants/${props.tenant.id}/settings/logo`);
    logoPreviewUrl.value = null;
    logoFile.value = null;
}

// Live PDF preview — values are HTML from the rich text editor
const pdfHeader = computed(() => reportForm.report_pdf_header_text || '<span style="color:#9ca3af">(no header)</span>');
const pdfFooter = computed(() => reportForm.report_pdf_footer_text || '<span style="color:#9ca3af">(no footer)</span>');

// ── Branding ──────────────────────────────────────────────────────────────────

const brandingForm = useForm({
    app_name: s.app_name ?? '',
    support_email: s.support_email ?? '',
    support_url: s.support_url ?? '',
});

function saveBranding() {
    brandingForm.put(`/admin/tenants/${props.tenant.id}/settings`);
}

// ── Flash ─────────────────────────────────────────────────────────────────────

const flash = computed(() => page.props.flash ?? {});
</script>

<template>
    <Head :title="`${tenant.name} — Settings`" />

    <main class="p-8">
            <!-- Flash -->
            <div v-if="flash.success" class="mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg px-4 py-3 text-sm">
                {{ flash.success }}
            </div>

            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
                <Link href="/admin/tenants" class="hover:text-slate-300 transition">Tenants</Link>
                <span>/</span>
                <span class="text-slate-300">{{ tenant.name }}</span>
                <span>/</span>
                <span class="text-slate-400">Settings</span>
            </div>

            <!-- Page header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-white">{{ tenant.name }} — Settings</h1>
                    <p class="text-sm text-slate-400 mt-1">Tenant-specific overrides. Unset fields fall back to system settings.</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="`/admin/tenants/${tenant.id}/users`"
                          class="px-4 py-2 text-sm rounded-lg border border-white/10 text-slate-300 hover:bg-white/5 transition">
                        View Users
                    </Link>
                </div>
            </div>

            <!-- Tabs -->
            <div id="tour-settings-tabs" class="flex gap-1 mb-6 border-b border-white/5">
                <button v-for="tab in tabs" :key="tab.key"
                        @click="activeTab = tab.key"
                        :class="activeTab === tab.key
                            ? 'border-b-2 border-blue-500 text-blue-300'
                            : 'text-slate-400 hover:text-slate-200'"
                        class="px-4 py-2 text-sm font-medium -mb-px transition">
                    {{ tab.label }}
                </button>
            </div>

            <!-- Email tab -->
            <div id="tour-settings-content" v-show="activeTab === 'email'" class="max-w-2xl">
                <p v-if="settings.effective_email_driver" class="text-xs text-slate-500 mb-4">
                    Effective driver: <span class="text-slate-300 font-mono">{{ settings.effective_email_driver }}</span>
                    <span v-if="!settings.email_driver" class="ml-2 text-slate-600">(from system settings)</span>
                </p>
                <form @submit.prevent="saveEmail" class="space-y-6">
                    <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-2">Email Driver Override</label>
                            <div class="flex flex-wrap gap-3">
                                <label v-for="d in [null, 'smtp', 'postmark', 'mailgun', 'ses']" :key="String(d)"
                                       class="flex items-center gap-2 cursor-pointer group">
                                    <input type="radio" v-model="emailForm.email_driver" :value="d" class="accent-blue-500" />
                                    <span class="text-sm text-slate-300 group-hover:text-white transition">{{ d ?? 'Use system default' }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- SMTP -->
                        <template v-if="emailForm.email_driver === 'smtp'">
                            <div class="border-t border-white/5 pt-4 grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Host</label>
                                    <input v-model="emailForm.smtp_host" type="text" placeholder="smtp.example.com" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Port</label>
                                    <input v-model="emailForm.smtp_port" type="number" placeholder="587" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Username</label>
                                    <input v-model="emailForm.smtp_username" type="text" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Password</label>
                                    <input v-model="emailForm.smtp_password" type="password" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Encryption</label>
                                    <select v-model="emailForm.smtp_encryption" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                        <option value="starttls">STARTTLS</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">From Address</label>
                                    <input v-model="emailForm.smtp_from_address" type="email" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-slate-400 mb-1">From Name</label>
                                    <input v-model="emailForm.smtp_from_name" type="text" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                                </div>
                            </div>
                        </template>

                        <!-- Postmark -->
                        <template v-if="emailForm.email_driver === 'postmark'">
                            <div class="border-t border-white/5 pt-4 space-y-4">
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Server Token</label>
                                    <input v-model="emailForm.postmark_token" type="password" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">From Address</label>
                                        <input v-model="emailForm.postmark_from_address" type="email" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">From Name</label>
                                        <input v-model="emailForm.postmark_from_name" type="text" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Mailgun -->
                        <template v-if="emailForm.email_driver === 'mailgun'">
                            <div class="border-t border-white/5 pt-4 space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Domain</label>
                                        <input v-model="emailForm.mailgun_domain" type="text" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Secret</label>
                                        <input v-model="emailForm.mailgun_secret" type="password" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Endpoint</label>
                                        <select v-model="emailForm.mailgun_endpoint" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                                            <option value="api.mailgun.net">US (api.mailgun.net)</option>
                                            <option value="api.eu.mailgun.net">EU (api.eu.mailgun.net)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">From Address</label>
                                        <input v-model="emailForm.mailgun_from_address" type="email" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">From Name</label>
                                        <input v-model="emailForm.mailgun_from_name" type="text" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- SES -->
                        <template v-if="emailForm.email_driver === 'ses'">
                            <div class="border-t border-white/5 pt-4 space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Access Key</label>
                                        <input v-model="emailForm.ses_key" type="text" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Secret</label>
                                        <input v-model="emailForm.ses_secret" type="password" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Region</label>
                                        <input v-model="emailForm.ses_region" type="text" placeholder="us-east-1" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">From Address</label>
                                        <input v-model="emailForm.ses_from_address" type="email" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">From Name</label>
                                        <input v-model="emailForm.ses_from_name" type="text" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" :disabled="emailForm.processing"
                                class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg transition">
                            {{ emailForm.processing ? 'Saving…' : 'Save Email Settings' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Storage tab -->
            <div v-show="activeTab === 'storage'" class="max-w-2xl">
                <p v-if="settings.effective_storage_driver" class="text-xs text-slate-500 mb-4">
                    Effective driver: <span class="text-slate-300 font-mono">{{ settings.effective_storage_driver }}</span>
                    <span v-if="!settings.storage_driver" class="ml-2 text-slate-600">(from system settings)</span>
                </p>
                <form @submit.prevent="saveStorage" class="space-y-6">
                    <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-2">Storage Driver Override</label>
                            <div class="flex flex-wrap gap-3">
                                <label v-for="d in [null, 's3', 'r2']" :key="String(d)" class="flex items-center gap-2 cursor-pointer group">
                                    <input type="radio" v-model="storageForm.storage_driver" :value="d" class="accent-blue-500" />
                                    <span class="text-sm text-slate-300 group-hover:text-white transition">{{ d ?? 'Use system default' }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- S3 -->
                        <template v-if="storageForm.storage_driver === 's3'">
                            <div class="border-t border-white/5 pt-4 grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Access Key</label>
                                    <input v-model="storageForm.s3_key" type="text" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Secret</label>
                                    <input v-model="storageForm.s3_secret" type="password" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Region</label>
                                    <input v-model="storageForm.s3_region" type="text" placeholder="us-east-1" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Bucket</label>
                                    <input v-model="storageForm.s3_bucket" type="text" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-slate-400 mb-1">URL <span class="text-slate-600">optional</span></label>
                                    <input v-model="storageForm.s3_url" type="url" placeholder="https://cdn.example.com" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                                </div>
                            </div>
                        </template>

                        <!-- R2 -->
                        <template v-if="storageForm.storage_driver === 'r2'">
                            <div class="border-t border-white/5 pt-4 grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-xs text-slate-400 mb-1">Account ID</label>
                                    <input v-model="storageForm.r2_account_id" type="text" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Access Key</label>
                                    <input v-model="storageForm.r2_access_key" type="text" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Secret</label>
                                    <input v-model="storageForm.r2_secret" type="password" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Bucket</label>
                                    <input v-model="storageForm.r2_bucket" type="text" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Public URL <span class="text-slate-600">optional</span></label>
                                    <input v-model="storageForm.r2_url" type="url" class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Upload Settings -->
                    <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-5">
                        <div>
                            <h3 class="font-semibold text-white">Document Upload Settings</h3>
                            <p class="text-xs text-slate-400 mt-1">Override the system-wide upload settings for this tenant. Leave the override toggle off to inherit from system defaults.</p>
                        </div>

                        <div class="flex items-center gap-3 mb-2">
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox"
                                       :checked="tenantAllowedTypeSet !== null"
                                       @change="tenantAllowedTypeSet = tenantAllowedTypeSet === null ? new Set(ALL_TYPE_KEYS) : null"
                                       class="accent-blue-500 w-4 h-4" />
                                <span class="text-sm text-slate-300">Override allowed file types for this tenant</span>
                            </label>
                        </div>

                        <div v-if="tenantAllowedTypeSet !== null" class="space-y-3 pl-6 border-l border-white/5">
                            <div v-for="group in FILE_TYPE_GROUPS" :key="group.key"
                                 class="flex items-center gap-4 py-2 border-b border-white/5 last:border-0">
                                <label class="flex items-center gap-2.5 cursor-pointer w-52 shrink-0">
                                    <input type="checkbox"
                                           :checked="tenantAllowedTypeSet.has(group.key)"
                                           @change="toggleTenantType(group.key)"
                                           class="accent-blue-500 w-4 h-4" />
                                    <span class="text-sm text-slate-300">{{ group.label }}</span>
                                </label>
                                <div class="flex items-center gap-2" :class="{ 'opacity-40 pointer-events-none': !tenantAllowedTypeSet.has(group.key) }">
                                    <input v-model="storageForm[`upload_max_size_${group.key}`]"
                                           type="number" min="1" max="100"
                                           :placeholder="s[`effective_upload_max_size_${group.key}`] ?? '5'"
                                           class="w-20 bg-slate-800 border border-white/10 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-blue-500" />
                                    <span class="text-sm text-slate-400">MB max</span>
                                    <span class="text-xs text-slate-600">blank = system default</span>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-xs text-slate-500 pl-6">Using system-wide upload settings.</p>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" :disabled="storageForm.processing"
                                class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg transition">
                            {{ storageForm.processing ? 'Saving…' : 'Save Storage Settings' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Report PDF tab -->
            <div v-show="activeTab === 'report'" class="max-w-4xl">
                <div class="grid grid-cols-2 gap-8">
                    <!-- Left: form -->
                    <div class="space-y-6">
                        <!-- Logo upload -->
                        <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-4">
                            <h3 class="text-sm font-medium text-white">Report Logo</h3>

                            <div v-if="logoPreviewUrl" class="flex items-start gap-4">
                                <img :src="logoPreviewUrl" alt="Logo" class="h-16 object-contain rounded bg-white/5 p-2" />
                                <button @click="removeLogo" type="button"
                                        class="text-xs text-red-400 hover:text-red-300 transition mt-1">
                                    Remove
                                </button>
                            </div>

                            <div>
                                <input id="logo-upload" type="file" accept="image/*" class="hidden" @change="onLogoChange" />
                                <label for="logo-upload" class="inline-flex items-center gap-2 px-4 py-2 text-sm rounded-lg border border-white/10 text-slate-300 hover:bg-white/5 cursor-pointer transition">
                                    {{ logoPreviewUrl ? 'Replace logo' : 'Upload logo' }}
                                </label>
                                <p class="text-xs text-slate-600 mt-1">PNG, JPG, SVG, WebP · max 2 MB</p>
                            </div>

                            <button v-if="logoFile" @click="uploadLogo" type="button"
                                    :disabled="logoUploadForm.processing"
                                    class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                                {{ logoUploadForm.processing ? 'Uploading…' : 'Save Logo' }}
                            </button>
                        </div>

                        <!-- Header / Footer text -->
                        <form @submit.prevent="saveReport" class="space-y-6">
                            <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-4">
                                <h3 class="text-sm font-medium text-white">PDF Header & Footer</h3>

                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Header text</label>
                                    <RichTextEditor v-model="reportForm.report_pdf_header_text" />
                                    <p v-if="reportForm.errors.report_pdf_header_text" class="text-xs text-red-400 mt-1">{{ reportForm.errors.report_pdf_header_text }}</p>
                                </div>

                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Footer text</label>
                                    <RichTextEditor v-model="reportForm.report_pdf_footer_text" />
                                    <p v-if="reportForm.errors.report_pdf_footer_text" class="text-xs text-red-400 mt-1">{{ reportForm.errors.report_pdf_footer_text }}</p>
                                </div>

                            </div>

                            <div class="flex justify-end">
                                <button type="submit" :disabled="reportForm.processing"
                                        class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg transition">
                                    {{ reportForm.processing ? 'Saving…' : 'Save Report Settings' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Right: live PDF preview -->
                    <div class="sticky top-8">
                        <p class="text-xs text-slate-500 mb-3 uppercase tracking-wide">Live Preview</p>
                        <div class="bg-white rounded-xl shadow-2xl overflow-hidden" style="aspect-ratio: 210/297; max-height: 560px;">
                            <!-- Header -->
                            <div class="flex items-center justify-between px-6 py-3 border-b border-gray-200">
                                <img v-if="logoPreviewUrl" :src="logoPreviewUrl" alt="Logo" class="h-8 object-contain" />
                                <div v-else class="h-8 w-20 bg-gray-100 rounded" />
                                <span class="text-xs text-gray-500 font-medium truncate max-w-48 [&_*]:inline" v-html="pdfHeader"></span>
                            </div>
                            <!-- Body placeholder -->
                            <div class="px-6 py-4 space-y-2 flex-1">
                                <div class="h-3 bg-gray-100 rounded w-3/4" />
                                <div class="h-3 bg-gray-100 rounded w-full" />
                                <div class="h-3 bg-gray-100 rounded w-5/6" />
                                <div class="h-3 bg-gray-100 rounded w-2/3 mt-4" />
                                <div class="h-3 bg-gray-100 rounded w-full" />
                                <div class="h-24 bg-gray-50 border border-gray-100 rounded mt-4" />
                                <div class="h-3 bg-gray-100 rounded w-full mt-4" />
                                <div class="h-3 bg-gray-100 rounded w-4/5" />
                            </div>
                            <!-- Footer -->
                            <div class="px-6 py-3 border-t border-gray-200 mt-auto">
                                <span class="text-xs text-gray-400 [&_*]:inline" v-html="pdfFooter"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Server tab -->
            <div v-show="activeTab === 'report_server'" class="max-w-2xl">
                <form @submit.prevent="saveReportServer" class="space-y-6">
                    <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-4">
                        <h3 class="text-sm font-medium text-white">Report Server Overrides</h3>
                        <p class="text-xs text-slate-500">Leave blank to use the system defaults. Changes apply to all report jobs dispatched for this tenant.</p>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Queue name</label>
                            <input v-model="reportServerForm.report_queue" type="text"
                                   placeholder="reports"
                                   class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                            <p class="text-xs text-slate-600 mt-1">The Horizon queue that report generation jobs will be pushed onto.</p>
                            <p v-if="reportServerForm.errors.report_queue" class="text-xs text-red-400 mt-1">{{ reportServerForm.errors.report_queue }}</p>
                        </div>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Timeout (seconds)</label>
                            <input v-model="reportServerForm.report_timeout" type="number" min="10" max="3600"
                                   placeholder="300"
                                   class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                            <p class="text-xs text-slate-600 mt-1">Max seconds a report job may run before it is killed (10–3600).</p>
                            <p v-if="reportServerForm.errors.report_timeout" class="text-xs text-red-400 mt-1">{{ reportServerForm.errors.report_timeout }}</p>
                        </div>

                        <div class="border-t border-white/5 pt-4">
                            <h4 class="text-xs font-medium text-slate-400 mb-3">Redis Connection</h4>
                            <p class="text-xs text-slate-500 mb-3">Point this tenant's report jobs at a dedicated Redis instance. The connection name must match a key defined in <code class="text-slate-400">config/queue.php</code> connections.</p>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Connection name</label>
                                <select v-model="reportServerForm.report_connection"
                                        class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                                    <option value="">— use default —</option>
                                    <option v-for="conn in redisConnections" :key="conn" :value="conn">{{ conn }}</option>
                                </select>
                                <p class="text-xs text-slate-600 mt-1">Redis connections are defined in <code class="text-slate-400">config/queue.php</code>.</p>
                                <p v-if="reportServerForm.errors.report_connection" class="text-xs text-red-400 mt-1">{{ reportServerForm.errors.report_connection }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" :disabled="reportServerForm.processing"
                                class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg transition">
                            {{ reportServerForm.processing ? 'Saving…' : 'Save Report Server Settings' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Branding tab -->
            <div v-show="activeTab === 'branding'" class="max-w-2xl">
                <form @submit.prevent="saveBranding" class="space-y-6">
                    <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-4">
                        <p class="text-xs text-slate-500">These override the system-wide branding for this tenant's context.</p>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">App Name</label>
                            <input v-model="brandingForm.app_name" type="text" placeholder="My Tenant App"
                                   class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                            <p v-if="brandingForm.errors.app_name" class="text-xs text-red-400 mt-1">{{ brandingForm.errors.app_name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Support Email</label>
                                <input v-model="brandingForm.support_email" type="email" placeholder="support@tenant.com"
                                       class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                                <p v-if="brandingForm.errors.support_email" class="text-xs text-red-400 mt-1">{{ brandingForm.errors.support_email }}</p>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Support URL</label>
                                <input v-model="brandingForm.support_url" type="url" placeholder="https://help.tenant.com"
                                       class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500" />
                                <p v-if="brandingForm.errors.support_url" class="text-xs text-red-400 mt-1">{{ brandingForm.errors.support_url }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" :disabled="brandingForm.processing"
                                class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg transition">
                            {{ brandingForm.processing ? 'Saving…' : 'Save Branding' }}
                        </button>
                    </div>
                </form>
            </div>
    </main>

    <TourButton @click="startTour" />
</template>
