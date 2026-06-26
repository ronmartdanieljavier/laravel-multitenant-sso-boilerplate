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
    pending:    { label: 'Pending',    cls: 'bg-amber-500/20 text-amber-300' },
    processing: { label: 'Processing', cls: 'bg-blue-500/20 text-blue-300 animate-pulse' },
    success:    { label: 'Done',       cls: 'bg-emerald-500/20 text-emerald-400' },
    failed:     { label: 'Failed',     cls: 'bg-red-500/20 text-red-400' },
};

function reportStatusBadge(status) {
    return reportStatusConfig[status] ?? { label: status, cls: 'bg-slate-700 text-slate-400' };
}

// ── Error severity badge ────────────────────────────────────────────────────

const errorSeverityConfig = {
    error:    { label: 'Error',    cls: 'bg-red-500/20 text-red-400' },
    warning:  { label: 'Warning',  cls: 'bg-amber-500/20 text-amber-300' },
    critical: { label: 'Critical', cls: 'bg-red-900/40 text-red-300 font-bold' },
};

function errorSeverityBadge(s) {
    return errorSeverityConfig[s] ?? { label: s, cls: 'bg-slate-700 text-slate-400' };
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

    <main class="flex-1 px-8 py-10 space-y-8">

        <!-- Welcome -->
        <div id="tour-welcome" class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">
                    {{ greeting() }}, {{ user?.name?.split(' ')[0] ?? 'there' }}
                </h1>
                <p class="text-slate-400 mt-1 text-sm">Here's what's happening in your tenant right now.</p>
            </div>
        </div>

        <!-- Stats row -->
        <div id="tour-stats" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <component
                :is="card.href ? 'a' : 'div'"
                v-for="card in statCards"
                :key="card.id"
                :id="card.id"
                :href="card.href ?? undefined"
                :class="[
                    'bg-slate-900 border rounded-xl p-5 transition',
                    card.alert
                        ? 'border-red-500/30 hover:border-red-500/50'
                        : 'border-white/5 hover:border-white/10',
                    card.href ? 'cursor-pointer' : '',
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
                <p class="text-2xl font-bold text-white tabular-nums">{{ card.value }}</p>
                <p class="text-xs text-slate-500 mt-0.5">{{ card.label }}</p>
                <p class="text-xs mt-2" :class="card.alert ? 'text-red-400' : 'text-slate-600'">{{ card.sub }}</p>
            </component>
        </div>

        <!-- Quick actions -->
        <div id="tour-quick-actions" class="flex flex-wrap gap-3">
            <Link
                href="/tenant/reports"
                class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium rounded-lg transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Queue Report
            </Link>
            <Link
                href="/documents"
                class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-white/10 text-slate-300 hover:text-white text-sm font-medium rounded-lg transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Browse Documents
            </Link>
            <Link
                href="/tenant/errors"
                class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-white/10 text-slate-300 hover:text-white text-sm font-medium rounded-lg transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Review Errors
            </Link>
        </div>

        <!-- Bottom grid: reports + errors | documents -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Recent Reports (spans 2 cols) -->
            <div id="tour-recent-reports" class="lg:col-span-2 bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                    <h2 class="font-semibold text-white text-sm">Recent Reports</h2>
                    <Link href="/tenant/reports" class="text-xs text-emerald-400 hover:text-emerald-300 transition">
                        View all →
                    </Link>
                </div>

                <div v-if="recentReports.length === 0" class="px-6 py-12 text-center text-slate-500 text-sm">
                    No reports yet. <Link href="/tenant/reports" class="text-emerald-400 hover:underline">Queue your first report.</Link>
                </div>

                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/5 text-xs text-slate-500 uppercase tracking-wide">
                            <th class="text-left px-6 py-3 font-medium">Type</th>
                            <th class="text-left px-6 py-3 font-medium">Format</th>
                            <th class="text-left px-6 py-3 font-medium">Status</th>
                            <th class="text-left px-6 py-3 font-medium">Queued</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr v-for="report in recentReports" :key="report.id" class="hover:bg-white/[0.02] transition">
                            <td class="px-6 py-3 text-white font-medium capitalize">{{ report.type }}</td>
                            <td class="px-6 py-3 text-slate-400 uppercase text-xs">{{ report.format }}</td>
                            <td class="px-6 py-3">
                                <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-medium', reportStatusBadge(report.status).cls]">
                                    {{ reportStatusBadge(report.status).label }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-xs text-slate-500">{{ formatDate(report.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Recent Documents (1 col) -->
            <div id="tour-recent-documents" class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                    <h2 class="font-semibold text-white text-sm">Recent Documents</h2>
                    <Link href="/documents" class="text-xs text-emerald-400 hover:text-emerald-300 transition">
                        View all →
                    </Link>
                </div>

                <div v-if="recentDocuments.length === 0" class="px-6 py-12 text-center text-slate-500 text-sm">
                    No documents yet.
                </div>

                <ul v-else class="divide-y divide-white/5">
                    <li v-for="doc in recentDocuments" :key="doc.id" class="flex items-center gap-3 px-6 py-3 hover:bg-white/[0.02] transition">
                        <div class="w-8 h-8 rounded-lg bg-slate-800 border border-white/10 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-white truncate">{{ doc.title }}</p>
                            <p class="text-xs text-slate-500">{{ formatBytes(doc.file_size) }} · {{ formatDate(doc.created_at) }}</p>
                        </div>
                        <a :href="`/documents/${doc.id}/download`" class="text-slate-500 hover:text-emerald-400 transition shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </a>
                    </li>
                </ul>
            </div>

        </div>

        <!-- Open Error Logs -->
        <div id="tour-recent-errors" class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="font-semibold text-white text-sm">Open Error Logs</h2>
                    <span v-if="stats.critical_errors > 0"
                          class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-red-900/40 text-red-300 font-bold">
                        {{ stats.critical_errors }} critical
                    </span>
                </div>
                <Link href="/tenant/errors" class="text-xs text-emerald-400 hover:text-emerald-300 transition">
                    View all →
                </Link>
            </div>

            <div v-if="recentErrors.length === 0" class="px-6 py-8 text-center text-slate-500 text-sm">
                No open errors — all clear.
            </div>

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/5 text-xs text-slate-500 uppercase tracking-wide">
                        <th class="text-left px-6 py-3 font-medium">Error Code</th>
                        <th class="text-left px-6 py-3 font-medium">Exception</th>
                        <th class="text-left px-6 py-3 font-medium">Message</th>
                        <th class="text-left px-6 py-3 font-medium">Severity</th>
                        <th class="text-left px-6 py-3 font-medium">Occurred</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <tr v-for="err in recentErrors" :key="err.id" class="hover:bg-white/[0.02] transition">
                        <td class="px-6 py-3">
                            <Link :href="`/tenant/errors/${err.id}`"
                                  class="font-mono text-xs text-emerald-400 hover:text-emerald-300 transition">
                                {{ err.error_code }}
                            </Link>
                        </td>
                        <td class="px-6 py-3 font-mono text-xs text-slate-300">{{ shortClass(err.exception_class) }}</td>
                        <td class="px-6 py-3 text-sm text-slate-300 max-w-xs">
                            <span class="truncate block" :title="err.message">{{ err.message }}</span>
                        </td>
                        <td class="px-6 py-3">
                            <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-medium', errorSeverityBadge(err.severity).cls]">
                                {{ errorSeverityBadge(err.severity).label }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-xs text-slate-500">{{ formatDate(err.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </main>

    <TourButton @click="startTour" />
</template>
