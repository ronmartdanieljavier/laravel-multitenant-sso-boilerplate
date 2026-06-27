<script setup>
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    jobs: Object,
    tenants: Array,
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

function applyFilters(patch) {
    const current = {
        tenant_id: props.filters.tenant_id ?? null,
        status: props.filters.status ?? null,
    };
    const merged = { ...current, ...patch };
    const query = Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== null && v !== '')
    );
    router.get('/admin/jobs', query, { preserveState: true, replace: true });
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
    <Head title="Job Queue" />

    <div class="flex flex-col flex-1">
        <!-- Header -->
        <header class="h-16 border-b border-white/[0.05] flex items-center px-8 shrink-0 bg-[#030712]">
            <h1 class="font-grotesk text-lg font-semibold text-white">Job Queue</h1>
            <p class="ml-4 text-sm text-slate-500">Background tasks across all tenants · auto-refreshes every 4s while active.</p>
            <div class="ml-auto">
                <span
                    v-if="hasActiveJobs"
                    class="inline-flex items-center gap-1.5 text-xs text-blue-300 bg-blue-500/10 border border-blue-500/20 px-3 py-1.5 rounded-full"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse" />
                    Live
                </span>
            </div>
        </header>

        <main class="p-8 overflow-y-auto">
            <!-- Filters row -->
            <div class="flex items-center gap-3 mb-6">
                <!-- Tenant dropdown -->
                <select
                    :value="filters.tenant_id ?? ''"
                    @change="applyFilters({ tenant_id: $event.target.value || null })"
                    class="text-xs bg-white/[0.04] border border-white/[0.08] rounded-xl px-3 py-2 text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition"
                >
                    <option value="">All tenants</option>
                    <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>

                <!-- Status filter tabs -->
                <div class="flex gap-1 bg-white/[0.03] border border-white/[0.06] rounded-xl p-1">
                    <button
                        v-for="tab in filterTabs"
                        :key="tab.label"
                        :class="[
                            'text-xs px-3 py-1.5 rounded-lg font-medium transition',
                            filters.status === tab.value
                                ? 'bg-blue-600 text-white shadow'
                                : 'text-slate-400 hover:text-slate-200',
                        ]"
                        @click="applyFilters({ status: tab.value })"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/[0.06]">
                            <th class="text-left px-5 py-3 text-xs font-mono text-slate-500 uppercase tracking-wider">Tenant</th>
                            <th class="text-left px-5 py-3 text-xs font-mono text-slate-500 uppercase tracking-wider">Job</th>
                            <th class="text-left px-5 py-3 text-xs font-mono text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="text-left px-5 py-3 text-xs font-mono text-slate-500 uppercase tracking-wider">Queued</th>
                            <th class="text-left px-5 py-3 text-xs font-mono text-slate-500 uppercase tracking-wider">Duration</th>
                            <th class="text-left px-5 py-3 text-xs font-mono text-slate-500 uppercase tracking-wider">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <tr v-if="jobs.data.length === 0">
                            <td colspan="6" class="px-5 py-12 text-center text-slate-600">No jobs found.</td>
                        </tr>
                        <tr
                            v-for="job in jobs.data"
                            :key="job.id"
                            class="hover:bg-white/[0.02] transition-colors"
                        >
                            <td class="px-5 py-3.5 text-slate-400 text-xs font-mono">{{ job.tenant_name }}</td>
                            <td class="px-5 py-3.5">
                                <p class="text-slate-200 text-xs font-medium">{{ job.display_name }}</p>
                                <p class="text-slate-600 text-xs font-mono mt-0.5">{{ job.job_class }}</p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span :class="['text-xs px-2.5 py-1 rounded-full font-mono font-medium', statusBadge(job.status).cls]">
                                    {{ statusBadge(job.status).label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-500 text-xs">{{ formatDate(job.created_at) }}</td>
                            <td class="px-5 py-3.5 text-slate-500 text-xs font-mono">{{ formatDuration(job.duration_seconds) }}</td>
                            <td class="px-5 py-3.5 text-red-400 text-xs max-w-xs truncate" :title="job.error_message ?? ''">
                                {{ job.error_message ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="jobs.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-slate-500">
                <span>Page {{ jobs.current_page }} of {{ jobs.last_page }}</span>
                <div class="flex gap-2">
                    <button
                        @click="goToPage(jobs.prev_page_url)"
                        :disabled="!jobs.prev_page_url"
                        class="px-3.5 py-1.5 rounded-lg border border-white/[0.08] bg-white/[0.03] hover:bg-white/[0.06] disabled:opacity-30 disabled:cursor-not-allowed transition text-slate-300 text-xs font-medium"
                    >
                        Previous
                    </button>
                    <button
                        @click="goToPage(jobs.next_page_url)"
                        :disabled="!jobs.next_page_url"
                        class="px-3.5 py-1.5 rounded-lg border border-white/[0.08] bg-white/[0.03] hover:bg-white/[0.06] disabled:opacity-30 disabled:cursor-not-allowed transition text-slate-300 text-xs font-medium"
                    >
                        Next
                    </button>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
