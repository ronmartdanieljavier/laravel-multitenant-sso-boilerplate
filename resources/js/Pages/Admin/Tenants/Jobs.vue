<script setup>
import { Head, router, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AdminTenantLayout from '../../../Layouts/AdminTenantLayout.vue';

defineOptions({ layout: AdminTenantLayout });

const props = defineProps({
    tenant: Object,
    jobs: Object,
    filters: Object,
});

const statusConfig = {
    pending:   { label: 'Pending',   cls: 'bg-amber-500/20 text-amber-300' },
    running:   { label: 'Running',   cls: 'bg-blue-500/20 text-blue-300 animate-pulse' },
    completed: { label: 'Done',      cls: 'bg-emerald-500/20 text-emerald-400' },
    failed:    { label: 'Failed',    cls: 'bg-red-500/20 text-red-400' },
};

function statusBadge(status) {
    return statusConfig[status] ?? { label: status, cls: 'bg-slate-700 text-slate-400' };
}

const hasActiveJobs = computed(() =>
    props.jobs.data.some(j => j.status === 'pending' || j.status === 'running')
);

const { start: startPoll, stop: stopPoll } = usePoll(4000, { only: ['jobs'] }, { autoStart: false });

watch(hasActiveJobs, (active) => {
    if (active) { startPoll(); } else { stopPoll(); }
}, { immediate: true });

const filterTabs = [
    { label: 'All',     value: null        },
    { label: 'Pending', value: 'pending'   },
    { label: 'Running', value: 'running'   },
    { label: 'Done',    value: 'completed' },
    { label: 'Failed',  value: 'failed'    },
];

function filterByStatus(status) {
    router.get(
        `/admin/tenants/${props.tenant.id}/jobs`,
        status ? { status } : {},
        { preserveState: true, replace: true },
    );
}

function formatDate(val) {
    if (!val) { return '—'; }
    return new Date(val).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatDuration(seconds) {
    if (seconds === null || seconds === undefined) { return '—'; }
    if (seconds < 60) { return `${seconds}s`; }
    return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
}

function goToPage(url) {
    if (url) { router.visit(url, { preserveScroll: true }); }
}
</script>


<template>
    <Head :title="`${tenant.name} — Job Queue`" />

    <main class="p-8 max-w-6xl">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-slate-500 mb-6 font-mono">
            <a href="/admin/tenants" class="hover:text-slate-300 transition">Tenants</a>
            <span class="text-slate-700">/</span>
            <span class="text-slate-300">{{ tenant.name }}</span>
            <span class="text-slate-700">/</span>
            <span class="text-slate-300">Job Queue</span>
        </div>

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="font-grotesk text-xl font-semibold text-white">{{ tenant.name }} — Job Queue</h1>
                <p class="text-sm text-slate-500 mt-0.5">Background tasks for this tenant · auto-refreshes every 4s while active.</p>
            </div>
            <span v-if="hasActiveJobs"
                  class="inline-flex items-center gap-1.5 text-xs font-mono text-blue-300 bg-blue-500/10 border border-blue-500/20 px-3 py-1.5 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse" />
                Live
            </span>
        </div>

        <!-- Status filter tabs -->
        <div class="flex gap-1 mb-6">
            <button
                v-for="tab in filterTabs"
                :key="tab.label"
                :class="[
                    'text-xs px-3 py-1.5 rounded-lg font-mono font-medium transition cursor-pointer',
                    filters.status === tab.value
                        ? 'bg-blue-600 text-white'
                        : 'text-slate-400 hover:text-white hover:bg-white/[0.05]',
                ]"
                @click="filterByStatus(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/[0.06]">
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Job</th>
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Queued</th>
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Duration</th>
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/[0.04]">
                    <tr v-if="jobs.data.length === 0">
                        <td colspan="5" class="px-5 py-12 text-center text-slate-600 font-mono text-sm">No jobs found.</td>
                    </tr>
                    <tr
                        v-for="job in jobs.data"
                        :key="job.id"
                        class="hover:bg-white/[0.02] transition-colors"
                    >
                        <td class="px-5 py-3.5">
                            <p class="text-slate-200 text-xs font-medium">{{ job.display_name }}</p>
                            <p class="text-slate-600 text-xs font-mono mt-0.5">{{ job.job_class }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <span :class="['text-xs font-mono px-2.5 py-1 rounded-full border', statusBadge(job.status).cls]">
                                {{ statusBadge(job.status).label }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-slate-500 text-xs font-mono">{{ formatDate(job.created_at) }}</td>
                        <td class="px-5 py-3.5 text-slate-500 text-xs font-mono">{{ formatDuration(job.duration_seconds) }}</td>
                        <td class="px-5 py-3.5 text-red-400 text-xs font-mono max-w-xs truncate" :title="job.error_message ?? ''">
                            {{ job.error_message ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="jobs.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-slate-500">
            <span class="font-mono text-xs">Page {{ jobs.current_page }} of {{ jobs.last_page }}</span>
            <div class="flex gap-2">
                <button
                    @click="goToPage(jobs.prev_page_url)"
                    :disabled="!jobs.prev_page_url"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                >
                    Previous
                </button>
                <button
                    @click="goToPage(jobs.next_page_url)"
                    :disabled="!jobs.next_page_url"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                >
                    Next
                </button>
            </div>
        </div>
    </main>
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
