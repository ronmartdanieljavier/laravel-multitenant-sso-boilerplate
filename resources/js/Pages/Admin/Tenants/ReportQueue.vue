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
    screen: { label: 'Screen', cls: 'bg-violet-500/20 text-violet-300' },
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
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
                <Link href="/admin/tenants" class="hover:text-slate-300 transition">Tenants</Link>
                <span>/</span>
                <Link :href="`/admin/tenants/${tenant.id}/reports`" class="hover:text-slate-300 transition">{{ tenant.name }}</Link>
                <span>/</span>
                <span class="text-slate-300">Report Queue</span>
            </div>

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-semibold text-white">{{ tenant.name }} — Report Queue</h1>
                    <p class="text-sm text-slate-500 mt-0.5">All report jobs for this tenant · auto-refreshes every 4 s while jobs are active.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div v-if="hasActiveJobs" class="flex items-center gap-2 text-xs text-blue-400">
                        <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse inline-block"></span>
                        Live
                    </div>
                    <Link :href="`/admin/tenants/${tenant.id}/errors`"
                          class="text-xs px-3 py-1.5 rounded-lg bg-slate-800 border border-white/10 text-slate-400 hover:text-white transition">
                        ← Error Logs
                    </Link>
                </div>
            </div>

            <!-- Flash -->
            <div v-if="flash.success"
                 class="mb-6 px-4 py-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
                {{ flash.success }}
            </div>

            <!-- Stat cards -->
            <div id="tour-admin-rq-stats" class="grid grid-cols-4 gap-4 mb-8">
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

            <!-- Table -->
            <div id="tour-admin-rq-table" class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
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
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="reports.data.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-slate-600">No report jobs found for this tenant.</td>
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
                            <td class="px-4 py-3 text-red-400 text-xs max-w-xs truncate" :title="report.error_message ?? ''">
                                {{ report.error_message ?? '—' }}
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
    </main>

    <TourButton @click="startTour" />
</template>
