<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import TenantLayout from '../../Layouts/TenantLayout.vue';
import TourButton from '../Partials/TourButton.vue';
import { useTour } from '../../composables/useTour';

defineOptions({ layout: TenantLayout });

const page = usePage();
const user = computed(() => page.props.auth?.user);

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    recentReports: {
        type: Array,
        default: () => [],
    },
    recentErrors: {
        type: Array,
        default: () => [],
    },
    recentDocuments: {
        type: Array,
        default: () => [],
    },
});

function greeting() {
    const h = new Date().getHours();
    if (h < 12) { return 'Good morning'; }
    if (h < 18) { return 'Good afternoon'; }
    return 'Good evening';
}

// ── Formatters ─────────────────────────────────────────────────────────────

function formatDate(val) {
    if (!val) { return '—'; }
    return new Date(val).toLocaleString('en-US', {
        month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function formatBytes(bytes) {
    if (!bytes) { return '—'; }
    if (bytes < 1024) { return bytes + ' B'; }
    if (bytes < 1_048_576) { return (bytes / 1024).toFixed(1) + ' KB'; }
    return (bytes / 1_048_576).toFixed(1) + ' MB';
}

function shortClass(cls) {
    if (!cls) { return '—'; }
    const parts = cls.split('\\');
    return parts[parts.length - 1];
}

// ── Report status badge ─────────────────────────────────────────────────────

const reportStatusConfig = {
    pending:    { label: 'Pending',    cls: 'bg-amber-500/10 border-amber-500/20 text-amber-300' },
    processing: { label: 'Processing', cls: 'bg-blue-500/10 border-blue-500/20 text-blue-300 animate-pulse' },
    success:    { label: 'Done',       cls: 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' },
    failed:     { label: 'Failed',     cls: 'bg-red-500/10 border-red-500/20 text-red-300' },
};

function reportStatusBadge(status) {
    return reportStatusConfig[status] ?? { label: status, cls: 'bg-slate-700/50 border-white/10 text-slate-400' };
}

// ── Error severity badge ────────────────────────────────────────────────────

const errorSeverityConfig = {
    error:    { label: 'Error',    cls: 'bg-red-500/10 border-red-500/20 text-red-300' },
    warning:  { label: 'Warning',  cls: 'bg-amber-500/10 border-amber-500/20 text-amber-300' },
    critical: { label: 'Critical', cls: 'bg-red-900/30 border-red-500/20 text-red-300' },
};

function errorSeverityBadge(s) {
    return errorSeverityConfig[s] ?? { label: s, cls: 'bg-slate-700/50 border-white/10 text-slate-400' };
}

// ── Stats cards ─────────────────────────────────────────────────────────────

const statCards = computed(() => [
    {
        id: 'stat-reports-active',
        label: 'Active Reports',
        value: (props.stats.pending_reports ?? 0) + (props.stats.processing_reports ?? 0),
        sub: `${props.stats.success_reports_this_month ?? 0} completed this month`,
        icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        iconColor: 'text-blue-400',
        iconBg: 'bg-blue-500/10',
        topBorder: 'border-t-blue-500/40',
        alert: false,
    },
    {
        id: 'stat-reports-failed',
        label: 'Failed Reports',
        value: props.stats.failed_reports ?? 0,
        sub: props.stats.failed_reports > 0 ? 'Retry from Report Queue' : 'All clear',
        href: props.stats.failed_reports > 0 ? '/tenant/reports' : null,
        icon: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        iconColor: props.stats.failed_reports > 0 ? 'text-red-400' : 'text-slate-400',
        iconBg: props.stats.failed_reports > 0 ? 'bg-red-500/10' : 'bg-slate-700/50',
        topBorder: props.stats.failed_reports > 0 ? 'border-t-red-500/40' : 'border-t-slate-700/40',
        alert: props.stats.failed_reports > 0,
    },
    {
        id: 'stat-errors',
        label: 'Open Errors',
        value: props.stats.unresolved_errors ?? 0,
        sub: props.stats.critical_errors > 0
            ? `${props.stats.critical_errors} critical`
            : 'No critical issues',
        href: props.stats.unresolved_errors > 0 ? '/tenant/errors' : null,
        icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        iconColor: props.stats.unresolved_errors > 0 ? 'text-amber-400' : 'text-slate-400',
        iconBg: props.stats.unresolved_errors > 0 ? 'bg-amber-500/10' : 'bg-slate-700/50',
        topBorder: props.stats.unresolved_errors > 0 ? 'border-t-amber-500/40' : 'border-t-slate-700/40',
        alert: props.stats.critical_errors > 0,
    },
    {
        id: 'stat-documents',
        label: 'Documents',
        value: props.stats.total_documents ?? 0,
        sub: 'View library',
        href: '/documents',
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        iconColor: 'text-emerald-400',
        iconBg: 'bg-emerald-500/10',
        topBorder: 'border-t-emerald-500/40',
        alert: false,
    },
]);

// ── Tour ───────────────────────────────────────────────────────────────────

const { startTour } = useTour('tenant-dashboard', [
    {
        element: '#tour-welcome',
        title: 'Your Dashboard',
        description: 'An at-a-glance view of your tenant\'s activity — reports, errors, and documents all in one place.',
    },
    {
        element: '#tour-stats',
        title: 'Stats at a Glance',
        description: 'Live counts for active reports, failures, open errors, and stored documents. Cards with issues are highlighted — click them to jump straight to the relevant page.',
        side: 'bottom',
    },
    {
        element: '#tour-quick-actions',
        title: 'Quick Actions',
        description: 'Shortcuts to the most common tasks: queue a report, browse documents, or review error logs.',
        side: 'bottom',
    },
    {
        element: '#tour-recent-reports',
        title: 'Recent Reports',
        description: 'The five most recently queued reports for your tenant. Click "View all" to see the full queue.',
        side: 'top',
    },
    {
        element: '#tour-recent-errors',
        title: 'Open Error Logs',
        description: 'Unresolved exceptions captured in your tenant\'s context. Quote the error code when contacting support.',
        side: 'top',
    },
    {
        element: '#tour-recent-documents',
        title: 'Recent Documents',
        description: 'The five most recent files in your document library — uploads, generated reports, and subscription deliveries.',
        side: 'top',
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col flex-1 bg-[#030712]">
        <header class="h-16 border-b border-white/[0.05] flex items-center px-8 shrink-0 bg-[#030712]">
            <div id="tour-welcome">
                <h1 class="font-grotesk text-lg font-semibold text-white">
                    {{ greeting() }}, {{ user?.name?.split(' ')[0] ?? 'there' }}
                </h1>
                <p class="text-slate-500 text-xs mt-0.5">Here's what's happening in your tenant right now.</p>
            </div>
            <div id="tour-quick-actions" class="ml-auto flex items-center gap-3">
                <Link
                    href="/tenant/reports"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white cursor-pointer transition-all duration-150"
                    style="background: linear-gradient(135deg, #10b981, #0d9488); box-shadow: 0 0 20px rgba(16,185,129,0.2)"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Queue Report
                </Link>
                <Link
                    href="/documents"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Documents
                </Link>
                <Link
                    href="/tenant/errors"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Errors
                </Link>
            </div>
        </header>

        <main class="flex-1 px-8 py-8 space-y-6">

            <!-- Stats row -->
            <div id="tour-stats" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <component
                    :is="card.href ? 'a' : 'div'"
                    v-for="card in statCards"
                    :key="card.id"
                    :id="card.id"
                    :href="card.href ?? undefined"
                    :class="[
                        'bg-white/[0.03] backdrop-blur-sm border border-white/[0.06] rounded-2xl p-5 transition border-t-2',
                        card.topBorder,
                        card.href ? 'cursor-pointer hover:bg-white/[0.05]' : '',
                    ]"
                >
                    <div class="flex items-start justify-between mb-3">
                        <div :class="['w-9 h-9 rounded-lg flex items-center justify-center', card.iconBg]">
                            <svg :class="['w-4 h-4', card.iconColor]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="card.icon" />
                            </svg>
                        </div>
                        <span v-if="card.alert" class="w-2 h-2 rounded-full bg-red-500 mt-1 animate-pulse" />
                    </div>
                    <div class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-1">{{ card.label }}</div>
                    <div class="text-3xl font-grotesk font-semibold text-white tabular-nums">{{ card.value }}</div>
                    <div class="text-xs mt-1" :class="card.alert ? 'text-red-400' : 'text-slate-500'">{{ card.sub }}</div>
                </component>
            </div>

            <!-- Bottom grid: reports + errors | documents -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                <!-- Recent Reports (spans 2 cols) -->
                <div id="tour-recent-reports" class="lg:col-span-2 bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-white/[0.06] flex items-center justify-between">
                        <h2 class="font-grotesk font-semibold text-white text-sm">Recent Reports</h2>
                        <Link href="/tenant/reports" class="text-xs text-emerald-400 hover:text-emerald-300 transition font-mono">
                            View all →
                        </Link>
                    </div>

                    <div v-if="recentReports.length === 0" class="px-5 py-12 text-center text-slate-500 text-sm">
                        No reports yet.
                        <Link href="/tenant/reports" class="text-emerald-400 hover:underline ml-1">Queue your first report.</Link>
                    </div>

                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-white/[0.06]">
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Type</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Format</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Queued</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.04]">
                            <tr v-for="report in recentReports" :key="report.id" class="hover:bg-white/[0.02] transition-colors">
                                <td class="px-5 py-3.5 text-slate-300 font-medium capitalize">{{ report.type }}</td>
                                <td class="px-5 py-3.5 text-slate-500 uppercase text-xs font-mono">{{ report.format }}</td>
                                <td class="px-5 py-3.5">
                                    <span :class="['inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full border', reportStatusBadge(report.status).cls]">
                                        {{ reportStatusBadge(report.status).label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-xs text-slate-500">{{ formatDate(report.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Recent Documents (1 col) -->
                <div id="tour-recent-documents" class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-white/[0.06] flex items-center justify-between">
                        <h2 class="font-grotesk font-semibold text-white text-sm">Recent Documents</h2>
                        <Link href="/documents" class="text-xs text-emerald-400 hover:text-emerald-300 transition font-mono">
                            View all →
                        </Link>
                    </div>

                    <div v-if="recentDocuments.length === 0" class="px-5 py-12 text-center text-slate-500 text-sm">
                        No documents yet.
                    </div>

                    <ul v-else class="divide-y divide-white/[0.04]">
                        <li v-for="doc in recentDocuments" :key="doc.id" class="flex items-center gap-3 px-5 py-3 hover:bg-white/[0.02] transition-colors">
                            <div class="w-8 h-8 rounded-lg bg-white/[0.04] border border-white/[0.06] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-slate-300 truncate">{{ doc.title }}</p>
                                <p class="text-xs text-slate-600">{{ formatBytes(doc.file_size) }} · {{ formatDate(doc.created_at) }}</p>
                            </div>
                            <a :href="`/documents/${doc.id}/download`" class="text-slate-600 hover:text-emerald-400 transition shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                            </a>
                        </li>
                    </ul>
                </div>

            </div>

            <!-- Open Error Logs -->
            <div id="tour-recent-errors" class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-white/[0.06] flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h2 class="font-grotesk font-semibold text-white text-sm">Open Error Logs</h2>
                        <span v-if="stats.critical_errors > 0"
                              class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-red-500/10 border border-red-500/20 text-red-300">
                            {{ stats.critical_errors }} critical
                        </span>
                    </div>
                    <Link href="/tenant/errors" class="text-xs text-emerald-400 hover:text-emerald-300 transition font-mono">
                        View all →
                    </Link>
                </div>

                <div v-if="recentErrors.length === 0" class="px-5 py-8 text-center text-slate-500 text-sm">
                    No open errors — all clear.
                </div>

                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/[0.06]">
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Error Code</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Exception</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Message</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Severity</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Occurred</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <tr v-for="err in recentErrors" :key="err.id" class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-5 py-3.5">
                                <Link :href="`/tenant/errors/${err.id}`"
                                      class="font-mono text-xs text-emerald-400 hover:text-emerald-300 transition">
                                    {{ err.error_code }}
                                </Link>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs text-slate-400">{{ shortClass(err.exception_class) }}</td>
                            <td class="px-5 py-3.5 text-sm text-slate-400 max-w-xs">
                                <span class="truncate block" :title="err.message">{{ err.message }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span :class="['inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full border', errorSeverityBadge(err.severity).cls]">
                                    {{ errorSeverityBadge(err.severity).label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-500">{{ formatDate(err.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <TourButton @click="startTour" />
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
