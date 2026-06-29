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
    pending:    { label: 'Pending',    cls: 'bg-amber-500/10 border-amber-500/20 text-amber-300' },
    processing: { label: 'Processing', cls: 'bg-blue-500/10 border-blue-500/20 text-blue-300 animate-pulse' },
    success:    { label: 'Done',       cls: 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' },
    failed:     { label: 'Failed',     cls: 'bg-red-500/10 border-red-500/20 text-red-300' },
};

function statusBadge(status) {
    return statusConfig[status] ?? { label: status, cls: 'bg-slate-700/50 border-white/10 text-slate-400' };
}

const formatConfig = {
    pdf:    { label: 'PDF',    cls: 'bg-red-500/10 border-red-500/20 text-red-300' },
    excel:  { label: 'Excel',  cls: 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' },
    screen: { label: 'Screen', cls: 'bg-blue-500/10 border-blue-500/20 text-blue-300' },
};

function formatBadge(format) {
    return formatConfig[format] ?? { label: format, cls: 'bg-slate-700/50 border-white/10 text-slate-400' };
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

    <div class="flex flex-col flex-1 bg-[#030712]">
        <header id="tour-rq-header" class="h-16 border-b border-white/[0.05] flex items-center px-8 shrink-0 bg-[#030712]">
            <h1 class="font-grotesk text-lg font-semibold text-white">Report Queue</h1>
            <p class="text-slate-500 text-xs ml-3 mt-0.5 hidden sm:block">auto-refreshes every 4s while jobs are active</p>
            <div class="ml-auto flex items-center gap-3">
                <div v-if="hasActiveJobs" class="flex items-center gap-2 text-xs text-blue-400 font-mono">
                    <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse inline-block"></span>
                    Live
                </div>
                <button id="tour-rq-generate"
                        @click="showGenerateModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white cursor-pointer transition-all duration-150"
                        style="background: linear-gradient(135deg, #10b981, #0d9488); box-shadow: 0 0 20px rgba(16,185,129,0.2)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Generate Report
                </button>
            </div>
        </header>

        <main class="flex-1 px-8 py-8 space-y-6">

            <!-- Flash -->
            <div v-if="flash.success"
                 class="px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ flash.success }}
            </div>

            <!-- Stat cards -->
            <div id="tour-rq-stats" class="grid grid-cols-4 gap-4">
                <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5 border-t-2 border-t-slate-700/40">
                    <div class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-1">Total</div>
                    <div class="text-3xl font-grotesk font-semibold text-white">{{ reports.total }}</div>
                </div>
                <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5 border-t-2 border-t-amber-500/40">
                    <div class="text-xs font-mono text-amber-400 uppercase tracking-wider mb-1">Pending</div>
                    <div class="text-3xl font-grotesk font-semibold text-white">{{ reports.data.filter(r => r.status === 'pending').length }}</div>
                </div>
                <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5 border-t-2 border-t-blue-500/40">
                    <div class="text-xs font-mono text-blue-400 uppercase tracking-wider mb-1">Processing</div>
                    <div class="text-3xl font-grotesk font-semibold text-white">{{ reports.data.filter(r => r.status === 'processing').length }}</div>
                </div>
                <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5 border-t-2 border-t-red-500/40">
                    <div class="text-xs font-mono text-red-400 uppercase tracking-wider mb-1">Failed</div>
                    <div class="text-3xl font-grotesk font-semibold text-white">{{ reports.data.filter(r => r.status === 'failed').length }}</div>
                </div>
            </div>

            <!-- Report jobs table -->
            <div id="tour-rq-table" class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/[0.06]">
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Type</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Format</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Requested by</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Queued</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Duration</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Error</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <tr v-if="reports.data.length === 0">
                            <td colspan="8" class="px-5 py-12 text-center text-slate-600 font-mono text-sm">No report jobs found.</td>
                        </tr>
                        <tr v-for="report in reports.data" :key="report.id"
                            class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-5 py-3.5 text-slate-400 font-mono text-xs">{{ report.type }}</td>
                            <td class="px-5 py-3.5">
                                <span :class="['inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full border', formatBadge(report.format).cls]">
                                    {{ formatBadge(report.format).label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-400 text-xs">{{ report.user?.name ?? '—' }}</td>
                            <td class="px-5 py-3.5">
                                <span :class="['inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full border', statusBadge(report.status).cls]">
                                    {{ statusBadge(report.status).label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-500 text-xs">{{ formatDate(report.created_at) }}</td>
                            <td class="px-5 py-3.5 text-slate-500 text-xs font-mono">{{ duration(report) ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-red-400 text-xs max-w-48 truncate font-mono" :title="report.error_message ?? ''">
                                {{ report.error_message ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a v-if="report.status === 'success' && report.file_path"
                                   :href="`/tenant/reports/${report.id}/download`"
                                   class="text-xs text-emerald-400 hover:text-emerald-300 transition font-medium font-mono">
                                    Download
                                </a>
                                <button v-else-if="report.status === 'failed'"
                                        @click="retryReport(report.id)"
                                        class="text-xs text-amber-400 hover:text-amber-300 transition font-medium font-mono cursor-pointer">
                                    Retry
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="reports.last_page > 1" class="flex items-center justify-between text-sm text-slate-500">
                <span class="font-mono text-xs">Page {{ reports.current_page }} of {{ reports.last_page }}</span>
                <div class="flex gap-2">
                    <button @click="goToPage(reports.prev_page_url)"
                            :disabled="!reports.prev_page_url"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer transition-all">
                        Previous
                    </button>
                    <button @click="goToPage(reports.next_page_url)"
                            :disabled="!reports.next_page_url"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer transition-all">
                        Next
                    </button>
                </div>
            </div>

            <!-- Subscriptions panel -->
            <div id="tour-rq-subscriptions" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-grotesk text-base font-semibold text-white">Scheduled Subscriptions</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Recurring report jobs dispatched automatically on a schedule.</p>
                    </div>
                    <button @click="showSubscriptionModal = true"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Subscription
                    </button>
                </div>

                <div class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-white/[0.06]">
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Type</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Format</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Frequency</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Delivery</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Last Run</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.04]">
                            <tr v-if="!subscriptions || subscriptions.length === 0">
                                <td colspan="7" class="px-5 py-10 text-center text-slate-600 font-mono text-sm">No subscriptions yet.</td>
                            </tr>
                            <tr v-for="sub in subscriptions" :key="sub.id"
                                class="hover:bg-white/[0.02] transition-colors">
                                <td class="px-5 py-3.5 text-slate-400 font-mono text-xs">{{ sub.type }}</td>
                                <td class="px-5 py-3.5">
                                    <span :class="['inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full border', formatBadge(sub.format).cls]">
                                        {{ formatBadge(sub.format).label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-slate-400 text-xs capitalize">{{ frequencyLabel[sub.frequency] ?? sub.frequency }}</td>
                                <td class="px-5 py-3.5 text-slate-400 text-xs">{{ deliveryLabel[sub.delivery] ?? sub.delivery }}</td>
                                <td class="px-5 py-3.5">
                                    <span :class="['inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full border', sub.is_active ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' : 'bg-slate-700/30 border-white/10 text-slate-500']">
                                        {{ sub.is_active ? 'Active' : 'Paused' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-slate-500 text-xs">{{ formatDate(sub.last_dispatched_at) }}</td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <button @click="toggleSubscription(sub.id)"
                                                class="text-xs text-slate-400 hover:text-white transition cursor-pointer font-mono">
                                            {{ sub.is_active ? 'Pause' : 'Resume' }}
                                        </button>
                                        <button @click="deleteSubscription(sub.id)"
                                                class="text-xs text-red-400 hover:text-red-300 transition cursor-pointer font-mono">
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
    </div>

    <!-- Generate Report Modal -->
    <Teleport to="body">
        <div v-if="showGenerateModal"
             class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50"
             @click.self="showGenerateModal = false">
            <div class="bg-[#0d1117] border border-white/[0.08] rounded-2xl w-full max-w-md p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="font-grotesk text-base font-semibold text-white">Generate Report</h2>
                    <button @click="showGenerateModal = false" class="text-slate-500 hover:text-white transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitQuickReport" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Report Type</label>
                        <select v-model="quickForm.type"
                                class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-transparent transition">
                            <option v-for="rt in reportTypes" :key="rt.value" :value="rt.value">{{ rt.label }}</option>
                        </select>
                        <p v-if="quickForm.errors.type" class="mt-1 text-xs text-red-400">{{ quickForm.errors.type }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Format</label>
                        <div class="flex gap-2">
                            <label v-for="rf in reportFormats" :key="rf.value"
                                   :class="['flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-xl border text-sm cursor-pointer transition',
                                            quickForm.format === rf.value
                                                ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300'
                                                : 'border-white/[0.08] text-slate-400 hover:border-white/20']">
                                <input type="radio" :value="rf.value" v-model="quickForm.format" class="sr-only" />
                                {{ rf.label }}
                            </label>
                        </div>
                        <p v-if="quickForm.errors.format" class="mt-1 text-xs text-red-400">{{ quickForm.errors.format }}</p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="showGenerateModal = false"
                                class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="quickForm.processing"
                                class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50 cursor-pointer transition-all duration-150"
                                style="background: linear-gradient(135deg, #10b981, #0d9488); box-shadow: 0 0 20px rgba(16,185,129,0.2)">
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
             class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50"
             @click.self="showSubscriptionModal = false">
            <div class="bg-[#0d1117] border border-white/[0.08] rounded-2xl w-full max-w-md p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="font-grotesk text-base font-semibold text-white">New Subscription</h2>
                    <button @click="showSubscriptionModal = false" class="text-slate-500 hover:text-white transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitSubscription" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Report Type</label>
                        <select v-model="subForm.type"
                                class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-transparent transition">
                            <option v-for="rt in reportTypes" :key="rt.value" :value="rt.value">{{ rt.label }}</option>
                        </select>
                        <p v-if="subForm.errors.type" class="mt-1 text-xs text-red-400">{{ subForm.errors.type }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Format</label>
                        <div class="flex gap-2">
                            <label v-for="rf in reportFormats" :key="rf.value"
                                   :class="['flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-xl border text-sm cursor-pointer transition',
                                            subForm.format === rf.value
                                                ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300'
                                                : 'border-white/[0.08] text-slate-400 hover:border-white/20']">
                                <input type="radio" :value="rf.value" v-model="subForm.format" class="sr-only" />
                                {{ rf.label }}
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Frequency</label>
                        <select v-model="subForm.frequency"
                                class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-transparent transition">
                            <option v-for="f in frequencies" :key="f.value" :value="f.value">{{ f.label }}</option>
                        </select>
                        <p v-if="subForm.errors.frequency" class="mt-1 text-xs text-red-400">{{ subForm.errors.frequency }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Delivery</label>
                        <select v-model="subForm.delivery"
                                class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-transparent transition">
                            <option v-for="d in deliveries" :key="d.value" :value="d.value">{{ d.label }}</option>
                        </select>
                    </div>

                    <div v-if="subForm.delivery === 'email' || subForm.delivery === 'email_and_s3'">
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Recipients <span class="text-slate-600 normal-case">(comma-separated)</span></label>
                        <input v-model="subForm.recipients" type="text" placeholder="admin@example.com, ops@example.com"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-transparent transition" />
                        <p v-if="subForm.errors.recipients" class="mt-1 text-xs text-red-400">{{ subForm.errors.recipients }}</p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="showSubscriptionModal = false"
                                class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="subForm.processing"
                                class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50 cursor-pointer transition-all duration-150"
                                style="background: linear-gradient(135deg, #10b981, #0d9488); box-shadow: 0 0 20px rgba(16,185,129,0.2)">
                            {{ subForm.processing ? 'Saving…' : 'Create Subscription' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <TourButton @click="startTour" />
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
