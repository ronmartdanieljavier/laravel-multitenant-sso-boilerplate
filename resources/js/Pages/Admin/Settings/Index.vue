<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useIdleTimeout } from '../../../composables/useIdleTimeout';
import TourButton from '../../Partials/TourButton.vue';
import { useTour } from '../../../composables/useTour';
import RichTextEditor from './RichTextEditor.vue';

const page = usePage();
useIdleTimeout(page.props.idleTimeoutMinutes);

const props = defineProps({
    settings: Object,
});

const activeTab = ref('email');

const { startTour } = useTour('admin-settings', [
    {
        element: '#tour-settings-tabs',
        title: 'System Settings',
        description: 'Configure global defaults for Email, SMS, Push notifications, Storage, Security, Branding, and Authentication. These apply to all tenants unless overridden at the tenant level.',
    },
    {
        element: '#tour-settings-panel',
        title: 'Settings Panel',
        description: 'Each tab contains a form for that category of settings. Changes are saved per-tab using the Save button at the bottom of each form.',
        side: 'top',
    },
]);

const tabs = [
    { key: 'email', label: 'Email' },
    { key: 'sms', label: 'SMS' },
    { key: 'push', label: 'Push' },
    { key: 'storage', label: 'Storage' },
    { key: 'authentication', label: 'Authentication' },
    { key: 'security', label: 'Security' },
    { key: 'branding', label: 'Branding' },
];

const emailForm = useForm({
    email_driver: props.settings.email_driver ?? null,
    smtp_host: props.settings.smtp_host ?? '',
    smtp_port: props.settings.smtp_port ?? '',
    smtp_username: props.settings.smtp_username ?? '',
    smtp_password: props.settings.smtp_password ?? '',
    smtp_encryption: props.settings.smtp_encryption ?? 'tls',
    smtp_from_address: props.settings.smtp_from_address ?? '',
    smtp_from_name: props.settings.smtp_from_name ?? '',
    postmark_token: props.settings.postmark_token ?? '',
    postmark_from_address: props.settings.postmark_from_address ?? '',
    postmark_from_name: props.settings.postmark_from_name ?? '',
    email_footer_signature: props.settings.email_footer_signature ?? '',
    email_footer_unsubscribe_url: props.settings.email_footer_unsubscribe_url ?? '',
    mailgun_domain: props.settings.mailgun_domain ?? '',
    mailgun_secret: props.settings.mailgun_secret ?? '',
    mailgun_endpoint: props.settings.mailgun_endpoint ?? 'api.mailgun.net',
    mailgun_from_address: props.settings.mailgun_from_address ?? '',
    mailgun_from_name: props.settings.mailgun_from_name ?? '',
    ses_key: props.settings.ses_key ?? '',
    ses_secret: props.settings.ses_secret ?? '',
    ses_region: props.settings.ses_region ?? '',
    ses_from_address: props.settings.ses_from_address ?? '',
    ses_from_name: props.settings.ses_from_name ?? '',
});

const smsForm = useForm({
    sms_driver: props.settings.sms_driver ?? null,
    twilio_sid: props.settings.twilio_sid ?? '',
    twilio_token: props.settings.twilio_token ?? '',
    twilio_from: props.settings.twilio_from ?? '',
    vonage_key: props.settings.vonage_key ?? '',
    vonage_secret: props.settings.vonage_secret ?? '',
    vonage_from: props.settings.vonage_from ?? '',
    sns_sms_key: props.settings.sns_sms_key ?? '',
    sns_sms_secret: props.settings.sns_sms_secret ?? '',
    sns_sms_region: props.settings.sns_sms_region ?? '',
    sns_sms_sender_id: props.settings.sns_sms_sender_id ?? '',
});

const pushForm = useForm({
    push_driver: props.settings.push_driver ?? null,
    fcm_project_id: props.settings.fcm_project_id ?? '',
    fcm_server_key: props.settings.fcm_server_key ?? '',
    apns_key_id: props.settings.apns_key_id ?? '',
    apns_team_id: props.settings.apns_team_id ?? '',
    apns_private_key: props.settings.apns_private_key ?? '',
    apns_bundle_id: props.settings.apns_bundle_id ?? '',
    apns_environment: props.settings.apns_environment ?? 'production',
    onesignal_app_id: props.settings.onesignal_app_id ?? '',
    onesignal_rest_api_key: props.settings.onesignal_rest_api_key ?? '',
});

const securityForm = useForm({
    password_min_length: props.settings.password_min_length ?? '8',
    password_require_uppercase: props.settings.password_require_uppercase === '1' || props.settings.password_require_uppercase === true,
    password_require_digit: props.settings.password_require_digit === '1' || props.settings.password_require_digit === true,
    password_require_symbol: props.settings.password_require_symbol === '1' || props.settings.password_require_symbol === true,
    password_expiry_days: props.settings.password_expiry_days ?? '',
    two_factor_auth: props.settings.two_factor_auth ?? 'off',
    session_concurrency_limit: props.settings.session_concurrency_limit ?? '',
});

const brandingForm = useForm({
    app_name: props.settings.app_name ?? '',
    support_email: props.settings.support_email ?? '',
    support_url: props.settings.support_url ?? '',
    logo_url: props.settings.logo_url ?? '',
    favicon_url: props.settings.favicon_url ?? '',
});

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
    if (!raw) { return new Set(ALL_TYPE_KEYS); }
    return new Set(raw.split(',').map(s => s.trim()).filter(Boolean));
}

const allowedTypeSet = ref(parseAllowedTypes(props.settings.upload_allowed_types));

