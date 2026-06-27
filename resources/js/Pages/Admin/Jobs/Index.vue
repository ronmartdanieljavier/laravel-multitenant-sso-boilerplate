<script setup>
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

function logout() {
    router.post('/logout');
}

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

usePoll(4000, { only: ['jobs'] }, { autoStart: true });

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

    <div class="min-h-screen bg-slate-950 text-slate-100 flex">
        <!-- Sidebar -->
        <aside class="fixed inset-y-0 left-0 w-60 bg-slate-900 border-r border-white/5 flex flex-col">
            <div class="h-16 flex items-center px-6 border-b border-white/5 shrink-0">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center mr-3 shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <span class="font-semibold text-white">SSO Admin</span>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <Link href="/admin" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">Dashboard</Link>
                <Link href="/admin/users" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">Users</Link>
                <Link href="/admin/apps" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">Apps</Link>
                <Link href="/admin/tenants" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">Tenants</Link>
                <Link href="/admin/jobs" class="flex items-center gap-3 py-2 text-sm font-medium transition border-l-2 border-blue-500 rounded-r-lg pl-[10px] pr-3 text-white">Jobs</Link>
                <Link href="/admin/settings" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">Settings</Link>
            </nav>
            <div class="p-3 border-t border-white/5 shrink-0 space-y-1">
                <Link href="/apps" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-white hover:bg-white/5 transition">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    App Selection
                </Link>
                <button @click="logout" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-white hover:bg-white/5 transition">
                    Sign out
                </button>
            </div>
        </aside>

        <!-- Content -->
        <div class="ml-60 flex-1 p-8">
            <div class="max-w-6xl">
                <!-- Header -->
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-xl font-semibold text-white">Job Queue</h1>
                        <p class="text-sm text-slate-500 mt-0.5">Background tasks across all tenants · auto-refreshes every 4s while active.</p>
                    </div>
                    <span
                        v-if="hasActiveJobs"
                        class="inline-flex items-center gap-1.5 text-xs text-blue-300 bg-blue-500/10 border border-blue-500/20 px-3 py-1.5 rounded-full"
                    >
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse" />
                        Live
                    </span>
                </div>

                <!-- Filters row -->
                <div class="flex items-center gap-3 mb-6">
                    <!-- Tenant dropdown -->
                    <select
                        :value="filters.tenant_id ?? ''"
                        @change="applyFilters({ tenant_id: $event.target.value || null })"
                        class="text-xs bg-slate-900 border border-white/10 rounded-lg px-3 py-1.5 text-slate-300 focus:outline-none focus:border-blue-500"
                    >
                        <option value="">All tenants</option>
                        <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>

                    <!-- Status tabs -->
                    <div class="flex gap-1">
                        <button
                            v-for="tab in filterTabs"
                            :key="tab.label"
                            :class="[
                                'text-xs px-3 py-1.5 rounded-lg font-medium transition',
                                filters.status === tab.value
                                    ? 'bg-blue-600 text-white'
                                    : 'text-slate-400 hover:text-white hover:bg-white/5',
                            ]"
                            @click="applyFilters({ status: tab.value })"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-white/5 text-xs text-slate-500 uppercase tracking-wide">
                                <th class="text-left px-4 py-3 font-medium">Tenant</th>
                                <th class="text-left px-4 py-3 font-medium">Job</th>
                                <th class="text-left px-4 py-3 font-medium">Status</th>
                                <th class="text-left px-4 py-3 font-medium">Queued</th>
                                <th class="text-left px-4 py-3 font-medium">Duration</th>
                                <th class="text-left px-4 py-3 font-medium">Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="jobs.data.length === 0">
                                <td colspan="6" class="px-4 py-12 text-center text-slate-600">No jobs found.</td>
                            </tr>
                            <tr
                                v-for="job in jobs.data"
                                :key="job.id"
                                class="border-b border-white/5 last:border-0 hover:bg-white/[0.02] transition"
                            >
                                <td class="px-4 py-3 text-slate-400 text-xs">{{ job.tenant_name }}</td>
                                <td class="px-4 py-3">
                                    <p class="text-slate-200 text-xs font-medium">{{ job.display_name }}</p>
                                    <p class="text-slate-600 text-xs font-mono mt-0.5">{{ job.job_class }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusBadge(job.status).cls]">
                                        {{ statusBadge(job.status).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDate(job.created_at) }}</td>
                                <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDuration(job.duration_seconds) }}</td>
                                <td class="px-4 py-3 text-red-400 text-xs max-w-xs truncate" :title="job.error_message ?? ''">
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
                            class="px-3 py-1.5 rounded-lg border border-white/10 hover:bg-white/5 disabled:opacity-30 disabled:cursor-not-allowed transition text-slate-300"
                        >
                            Previous
                        </button>
                        <button
                            @click="goToPage(jobs.next_page_url)"
                            :disabled="!jobs.next_page_url"
                            class="px-3 py-1.5 rounded-lg border border-white/10 hover:bg-white/5 disabled:opacity-30 disabled:cursor-not-allowed transition text-slate-300"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
