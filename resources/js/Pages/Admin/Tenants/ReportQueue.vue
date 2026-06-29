<script setup>
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminTenantLayout from '../../../Layouts/AdminTenantLayout.vue';
import TourButton from '../../Partials/TourButton.vue';
import { useTour } from '../../../composables/useTour';

defineOptions({ layout: AdminTenantLayout });

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const props = defineProps({
    tenant: Object,
    reports: Object,
});

const hasActiveJobs = computed(() =>
    props.reports.data.some(r => r.status === 'pending' || r.status === 'processing')
);

usePoll(4000, { only: ['reports'] });

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
    return new Date(val).toLocaleString('en-US', {
        month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function duration(report) {
    if (!report.started_at || !report.completed_at) { return null; }
    const ms = new Date(report.completed_at) - new Date(report.started_at);
    return ms < 1000 ? `${ms}ms` : `${(ms / 1000).toFixed(1)}s`;
}

function goToPage(url) {
    if (url) { router.visit(url, { preserveScroll: true }); }
}

const { startTour } = useTour('admin-tenant-report-queue', [
    {
        element: '#tour-admin-rq-stats',
        title: 'Report Queue Summary',
        description: 'Overview of report job counts for this tenant: total jobs, currently pending, actively processing, and those that have failed.',
    },
    {
        element: '#tour-admin-rq-table',
        title: 'Report Jobs',
        description: 'Each row is a queued report job showing its type, output format, who requested it, current processing status, and any error message on failure.',
        side: 'top',
    },
]);
</script>


<template>
    <Head :title="`${tenant.name} — Report Queue`" />

    <main class="p-8 max-w-6xl">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-slate-500 mb-6 font-mono">
            <Link href="/admin/tenants" class="hover:text-slate-300 transition">Tenants</Link>
            <span class="text-slate-700">/</span>
            <Link :href="`/admin/tenants/${tenant.id}/reports`" class="hover:text-slate-300 transition">{{ tenant.name }}</Link>
            <span class="text-slate-700">/</span>
            <span class="text-slate-300">Report Queue</span>
        </div>

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="font-grotesk text-xl font-semibold text-white">{{ tenant.name }} — Report Queue</h1>
                <p class="text-sm text-slate-500 mt-0.5">All report jobs for this tenant · auto-refreshes every 4 s while jobs are active.</p>
            </div>
            <div class="flex items-center gap-3">
                <span v-if="hasActiveJobs" class="inline-flex items-center gap-1.5 text-xs font-mono text-blue-300 bg-blue-500/10 border border-blue-500/20 px-3 py-1.5 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse inline-block"></span>
                    Live
                </span>
                <Link :href="`/admin/tenants/${tenant.id}/errors`"
                      class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                    ← Error Logs
                </Link>
            </div>
        </div>

        <!-- Flash -->
        <div v-if="flash.success"
             class="mb-6 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
            {{ flash.success }}
        </div>

        <!-- Stat cards -->
        <div id="tour-admin-rq-stats" class="grid grid-cols-4 gap-4 mb-8">
            <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5">
                <p class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-2">Total</p>
                <p class="text-3xl font-semibold font-grotesk text-white">{{ reports.total }}</p>
            </div>
            <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5">
                <p class="text-xs font-mono text-amber-400 uppercase tracking-wider mb-2">Pending</p>
                <p class="text-3xl font-semibold font-grotesk text-white">{{ reports.data.filter(r => r.status === 'pending').length }}</p>
            </div>
            <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5">
                <p class="text-xs font-mono text-blue-400 uppercase tracking-wider mb-2">Processing</p>
                <p class="text-3xl font-semibold font-grotesk text-white">{{ reports.data.filter(r => r.status === 'processing').length }}</p>
            </div>
            <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5">
                <p class="text-xs font-mono text-red-400 uppercase tracking-wider mb-2">Failed</p>
                <p class="text-3xl font-semibold font-grotesk text-white">{{ reports.data.filter(r => r.status === 'failed').length }}</p>
            </div>
        </div>

        <!-- Table -->
        <div id="tour-admin-rq-table" class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
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
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/[0.04]">
                    <tr v-if="reports.data.length === 0">
                        <td colspan="7" class="px-5 py-12 text-center text-slate-600 font-mono text-sm">No report jobs found for this tenant.</td>
                    </tr>
                    <tr v-for="report in reports.data" :key="report.id"
                        class="hover:bg-white/[0.02] transition-colors">
                        <td class="px-5 py-3.5 text-slate-300 font-mono text-xs">{{ report.type }}</td>
                        <td class="px-5 py-3.5">
                            <span :class="['text-xs font-mono px-2.5 py-1 rounded-full border', formatBadge(report.format).cls]">
                                {{ formatBadge(report.format).label }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-slate-400 text-xs">{{ report.user?.name ?? '—' }}</td>
                        <td class="px-5 py-3.5">
                            <span :class="['text-xs font-mono px-2.5 py-1 rounded-full border', statusBadge(report.status).cls]">
                                {{ statusBadge(report.status).label }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-slate-500 text-xs font-mono">{{ formatDate(report.created_at) }}</td>
                        <td class="px-5 py-3.5 text-slate-500 text-xs font-mono">{{ duration(report) ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-red-400 text-xs font-mono max-w-xs truncate" :title="report.error_message ?? ''">
                            {{ report.error_message ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="reports.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-slate-500">
            <span class="font-mono text-xs">Page {{ reports.current_page }} of {{ reports.last_page }}</span>
            <div class="flex gap-2">
                <button @click="goToPage(reports.prev_page_url)"
                        :disabled="!reports.prev_page_url"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all disabled:opacity-30 disabled:cursor-not-allowed">
                    Previous
                </button>
                <button @click="goToPage(reports.next_page_url)"
                        :disabled="!reports.next_page_url"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all disabled:opacity-30 disabled:cursor-not-allowed">
                    Next
                </button>
            </div>
        </div>
    </main>

    <TourButton @click="startTour" />
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