function toggleType(key) {
    if (allowedTypeSet.value.has(key)) {
        allowedTypeSet.value.delete(key);
    } else {
        allowedTypeSet.value.add(key);
    }
}

const storageForm = useForm({
    storage_driver: props.settings.storage_driver ?? null,
    s3_key: props.settings.s3_key ?? '',
    s3_secret: props.settings.s3_secret ?? '',
    s3_region: props.settings.s3_region ?? '',
    s3_bucket: props.settings.s3_bucket ?? '',
    s3_url: props.settings.s3_url ?? '',
    r2_account_id: props.settings.r2_account_id ?? '',
    r2_access_key: props.settings.r2_access_key ?? '',
    r2_secret: props.settings.r2_secret ?? '',
    r2_bucket: props.settings.r2_bucket ?? '',
    r2_url: props.settings.r2_url ?? '',
    gcs_project_id: props.settings.gcs_project_id ?? '',
    gcs_key_json: props.settings.gcs_key_json ?? '',
    gcs_bucket: props.settings.gcs_bucket ?? '',
    gcs_url: props.settings.gcs_url ?? '',
    ftp_host: props.settings.ftp_host ?? '',
    ftp_port: props.settings.ftp_port ?? '21',
    ftp_username: props.settings.ftp_username ?? '',
    ftp_password: props.settings.ftp_password ?? '',
    ftp_root: props.settings.ftp_root ?? '/',
    ftp_passive: props.settings.ftp_passive ?? true,
    sftp_host: props.settings.sftp_host ?? '',
    sftp_port: props.settings.sftp_port ?? '22',
    sftp_username: props.settings.sftp_username ?? '',
    sftp_password: props.settings.sftp_password ?? '',
    sftp_private_key: props.settings.sftp_private_key ?? '',
    sftp_root: props.settings.sftp_root ?? '/',
    upload_allowed_types: props.settings.upload_allowed_types ?? 'pdf,doc,text,excel,image,csv',
    upload_max_size_pdf: props.settings.upload_max_size_pdf ?? '5',
    upload_max_size_doc: props.settings.upload_max_size_doc ?? '5',
    upload_max_size_text: props.settings.upload_max_size_text ?? '5',
    upload_max_size_excel: props.settings.upload_max_size_excel ?? '5',
    upload_max_size_image: props.settings.upload_max_size_image ?? '5',
    upload_max_size_csv: props.settings.upload_max_size_csv ?? '5',
});

const authForm = useForm({
    authentication_idle_time: props.settings.authentication_idle_time ?? '',
    max_login_attempts: props.settings.max_login_attempts ?? '',
});

function saveEmail() {
    emailForm.put('/admin/settings');
}

function saveSms() {
    smsForm.put('/admin/settings');
}

function savePush() {
    pushForm.put('/admin/settings');
}

function saveSecurity() {
    securityForm.put('/admin/settings');
}

function saveBranding() {
    brandingForm.put('/admin/settings');
}

watch(allowedTypeSet, (set) => {
    storageForm.upload_allowed_types = [...set].join(',');
}, { deep: true });

function saveStorage() {
    storageForm.upload_allowed_types = [...allowedTypeSet.value].join(',');
    storageForm.put('/admin/settings');
}

function saveAuth() {
    authForm.put('/admin/settings');
}

function logout() {
    router.post('/logout');
}

const missingSettings = page.props.missingRequiredSettings ?? [];
</script>

