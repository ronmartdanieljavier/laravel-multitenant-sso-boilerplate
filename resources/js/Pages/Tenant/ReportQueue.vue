<script setup>
import { Head, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TenantLayout from '../../Layouts/TenantLayout.vue';
import TourButton from '../Partials/TourButton.vue';
import { useTour } from '../../composables/useTour';

defineOptions({ layout: TenantLayout });

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const props = defineProps({
    tenant: Object,
    reports: Object,
    subscriptions: Array,
});

// ── Report polling ──────────────────────────────────────────────────────────

const hasActiveJobs = computed(() =>
    props.reports.data.some(r => r.status === 'pending' || r.status === 'processing')
);

usePoll(4000, { only: ['reports'] }, { autoStart: true });

// ── Status / format badges ──────────────────────────────────────────────────

const statusConfig = {
    pending:    { label: 'Pending',    cls: 'bg-amber-500/20 text-amber-300' },
    processing: { label: 'Processing', cls: 'bg-blue-500/20 text-blue-300 animate-pulse' },
    success:    { label: 'Done',       cls: 'bg-emerald-500/20 text-emerald-400' },
    failed:     { label: 'Failed',     cls: 'bg-red-500/20 text-red-400' },
};

function statusBadge(status) {
    return statusConfig[status] ?? { label: status, cls: 'bg-slate-700 text-slate-400' };
}

const formatConfig = {
    pdf:    { label: 'PDF',    cls: 'bg-red-500/20 text-red-300' },
    excel:  { label: 'Excel',  cls: 'bg-emerald-500/20 text-emerald-300' },
    screen: { label: 'Screen', cls: 'bg-blue-500/20 text-blue-300' },
};

function formatBadge(format) {
    return formatConfig[format] ?? { label: format, cls: 'bg-slate-700 text-slate-400' };
}

function formatDate(val) {
    if (!val) { return '—'; }
    return new Date(val).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function duration(report) {
    if (!report.started_at || !report.completed_at) { return null; }
    const ms = new Date(report.completed_at) - new Date(report.started_at);
    return ms < 1000 ? `${ms}ms` : `${(ms / 1000).toFixed(1)}s`;
}

function goToPage(url) {
    if (url) { router.visit(url, { preserveScroll: true }); }
}

function retryReport(id) {
    router.post(`/tenant/reports/${id}/retry`, {}, { preserveScroll: true });
}

// ── Generate Report modal ───────────────────────────────────────────────────

const showGenerateModal = ref(false);

const quickForm = useForm({
    type: 'documents_summary',
    format: 'screen',
});

const reportTypes = [
    { value: 'documents_summary', label: 'Documents Summary' },
];

const reportFormats = [
    { value: 'screen', label: 'Screen' },
    { value: 'pdf', label: 'PDF' },
    { value: 'excel', label: 'Excel' },
];

function submitQuickReport() {
    quickForm.post('/tenant/reports/quick', {
        preserveScroll: true,
        onSuccess: () => {
            showGenerateModal.value = false;
            quickForm.reset();
        },
    });
}

// ── Subscriptions ───────────────────────────────────────────────────────────

const showSubscriptionModal = ref(false);

const subForm = useForm({
    type: 'documents_summary',
    format: 'screen',
    frequency: 'daily',
    delivery: 'none',
    recipients: '',
});

const frequencies = [
    { value: 'daily',   label: 'Daily' },
    { value: 'weekly',  label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
];

const deliveries = [
    { value: 'none',    label: 'None' },
    { value: 'email',   label: 'Email' },
    { value: 's3',      label: 'S3' },
];

function submitSubscription() {
    const data = {
        ...subForm.data(),
        recipients: subForm.recipients
            ? subForm.recipients.split(',').map(e => e.trim()).filter(Boolean)
            : [],
    };
    subForm.transform(() => data).post('/tenant/reports/subscriptions', {
        preserveScroll: true,
        onSuccess: () => {
            showSubscriptionModal.value = false;
            subForm.reset();
        },
    });
}

function toggleSubscription(id) {
    router.put(`/tenant/reports/subscriptions/${id}`, {}, { preserveScroll: true });
}

function deleteSubscription(id) {
    router.delete(`/tenant/reports/subscriptions/${id}`, { preserveScroll: true });
}

const frequencyLabel = {
    daily: 'Daily', weekly: 'Weekly', monthly: 'Monthly',
};

const deliveryLabel = {
    none: 'None', email: 'Email', s3: 'S3', email_and_s3: 'Email + S3',
};

// ── Tour ────────────────────────────────────────────────────────────────────

const { startTour } = useTour('tenant-report-queue', [
    {
        element: '#tour-rq-header',
        title: 'Report Queue',
        description: 'This page shows all report generation jobs for your tenant. Reports are processed asynchronously and this page auto-refreshes while jobs are active.',
    },
    {
        element: '#tour-rq-generate',
        title: 'Generate Report',
        description: 'Click this button to dispatch a quick report job. Choose the report type and format from the modal.',
        side: 'bottom',
    },
    {
        element: '#tour-rq-stats',
        title: 'Job Counters',
        description: 'A quick summary of how many reports are pending, currently processing, or have failed.',
        side: 'bottom',
    },
    {
        element: '#tour-rq-table',
        title: 'Report Jobs Table',
        description: 'Each row is a report job. You can see the type, format (PDF, Excel, Screen), who requested it, current status, and how long it took. Completed reports have a Download link.',
        side: 'top',
    },
    {
        element: '#tour-rq-subscriptions',
        title: 'Scheduled Subscriptions',
        description: 'Manage recurring report subscriptions here. Create, pause, or delete subscriptions that automatically dispatch report jobs on a schedule.',
        side: 'top',
    },
]);
</script>

<template>
    <Head title="Report Queue" />

    <main class="flex-1 px-8 py-10">

        <!-- Header -->
        <div id="tour-rq-header" class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-semibold text-white">Report Queue</h1>
                <p class="text-sm text-slate-500 mt-0.5">All report jobs for this tenant · auto-refreshes every 4 s while jobs are active.</p>
            </div>
            <div class="flex items-center gap-3">
                <div v-if="hasActiveJobs" class="flex items-center gap-2 text-xs text-blue-400">
                    <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse inline-block"></span>
                    Live
                </div>
                <button id="tour-rq-generate"
                        @click="showGenerateModal = true"
                        class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium transition">
                    Generate Report
                </button>
            </div>
        </div>

        <!-- Flash -->
        <div v-if="flash.success"
             class="mb-6 px-4 py-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
            {{ flash.success }}
        </div>

        <!-- Stat cards -->
        <div id="tour-rq-stats" class="grid grid-cols-4 gap-4 mb-8">
            <div class="bg-slate-900 border border-white/5 rounded-xl p-4">
                <p class="text-xs text-slate-500 mb-1">Total</p>
                <p class="text-2xl font-semibold text-white">{{ reports.total }}</p>
            </div>
            <div class="bg-slate-900 border border-white/5 rounded-xl p-4">
                <p class="text-xs text-amber-400 mb-1">Pending</p>
                <p class="text-2xl font-semibold text-white">{{ reports.data.filter(r => r.status === 'pending').length }}</p>
            </div>
            <div class="bg-slate-900 border border-white/5 rounded-xl p-4">
                <p class="text-xs text-blue-400 mb-1">Processing</p>
                <p class="text-2xl font-semibold text-white">{{ reports.data.filter(r => r.status === 'processing').length }}</p>
            </div>
            <div class="bg-slate-900 border border-white/5 rounded-xl p-4">
                <p class="text-xs text-red-400 mb-1">Failed</p>
                <p class="text-2xl font-semibold text-white">{{ reports.data.filter(r => r.status === 'failed').length }}</p>
            </div>
        </div>

        <!-- Report jobs table -->
        <div id="tour-rq-table" class="bg-slateate-900 bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/5 text-xs text-slate-500 uppercase tracking-wide">
                        <th class="text-left px-4 py-3 font-medium">Type</th>
                        <th class="text-left px-4 py-3 font-medium">Format</th>
                        <th class="text-left px-4 py-3 font-medium">Requested by</th>
                        <th class="text-left px-4 py-3 font-medium">Status</th>
                        <th class="text-left px-4 py-3 font-medium">Queued</th>
                        <th class="text-left px-4 py-3 font-medium">Duration</th>
                        <th class="text-left px-4 py-3 font-medium">Error</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="reports.data.length === 0">
                        <td colspan="8" class="px-4 py-12 text-center text-slate-600">No report jobs found.</td>
                    </tr>
                    <tr v-for="report in reports.data" :key="report.id"
                        class="border-b border-white/5 last:border-0 hover:bg-white/[0.02] transition">
                        <td class="px-4 py-3 text-slate-300 font-mono text-xs">{{ report.type }}</td>
                        <td class="px-4 py-3">
                            <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', formatBadge(report.format).cls]">
                                {{ formatBadge(report.format).label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-400 text-xs">{{ report.user?.name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusBadge(report.status).cls]">
                                {{ statusBadge(report.status).label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDate(report.created_at) }}</td>
                        <td class="px-4 py-3 text-slate-500 text-xs">{{ duration(report) ?? '—' }}</td>
                        <td class="px-4 py-3 text-red-400 text-xs max-w-48 truncate" :title="report.error_message ?? ''">
                            {{ report.error_message ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a v-if="report.status === 'success' && report.file_path"
                               :href="`/tenant/reports/${report.id}/download`"
                               class="text-xs text-emerald-400 hover:text-emerald-300 transition font-medium">
                                Download
                            </a>
                            <button v-else-if="report.status === 'failed'"
                                    @click="retryReport(report.id)"
                                    class="text-xs text-amber-400 hover:text-amber-300 transition font-medium">
                                Retry
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="reports.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-slate-500">
            <span>Page {{ reports.current_page }} of {{ reports.last_page }}</span>
            <div class="flex gap-2">
                <button @click="goToPage(reports.prev_page_url)"
                        :disabled="!reports.prev_page_url"
                        class="px-3 py-1.5 rounded-lg border border-white/10 hover:bg-white/5 disabled:opacity-30 disabled:cursor-not-allowed transition text-slate-300">
                    Previous
                </button>
                <button @click="goToPage(reports.next_page_url)"
                        :disabled="!reports.next_page_url"
                        class="px-3 py-1.5 rounded-lg border border-white/10 hover:bg-white/5 disabled:opacity-30 disabled:cursor-not-allowed transition text-slate-300">
                    Next
                </button>
            </div>
        </div>

        <!-- Subscriptions panel -->
        <div id="tour-rq-subscriptions" class="mt-10">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-semibold text-white">Scheduled Subscriptions</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Recurring report jobs dispatched automatically on a schedule.</p>
                </div>
                <button @click="showSubscriptionModal = true"
                        class="px-3 py-1.5 rounded-lg border border-white/10 hover:bg-white/5 text-slate-300 text-sm transition">
                    + New Subscription
                </button>
            </div>

            <div class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/5 text-xs text-slate-500 uppercase tracking-wide">
                            <th class="text-left px-4 py-3 font-medium">Type</th>
                            <th class="text-left px-4 py-3 font-medium">Format</th>
                            <th class="text-left px-4 py-3 font-medium">Frequency</th>
                            <th class="text-left px-4 py-3 font-medium">Delivery</th>
                            <th class="text-left px-4 py-3 font-medium">Status</th>
                            <th class="text-left px-4 py-3 font-medium">Last Run</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!subscriptions || subscriptions.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-600">No subscriptions yet.</td>
                        </tr>
                        <tr v-for="sub in subscriptions" :key="sub.id"
                            class="border-b border-white/5 last:border-0 hover:bg-white/[0.02] transition">
                            <td class="px-4 py-3 text-slate-300 font-mono text-xs">{{ sub.type }}</td>
                            <td class="px-4 py-3">
                                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', formatBadge(sub.format).cls]">
                                    {{ formatBadge(sub.format).label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-400 text-xs capitalize">{{ frequencyLabel[sub.frequency] ?? sub.frequency }}</td>
                            <td class="px-4 py-3 text-slate-400 text-xs">{{ deliveryLabel[sub.delivery] ?? sub.delivery }}</td>
                            <td class="px-4 py-3">
                                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', sub.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700 text-slate-500']">
                                    {{ sub.is_active ? 'Active' : 'Paused' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDate(sub.last_dispatched_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button @click="toggleSubscription(sub.id)"
                                            class="text-xs text-slate-400 hover:text-white transition">
                                        {{ sub.is_active ? 'Pause' : 'Resume' }}
                                    </button>
                                    <button @click="deleteSubscription(sub.id)"
                                            class="text-xs text-red-400 hover:text-red-300 transition">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Generate Report Modal -->
    <Teleport to="body">
        <div v-if="showGenerateModal"
             class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50"
             @click.self="showGenerateModal = false">
            <div class="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-md p-6 shadow-xl">
                <h2 class="text-base font-semibold text-white mb-5">Generate Report</h2>

                <form @submit.prevent="submitQuickReport" class="space-y-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Report Type</label>
                        <select v-model="quickForm.type"
                                class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option v-for="rt in reportTypes" :key="rt.value" :value="rt.value">{{ rt.label }}</option>
                        </select>
                        <p v-if="quickForm.errors.type" class="mt-1 text-xs text-red-400">{{ quickForm.errors.type }}</p>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Format</label>
                        <div class="flex gap-2">
                            <label v-for="rf in reportFormats" :key="rf.value"
                                   :class="['flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg border text-sm cursor-pointer transition',
                                            quickForm.format === rf.value
                                                ? 'border-blue-500 bg-blue-500/10 text-blue-300'
                                                : 'border-white/10 text-slate-400 hover:border-white/20']">
                                <input type="radio" :value="rf.value" v-model="quickForm.format" class="sr-only" />
                                {{ rf.label }}
                            </label>
                        </div>
                        <p v-if="quickForm.errors.format" class="mt-1 text-xs text-red-400">{{ quickForm.errors.format }}</p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="showGenerateModal = false"
                                class="flex-1 px-4 py-2 rounded-lg border border-white/10 text-slate-400 text-sm hover:bg-white/5 transition">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="quickForm.processing"
                                class="flex-1 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium transition">
                            {{ quickForm.processing ? 'Dispatching…' : 'Dispatch Job' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- New Subscription Modal -->
    <Teleport to="body">
        <div v-if="showSubscriptionModal"
             class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50"
             @click.self="showSubscriptionModal = false">
            <div class="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-md p-6 shadow-xl">
                <h2 class="text-base font-semibold text-white mb-5">New Subscription</h2>

                <form @submit.prevent="submitSubscription" class="space-y-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Report Type</label>
                        <select v-model="subForm.type"
                                class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option v-for="rt in reportTypes" :key="rt.value" :value="rt.value">{{ rt.label }}</option>
                        </select>
                        <p v-if="subForm.errors.type" class="mt-1 text-xs text-red-400">{{ subForm.errors.type }}</p>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Format</label>
                        <div class="flex gap-2">
                            <label v-for="rf in reportFormats" :key="rf.value"
                                   :class="['flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg border text-sm cursor-pointer transition',
                                            subForm.format === rf.value
                                                ? 'border-blue-500 bg-blue-500/10 text-blue-300'
                                                : 'border-white/10 text-slate-400 hover:border-white/20']">
                                <input type="radio" :value="rf.value" v-model="subForm.format" class="sr-only" />
                                {{ rf.label }}
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Frequency</label>
                        <select v-model="subForm.frequency"
                                class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option v-for="f in frequencies" :key="f.value" :value="f.value">{{ f.label }}</option>
                        </select>
                        <p v-if="subForm.errors.frequency" class="mt-1 text-xs text-red-400">{{ subForm.errors.frequency }}</p>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Delivery</label>
                        <select v-model="subForm.delivery"
                                class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option v-for="d in deliveries" :key="d.value" :value="d.value">{{ d.label }}</option>
                        </select>
                    </div>

                    <div v-if="subForm.delivery === 'email' || subForm.delivery === 'email_and_s3'">
                        <label class="block text-xs text-slate-400 mb-1.5">Recipients <span class="text-slate-600">(comma-separated)</span></label>
                        <input v-model="subForm.recipients" type="text" placeholder="admin@example.com, ops@example.com"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        <p v-if="subForm.errors.recipients" class="mt-1 text-xs text-red-400">{{ subForm.errors.recipients }}</p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="showSubscriptionModal = false"
                                class="flex-1 px-4 py-2 rounded-lg border border-white/10 text-slate-400 text-sm hover:bg-white/5 transition">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="subForm.processing"
                                class="flex-1 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium transition">
                            {{ subForm.processing ? 'Saving…' : 'Create Subscription' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <TourButton @click="startTour" />
</template>