<template>
    <Head title="System Settings" />

    <div class="min-h-screen bg-slate-950 text-slate-100">
        <!-- Sidebar -->
        <aside class="fixed inset-y-0 left-0 w-60 bg-slate-900 border-r border-white/5 flex flex-col">
            <div class="h-16 flex items-center px-6 border-b border-white/5">
                <div class="w-8 h-8 bg-violet-600 rounded-lg flex items-center justify-center mr-3">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <span class="font-semibold text-white">SSO Admin</span>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1">
                <Link href="/admin"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Dashboard
                </Link>
                <Link href="/admin/users"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Users
                </Link>
                <Link href="/admin/apps"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Apps
                </Link>
                <Link href="/admin/tenants"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Tenants
                </Link>
                <Link href="/admin/settings"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition bg-violet-600/20 text-violet-300">
                    Settings
                </Link>
            </nav>
            <div class="p-4 border-t border-white/5">
                <div class="flex items-center gap-3">
                    <Link href="/profile" class="flex items-center gap-3 flex-1 min-w-0 hover:opacity-80 transition">
                        <div v-if="page.props.auth.user?.profile_picture_url" class="w-8 h-8 rounded-full overflow-hidden shrink-0">
                            <img :src="page.props.auth.user.profile_picture_url" class="w-full h-full object-cover" alt="Profile" />
                        </div>
                        <div v-else class="w-8 h-8 rounded-full bg-violet-500 flex items-center justify-center text-xs font-bold text-white shrink-0">
                            {{ page.props.auth.user?.name?.[0]?.toUpperCase() ?? 'A' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ page.props.auth.user?.name ?? 'Admin' }}</p>
                            <p class="text-xs text-slate-400 truncate">{{ page.props.auth.user?.email }}</p>
                        </div>
                    </Link>
                    <button @click="logout" title="Sign out" class="text-slate-400 hover:text-white transition shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <div class="ml-60">
            <!-- Missing settings banner -->
            <div v-if="missingSettings.length > 0"
                 class="bg-amber-500/10 border-b border-amber-500/20 px-8 py-3 flex items-center gap-3">
                <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                <p class="text-sm text-amber-300">
                    Required settings not configured:
                    <span class="font-medium">{{ missingSettings.join(', ') }}</span>.
                </p>
            </div>

            <!-- Header -->
            <header class="h-16 bg-slate-900/50 border-b border-white/5 flex items-center px-8">
                <h2 class="text-lg font-semibold">System Settings</h2>
            </header>

            <main class="p-8">
                <!-- Flash success -->
                <div v-if="page.props.flash?.success"
                     class="mb-6 bg-emerald-500/10 border border-emerald-500/20 rounded-xl px-5 py-3 text-sm text-emerald-300">
                    {{ page.props.flash.success }}
                </div>

                <!-- Tab bar -->
                <div id="tour-settings-tabs" class="flex gap-1 mb-6 bg-slate-900 border border-white/5 rounded-xl p-1 w-fit">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="activeTab = tab.key"
                        :class="activeTab === tab.key
                            ? 'bg-violet-600 text-white shadow'
                            : 'text-slate-400 hover:text-slate-200'"
                        class="px-5 py-1.5 rounded-lg text-sm font-medium transition">
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Email tab -->
                <div id="tour-settings-panel" v-show="activeTab === 'email'" class="max-w-2xl">
                    <form @submit.prevent="saveEmail" class="space-y-6">
                        <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-5">
                            <div>
                                <p class="text-xs text-slate-400 mb-3">Only one email service can be active at a time.</p>
                                <div class="flex flex-wrap gap-5">
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="emailForm.email_driver" value="smtp" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">SMTP</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="emailForm.email_driver" value="postmark" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Postmark</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="emailForm.email_driver" value="mailgun" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Mailgun</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="emailForm.email_driver" value="ses" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Amazon SES</span>
                                    </label>
                                </div>
                                <p v-if="emailForm.errors.email_driver" class="text-xs text-red-400 mt-2">{{ emailForm.errors.email_driver }}</p>
                            </div>

                            <!-- SMTP fields -->
                            <template v-if="emailForm.email_driver === 'smtp'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Host</label>
                                        <input v-model="emailForm.smtp_host" type="text" placeholder="smtp.example.com"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="emailForm.errors.smtp_host" class="text-xs text-red-400 mt-1">{{ emailForm.errors.smtp_host }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Port</label>
                                        <input v-model="emailForm.smtp_port" type="number" placeholder="587"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="emailForm.errors.smtp_port" class="text-xs text-red-400 mt-1">{{ emailForm.errors.smtp_port }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Username</label>
                                        <input v-model="emailForm.smtp_username" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Password</label>
                                        <input v-model="emailForm.smtp_password" type="password"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Encryption</label>
                                        <select v-model="emailForm.smtp_encryption"
                                                class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500">
                                            <option value="tls">TLS</option>
                                            <option value="ssl">SSL</option>
                                            <option value="starttls">STARTTLS</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">From Name</label>
                                        <input v-model="emailForm.smtp_from_name" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">From Address</label>
                                        <input v-model="emailForm.smtp_from_address" type="email"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="emailForm.errors.smtp_from_address" class="text-xs text-red-400 mt-1">{{ emailForm.errors.smtp_from_address }}</p>
                                    </div>
                                </div>
                            </template>

                            <!-- Postmark fields -->
                            <template v-if="emailForm.email_driver === 'postmark'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div class="col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">Server Token</label>
                                        <input v-model="emailForm.postmark_token" type="password"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="emailForm.errors.postmark_token" class="text-xs text-red-400 mt-1">{{ emailForm.errors.postmark_token }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">From Name</label>
                                        <input v-model="emailForm.postmark_from_name" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">From Address</label>
                                        <input v-model="emailForm.postmark_from_address" type="email"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="emailForm.errors.postmark_from_address" class="text-xs text-red-400 mt-1">{{ emailForm.errors.postmark_from_address }}</p>
                                    </div>
                                </div>
                            </template>

                            <!-- Mailgun fields -->
                            <template v-if="emailForm.email_driver === 'mailgun'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Domain</label>
                                        <input v-model="emailForm.mailgun_domain" type="text" placeholder="mg.example.com"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="emailForm.errors.mailgun_domain" class="text-xs text-red-400 mt-1">{{ emailForm.errors.mailgun_domain }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">API Key</label>
                                        <input v-model="emailForm.mailgun_secret" type="password"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="emailForm.errors.mailgun_secret" class="text-xs text-red-400 mt-1">{{ emailForm.errors.mailgun_secret }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Region</label>
                                        <select v-model="emailForm.mailgun_endpoint"
                                                class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500">
                                            <option value="api.mailgun.net">US (api.mailgun.net)</option>
                                            <option value="api.eu.mailgun.net">EU (api.eu.mailgun.net)</option>
                                        </select>
                                        <p v-if="emailForm.errors.mailgun_endpoint" class="text-xs text-red-400 mt-1">{{ emailForm.errors.mailgun_endpoint }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">From Name</label>
                                        <input v-model="emailForm.mailgun_from_name" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">From Address</label>
                                        <input v-model="emailForm.mailgun_from_address" type="email"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="emailForm.errors.mailgun_from_address" class="text-xs text-red-400 mt-1">{{ emailForm.errors.mailgun_from_address }}</p>
                                    </div>
                                </div>
                            </template>

                            <!-- Amazon SES fields -->
                            <template v-if="emailForm.email_driver === 'ses'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Access Key</label>
                                        <input v-model="emailForm.ses_key" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="emailForm.errors.ses_key" class="text-xs text-red-400 mt-1">{{ emailForm.errors.ses_key }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Secret Key</label>
                                        <input v-model="emailForm.ses_secret" type="password"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="emailForm.errors.ses_secret" class="text-xs text-red-400 mt-1">{{ emailForm.errors.ses_secret }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Region</label>
                                        <input v-model="emailForm.ses_region" type="text" placeholder="us-east-1"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="emailForm.errors.ses_region" class="text-xs text-red-400 mt-1">{{ emailForm.errors.ses_region }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">From Name</label>
                                        <input v-model="emailForm.ses_from_name" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">From Address</label>
                                        <input v-model="emailForm.ses_from_address" type="email"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="emailForm.errors.ses_from_address" class="text-xs text-red-400 mt-1">{{ emailForm.errors.ses_from_address }}</p>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Email footer -->
                        <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-4">
                            <h3 class="font-semibold text-white">Email Footer</h3>
                            <p class="text-xs text-slate-400">Appended to all outbound emails. Tenants can override this in their own settings.</p>

                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Signature</label>
                                <RichTextEditor v-model="emailForm.email_footer_signature" />
                                <p class="text-xs text-slate-600 mt-1">Basic HTML supported. Max 2000 characters.</p>
                                <p v-if="emailForm.errors.email_footer_signature" class="text-xs text-red-400 mt-1">{{ emailForm.errors.email_footer_signature }}</p>
                            </div>

                            <div>
                                <label class="block text-xs text-slate-400 mb-1">
                                    Unsubscribe URL
                                    <span class="text-slate-600 ml-1">— optional, required by CAN-SPAM/GDPR for marketing emails</span>
                                </label>
                                <input v-model="emailForm.email_footer_unsubscribe_url" type="url"
                                       placeholder="https://example.com/unsubscribe"
                                       class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                <p v-if="emailForm.errors.email_footer_unsubscribe_url" class="text-xs text-red-400 mt-1">{{ emailForm.errors.email_footer_unsubscribe_url }}</p>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="emailForm.processing"
                                    class="bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg transition">
                                {{ emailForm.processing ? 'Saving…' : 'Save Email Settings' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- SMS tab -->
                <div v-show="activeTab === 'sms'" class="max-w-2xl">
                    <form @submit.prevent="saveSms" class="space-y-6">
                        <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-5">

                            <div>
                                <p class="text-xs text-slate-400 mb-3">Only one SMS provider can be active at a time.</p>
                                <div class="flex flex-wrap gap-5">
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="smsForm.sms_driver" value="twilio" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Twilio</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="smsForm.sms_driver" value="vonage" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Vonage</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="smsForm.sms_driver" value="sns" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Amazon SNS</span>
                                    </label>
                                </div>
                                <p v-if="smsForm.errors.sms_driver" class="text-xs text-red-400 mt-2">{{ smsForm.errors.sms_driver }}</p>
                            </div>

                            <!-- Twilio fields -->
                            <template v-if="smsForm.sms_driver === 'twilio'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div class="col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">Account SID</label>
                                        <input v-model="smsForm.twilio_sid" type="text" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="smsForm.errors.twilio_sid" class="text-xs text-red-400 mt-1">{{ smsForm.errors.twilio_sid }}</p>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">Auth Token</label>
                                        <input v-model="smsForm.twilio_token" type="password"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="smsForm.errors.twilio_token" class="text-xs text-red-400 mt-1">{{ smsForm.errors.twilio_token }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">From Number</label>
                                        <input v-model="smsForm.twilio_from" type="text" placeholder="+15550001234"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p class="text-xs text-slate-600 mt-1">Twilio phone number in E.164 format.</p>
                                        <p v-if="smsForm.errors.twilio_from" class="text-xs text-red-400 mt-1">{{ smsForm.errors.twilio_from }}</p>
                                    </div>
                                </div>
                            </template>

                            <!-- Vonage fields -->
                            <template v-if="smsForm.sms_driver === 'vonage'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">API Key</label>
                                        <input v-model="smsForm.vonage_key" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="smsForm.errors.vonage_key" class="text-xs text-red-400 mt-1">{{ smsForm.errors.vonage_key }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">API Secret</label>
                                        <input v-model="smsForm.vonage_secret" type="password"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="smsForm.errors.vonage_secret" class="text-xs text-red-400 mt-1">{{ smsForm.errors.vonage_secret }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">From</label>
                                        <input v-model="smsForm.vonage_from" type="text" placeholder="+15550001234 or MyApp"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p class="text-xs text-slate-600 mt-1">Phone number or alphanumeric sender ID (max 11 chars).</p>
                                        <p v-if="smsForm.errors.vonage_from" class="text-xs text-red-400 mt-1">{{ smsForm.errors.vonage_from }}</p>
                                    </div>
                                </div>
                            </template>

                            <!-- Amazon SNS fields -->
                            <template v-if="smsForm.sms_driver === 'sns'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Access Key</label>
                                        <input v-model="smsForm.sns_sms_key" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="smsForm.errors.sns_sms_key" class="text-xs text-red-400 mt-1">{{ smsForm.errors.sns_sms_key }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Secret Key</label>
                                        <input v-model="smsForm.sns_sms_secret" type="password"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="smsForm.errors.sns_sms_secret" class="text-xs text-red-400 mt-1">{{ smsForm.errors.sns_sms_secret }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Region</label>
                                        <input v-model="smsForm.sns_sms_region" type="text" placeholder="us-east-1"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="smsForm.errors.sns_sms_region" class="text-xs text-red-400 mt-1">{{ smsForm.errors.sns_sms_region }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">
                                            Sender ID <span class="text-slate-600 ml-1">optional, max 11 chars</span>
                                        </label>
                                        <input v-model="smsForm.sns_sms_sender_id" type="text" placeholder="MyApp"
                                               maxlength="11"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="smsForm.errors.sns_sms_sender_id" class="text-xs text-red-400 mt-1">{{ smsForm.errors.sns_sms_sender_id }}</p>
                                    </div>
                                </div>
                            </template>

                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="smsForm.processing"
                                    class="bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg transition">
                                {{ smsForm.processing ? 'Saving…' : 'Save SMS Settings' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Push Notifications tab -->
                <div v-show="activeTab === 'push'" class="max-w-2xl">
                    <form @submit.prevent="savePush" class="space-y-6">
                        <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-5">

                            <div>
                                <p class="text-xs text-slate-400 mb-3">Only one push notification provider can be active at a time.</p>
                                <div class="flex flex-wrap gap-5">
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="pushForm.push_driver" value="fcm" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Firebase (FCM)</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="pushForm.push_driver" value="apns" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Apple (APNs)</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="pushForm.push_driver" value="onesignal" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">OneSignal</span>
                                    </label>
                                </div>
                                <p v-if="pushForm.errors.push_driver" class="text-xs text-red-400 mt-2">{{ pushForm.errors.push_driver }}</p>
                            </div>

                            <!-- FCM fields -->
                            <template v-if="pushForm.push_driver === 'fcm'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Project ID</label>
                                        <input v-model="pushForm.fcm_project_id" type="text" placeholder="my-firebase-project"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="pushForm.errors.fcm_project_id" class="text-xs text-red-400 mt-1">{{ pushForm.errors.fcm_project_id }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Server Key</label>
                                        <input v-model="pushForm.fcm_server_key" type="password"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p class="text-xs text-slate-600 mt-1">Found in Firebase Console → Project Settings → Cloud Messaging.</p>
                                        <p v-if="pushForm.errors.fcm_server_key" class="text-xs text-red-400 mt-1">{{ pushForm.errors.fcm_server_key }}</p>
                                    </div>
                                </div>
                            </template>

                            <!-- APNs fields -->
                            <template v-if="pushForm.push_driver === 'apns'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Key ID</label>
                                        <input v-model="pushForm.apns_key_id" type="text" placeholder="ABCDE12345"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="pushForm.errors.apns_key_id" class="text-xs text-red-400 mt-1">{{ pushForm.errors.apns_key_id }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Team ID</label>
                                        <input v-model="pushForm.apns_team_id" type="text" placeholder="ABCDE12345"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="pushForm.errors.apns_team_id" class="text-xs text-red-400 mt-1">{{ pushForm.errors.apns_team_id }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Bundle ID</label>
                                        <input v-model="pushForm.apns_bundle_id" type="text" placeholder="com.example.myapp"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="pushForm.errors.apns_bundle_id" class="text-xs text-red-400 mt-1">{{ pushForm.errors.apns_bundle_id }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Environment</label>
                                        <select v-model="pushForm.apns_environment"
                                                class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500">
                                            <option value="production">Production</option>
                                            <option value="sandbox">Sandbox</option>
                                        </select>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">Private Key (.p8)</label>
                                        <textarea v-model="pushForm.apns_private_key" rows="6"
                                                  placeholder="-----BEGIN PRIVATE KEY-----"
                                                  class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 font-mono focus:outline-none focus:border-violet-500 resize-none"></textarea>
                                        <p class="text-xs text-slate-600 mt-1">Paste the contents of the .p8 file downloaded from Apple Developer.</p>
                                        <p v-if="pushForm.errors.apns_private_key" class="text-xs text-red-400 mt-1">{{ pushForm.errors.apns_private_key }}</p>
                                    </div>
                                </div>
                            </template>

                            <!-- OneSignal fields -->
                            <template v-if="pushForm.push_driver === 'onesignal'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">App ID</label>
                                        <input v-model="pushForm.onesignal_app_id" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="pushForm.errors.onesignal_app_id" class="text-xs text-red-400 mt-1">{{ pushForm.errors.onesignal_app_id }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">REST API Key</label>
                                        <input v-model="pushForm.onesignal_rest_api_key" type="password"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="pushForm.errors.onesignal_rest_api_key" class="text-xs text-red-400 mt-1">{{ pushForm.errors.onesignal_rest_api_key }}</p>
                                    </div>
                                </div>
                            </template>

                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="pushForm.processing"
                                    class="bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg transition">
                                {{ pushForm.processing ? 'Saving…' : 'Save Push Settings' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Storage tab -->
                <div v-show="activeTab === 'storage'" class="max-w-2xl">
                    <form @submit.prevent="saveStorage" class="space-y-6">
                        <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-5">

                            <!-- Driver selector -->
                            <div>
                                <p class="text-xs text-slate-400 mb-3">Only one storage provider can be active at a time.</p>
                                <div class="flex flex-wrap gap-5">
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="storageForm.storage_driver" value="local" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Local</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="storageForm.storage_driver" value="s3" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Amazon S3</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="storageForm.storage_driver" value="r2" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Cloudflare R2</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="storageForm.storage_driver" value="gcs" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Google Cloud Storage</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="storageForm.storage_driver" value="ftp" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">FTP</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="storageForm.storage_driver" value="sftp" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">SFTP</span>
                                    </label>
                                </div>
                                <p v-if="storageForm.errors.storage_driver" class="text-xs text-red-400 mt-2">{{ storageForm.errors.storage_driver }}</p>
                            </div>

                            <!-- Local — no fields needed -->
                            <template v-if="storageForm.storage_driver === 'local'">
                                <div class="border-t border-white/5 pt-5">
                                    <div class="flex items-start gap-3 bg-slate-800/50 rounded-lg px-4 py-3">
                                        <svg class="w-4 h-4 text-violet-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div class="text-sm text-slate-300 space-y-1">
                                            <p>Files will be stored in <code class="text-violet-300 bg-slate-900 px-1 py-0.5 rounded text-xs">storage/app/</code> on the server.</p>
                                            <p class="text-slate-500 text-xs">No credentials required. Best for development or single-server setups. Not suitable for multi-server deployments.</p>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Amazon S3 fields -->
                            <template v-if="storageForm.storage_driver === 's3'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Access Key</label>
                                        <input v-model="storageForm.s3_key" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.s3_key" class="text-xs text-red-400 mt-1">{{ storageForm.errors.s3_key }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Secret Key</label>
                                        <input v-model="storageForm.s3_secret" type="password"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.s3_secret" class="text-xs text-red-400 mt-1">{{ storageForm.errors.s3_secret }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Region</label>
                                        <input v-model="storageForm.s3_region" type="text" placeholder="us-east-1"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.s3_region" class="text-xs text-red-400 mt-1">{{ storageForm.errors.s3_region }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Bucket</label>
                                        <input v-model="storageForm.s3_bucket" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.s3_bucket" class="text-xs text-red-400 mt-1">{{ storageForm.errors.s3_bucket }}</p>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">
                                            Custom URL <span class="text-slate-600 ml-1">optional</span>
                                        </label>
                                        <input v-model="storageForm.s3_url" type="url" placeholder="https://cdn.example.com"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.s3_url" class="text-xs text-red-400 mt-1">{{ storageForm.errors.s3_url }}</p>
                                    </div>
                                </div>
                            </template>

                            <!-- Cloudflare R2 fields -->
                            <template v-if="storageForm.storage_driver === 'r2'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div class="col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">Account ID</label>
                                        <input v-model="storageForm.r2_account_id" type="text" placeholder="abc123def456..."
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p class="text-xs text-slate-600 mt-1">Found in the Cloudflare dashboard under R2 → Overview.</p>
                                        <p v-if="storageForm.errors.r2_account_id" class="text-xs text-red-400 mt-1">{{ storageForm.errors.r2_account_id }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Access Key ID</label>
                                        <input v-model="storageForm.r2_access_key" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.r2_access_key" class="text-xs text-red-400 mt-1">{{ storageForm.errors.r2_access_key }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Secret Access Key</label>
                                        <input v-model="storageForm.r2_secret" type="password"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.r2_secret" class="text-xs text-red-400 mt-1">{{ storageForm.errors.r2_secret }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Bucket</label>
                                        <input v-model="storageForm.r2_bucket" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.r2_bucket" class="text-xs text-red-400 mt-1">{{ storageForm.errors.r2_bucket }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">
                                            Public URL <span class="text-slate-600 ml-1">optional</span>
                                        </label>
                                        <input v-model="storageForm.r2_url" type="url" placeholder="https://pub.example.com"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.r2_url" class="text-xs text-red-400 mt-1">{{ storageForm.errors.r2_url }}</p>
                                    </div>
                                </div>
                            </template>

                            <!-- Google Cloud Storage fields -->
                            <template v-if="storageForm.storage_driver === 'gcs'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Project ID</label>
                                        <input v-model="storageForm.gcs_project_id" type="text" placeholder="my-gcp-project"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.gcs_project_id" class="text-xs text-red-400 mt-1">{{ storageForm.errors.gcs_project_id }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Bucket</label>
                                        <input v-model="storageForm.gcs_bucket" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.gcs_bucket" class="text-xs text-red-400 mt-1">{{ storageForm.errors.gcs_bucket }}</p>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">Service Account Key JSON</label>
                                        <textarea v-model="storageForm.gcs_key_json" rows="6"
                                                  placeholder='{"type":"service_account","project_id":"...","private_key":"...","client_email":"..."}'
                                                  class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 font-mono focus:outline-none focus:border-violet-500 resize-none"></textarea>
                                        <p class="text-xs text-slate-600 mt-1">Paste the full JSON from your GCP service account key file.</p>
                                        <p v-if="storageForm.errors.gcs_key_json" class="text-xs text-red-400 mt-1">{{ storageForm.errors.gcs_key_json }}</p>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">
                                            Custom URL <span class="text-slate-600 ml-1">optional</span>
                                        </label>
                                        <input v-model="storageForm.gcs_url" type="url" placeholder="https://cdn.example.com"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.gcs_url" class="text-xs text-red-400 mt-1">{{ storageForm.errors.gcs_url }}</p>
                                    </div>
                                </div>
                            </template>

                            <!-- FTP fields -->
                            <template v-if="storageForm.storage_driver === 'ftp'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Host</label>
                                        <input v-model="storageForm.ftp_host" type="text" placeholder="ftp.example.com"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.ftp_host" class="text-xs text-red-400 mt-1">{{ storageForm.errors.ftp_host }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Port</label>
                                        <input v-model="storageForm.ftp_port" type="number"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.ftp_port" class="text-xs text-red-400 mt-1">{{ storageForm.errors.ftp_port }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Username</label>
                                        <input v-model="storageForm.ftp_username" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Password</label>
                                        <input v-model="storageForm.ftp_password" type="password"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Root Path</label>
                                        <input v-model="storageForm.ftp_root" type="text" placeholder="/"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                    </div>
                                    <div class="flex items-center gap-3 pt-5">
                                        <label class="flex items-center gap-2.5 cursor-pointer">
                                            <input type="checkbox" v-model="storageForm.ftp_passive" class="accent-violet-500 w-4 h-4" />
                                            <span class="text-sm text-slate-300">Passive mode</span>
                                        </label>
                                    </div>
                                </div>
                            </template>

                            <!-- SFTP fields -->
                            <template v-if="storageForm.storage_driver === 'sftp'">
                                <div class="border-t border-white/5 pt-5 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Host</label>
                                        <input v-model="storageForm.sftp_host" type="text" placeholder="sftp.example.com"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.sftp_host" class="text-xs text-red-400 mt-1">{{ storageForm.errors.sftp_host }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Port</label>
                                        <input v-model="storageForm.sftp_port" type="number"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <p v-if="storageForm.errors.sftp_port" class="text-xs text-red-400 mt-1">{{ storageForm.errors.sftp_port }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Username</label>
                                        <input v-model="storageForm.sftp_username" type="text"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">
                                            Password <span class="text-slate-600 ml-1">optional if using private key</span>
                                        </label>
                                        <input v-model="storageForm.sftp_password" type="password"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Root Path</label>
                                        <input v-model="storageForm.sftp_root" type="text" placeholder="/"
                                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">
                                            Private Key <span class="text-slate-600 ml-1">optional if using password</span>
                                        </label>
                                        <textarea v-model="storageForm.sftp_private_key" rows="5"
                                                  placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"
                                                  class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 font-mono focus:outline-none focus:border-violet-500 resize-none"></textarea>
                                        <p v-if="storageForm.errors.sftp_private_key" class="text-xs text-red-400 mt-1">{{ storageForm.errors.sftp_private_key }}</p>
                                    </div>
                                </div>
                            </template>

                        </div>

                        <!-- Upload Settings -->
                        <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-5">
                            <div>
                                <h3 class="font-semibold text-white">Document Upload Settings</h3>
                                <p class="text-xs text-slate-400 mt-1">Set which file types are allowed and the maximum upload size per type. Tenants can override these in their settings.</p>
                            </div>

                            <div class="space-y-3">
                                <div v-for="group in FILE_TYPE_GROUPS" :key="group.key"
                                     class="flex items-center gap-4 py-2 border-b border-white/5 last:border-0">
                                    <label class="flex items-center gap-2.5 cursor-pointer w-52 shrink-0">
                                        <input type="checkbox"
                                               :checked="allowedTypeSet.has(group.key)"
                                               @change="toggleType(group.key)"
                                               class="accent-violet-500 w-4 h-4" />
                                        <span class="text-sm text-slate-300">{{ group.label }}</span>
                                    </label>
                                    <div class="flex items-center gap-2" :class="{ 'opacity-40 pointer-events-none': !allowedTypeSet.has(group.key) }">
                                        <input v-model="storageForm[`upload_max_size_${group.key}`]"
                                               type="number" min="1" max="100"
                                               class="w-20 bg-slate-800 border border-white/10 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-violet-500" />
                                        <span class="text-sm text-slate-400">MB max</span>
                                        <p v-if="storageForm.errors[`upload_max_size_${group.key}`]" class="text-xs text-red-400">{{ storageForm.errors[`upload_max_size_${group.key}`] }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="storageForm.processing"
                                    class="bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg transition">
                                {{ storageForm.processing ? 'Saving…' : 'Save Storage Settings' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security tab -->
                <div v-show="activeTab === 'security'" class="max-w-2xl">
                    <form @submit.prevent="saveSecurity" class="space-y-6">
                        <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-6">

                            <!-- Password policy -->
                            <div>
                                <h4 class="text-sm font-medium text-white mb-4">Password Policy</h4>
                                <div class="space-y-4">
                                    <div class="flex items-center gap-4">
                                        <label class="text-sm text-slate-300 w-40 shrink-0">Minimum length</label>
                                        <div class="flex items-center gap-2">
                                            <input v-model="securityForm.password_min_length" type="number" min="6" max="128"
                                                   class="w-20 bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                            <span class="text-sm text-slate-400">characters</span>
                                        </div>
                                        <p v-if="securityForm.errors.password_min_length" class="text-xs text-red-400">{{ securityForm.errors.password_min_length }}</p>
                                    </div>
                                    <div class="space-y-2.5">
                                        <label class="flex items-center gap-3 cursor-pointer group">
                                            <input type="checkbox" v-model="securityForm.password_require_uppercase" class="accent-violet-500 w-4 h-4" />
                                            <span class="text-sm text-slate-300 group-hover:text-white transition">Require uppercase letter (A–Z)</span>
                                        </label>
                                        <label class="flex items-center gap-3 cursor-pointer group">
                                            <input type="checkbox" v-model="securityForm.password_require_digit" class="accent-violet-500 w-4 h-4" />
                                            <span class="text-sm text-slate-300 group-hover:text-white transition">Require digit (0–9)</span>
                                        </label>
                                        <label class="flex items-center gap-3 cursor-pointer group">
                                            <input type="checkbox" v-model="securityForm.password_require_symbol" class="accent-violet-500 w-4 h-4" />
                                            <span class="text-sm text-slate-300 group-hover:text-white transition">Require symbol (!@#$…)</span>
                                        </label>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <label class="text-sm text-slate-300 w-40 shrink-0">Expires after</label>
                                        <div class="flex items-center gap-2">
                                            <input v-model="securityForm.password_expiry_days" type="number" min="0" max="365"
                                                   placeholder="0"
                                                   class="w-20 bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                            <span class="text-sm text-slate-400">days (0 = never)</span>
                                        </div>
                                        <p v-if="securityForm.errors.password_expiry_days" class="text-xs text-red-400">{{ securityForm.errors.password_expiry_days }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- 2FA -->
                            <div class="border-t border-white/5 pt-6">
                                <h4 class="text-sm font-medium text-white mb-3">Two-Factor Authentication</h4>
                                <div class="flex gap-6">
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="securityForm.two_factor_auth" value="off" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Off</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="securityForm.two_factor_auth" value="optional" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Optional</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group">
                                        <input type="radio" v-model="securityForm.two_factor_auth" value="required" class="accent-violet-500" />
                                        <span class="text-sm text-slate-300 group-hover:text-white transition">Required for all users</span>
                                    </label>
                                </div>
                                <p v-if="securityForm.errors.two_factor_auth" class="text-xs text-red-400 mt-2">{{ securityForm.errors.two_factor_auth }}</p>
                            </div>

                            <!-- Session concurrency -->
                            <div class="border-t border-white/5 pt-6">
                                <h4 class="text-sm font-medium text-white mb-1">Session Concurrency Limit</h4>
                                <div class="flex items-center gap-3 mt-2">
                                    <input v-model="securityForm.session_concurrency_limit" type="number" min="0" max="100"
                                           placeholder="0"
                                           class="w-20 bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                    <span class="text-sm text-slate-400">active sessions per user (0 = unlimited)</span>
                                </div>
                                <p v-if="securityForm.errors.session_concurrency_limit" class="text-xs text-red-400 mt-1">{{ securityForm.errors.session_concurrency_limit }}</p>
                            </div>

                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="securityForm.processing"
                                    class="bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg transition">
                                {{ securityForm.processing ? 'Saving…' : 'Save Security Settings' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Branding tab -->
                <div v-show="activeTab === 'branding'" class="max-w-2xl">
                    <form @submit.prevent="saveBranding" class="space-y-6">
                        <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-4">

                            <div>
                                <label class="block text-xs text-slate-400 mb-1">App Name</label>
                                <input v-model="brandingForm.app_name" type="text" placeholder="My SaaS App"
                                       class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                <p v-if="brandingForm.errors.app_name" class="text-xs text-red-400 mt-1">{{ brandingForm.errors.app_name }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Support Email</label>
                                    <input v-model="brandingForm.support_email" type="email" placeholder="support@example.com"
                                           class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                    <p v-if="brandingForm.errors.support_email" class="text-xs text-red-400 mt-1">{{ brandingForm.errors.support_email }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Support URL</label>
                                    <input v-model="brandingForm.support_url" type="url" placeholder="https://help.example.com"
                                           class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                    <p v-if="brandingForm.errors.support_url" class="text-xs text-red-400 mt-1">{{ brandingForm.errors.support_url }}</p>
                                </div>
                            </div>

                            <div class="border-t border-white/5 pt-4 space-y-4">
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">
                                        Logo URL <span class="text-slate-600 ml-1">optional</span>
                                    </label>
                                    <input v-model="brandingForm.logo_url" type="url" placeholder="https://cdn.example.com/logo.png"
                                           class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                    <p v-if="brandingForm.errors.logo_url" class="text-xs text-red-400 mt-1">{{ brandingForm.errors.logo_url }}</p>
                                    <div v-if="brandingForm.logo_url" class="mt-2">
                                        <img :src="brandingForm.logo_url" alt="Logo preview" class="h-10 object-contain rounded" @error="e => e.target.style.display = 'none'" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">
                                        Favicon URL <span class="text-slate-600 ml-1">optional</span>
                                    </label>
                                    <input v-model="brandingForm.favicon_url" type="url" placeholder="https://cdn.example.com/favicon.ico"
                                           class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-violet-500" />
                                    <p v-if="brandingForm.errors.favicon_url" class="text-xs text-red-400 mt-1">{{ brandingForm.errors.favicon_url }}</p>
                                    <div v-if="brandingForm.favicon_url" class="mt-2 flex items-center gap-2">
                                        <img :src="brandingForm.favicon_url" alt="Favicon preview" class="w-6 h-6 object-contain rounded" @error="e => e.target.style.display = 'none'" />
                                        <span class="text-xs text-slate-500">favicon preview</span>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="brandingForm.processing"
                                    class="bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg transition">
                                {{ brandingForm.processing ? 'Saving…' : 'Save Branding Settings' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Authentication tab -->
                <div v-show="activeTab === 'authentication'" class="max-w-2xl">
                    <form @submit.prevent="saveAuth" class="space-y-6">
                        <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-6">

                            <div>
                                <label class="block text-sm font-medium text-white mb-1">Idle Session Timeout</label>
                                <div class="flex items-center gap-3">
                                    <input v-model="authForm.authentication_idle_time" type="number" min="1" max="1440"
                                           class="w-32 bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                    <span class="text-sm text-slate-400">minutes</span>
                                </div>
                                <p v-if="authForm.errors.authentication_idle_time" class="text-xs text-red-400 mt-1">{{ authForm.errors.authentication_idle_time }}</p>
                                <p class="text-xs text-slate-500 mt-2">Users are automatically logged out after this many minutes of inactivity. Max 1440 (24 hours).</p>
                            </div>

                            <div class="border-t border-white/5 pt-6">
                                <label class="block text-sm font-medium text-white mb-1">Max Login Attempts</label>
                                <div class="flex items-center gap-3">
                                    <input v-model="authForm.max_login_attempts" type="number" min="1" max="100"
                                           class="w-32 bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                                    <span class="text-sm text-slate-400">attempts</span>
                                </div>
                                <p v-if="authForm.errors.max_login_attempts" class="text-xs text-red-400 mt-1">{{ authForm.errors.max_login_attempts }}</p>
                                <p class="text-xs text-slate-500 mt-2">Number of failed login attempts before the account is temporarily locked. Max 100.</p>
                            </div>

                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="authForm.processing"
                                    class="bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg transition">
                                {{ authForm.processing ? 'Saving…' : 'Save Authentication Settings' }}
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <TourButton @click="startTour" />
</template>
