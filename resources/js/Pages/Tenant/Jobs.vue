<script setup>
import { Head, router, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';
import TenantLayout from '../../Layouts/TenantLayout.vue';

defineOptions({ layout: TenantLayout });

const props = defineProps({
    jobs: Object,
    filters: Object,
});

const statusConfig = {
    pending:   { label: 'Pending',   cls: 'bg-amber-500/10 border-amber-500/20 text-amber-300' },
    running:   { label: 'Running',   cls: 'bg-blue-500/10 border-blue-500/20 text-blue-300 animate-pulse' },
    completed: { label: 'Done',      cls: 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' },
    failed:    { label: 'Failed',    cls: 'bg-red-500/10 border-red-500/20 text-red-300' },
};

function statusBadge(status) {
    return statusConfig[status] ?? { label: status, cls: 'bg-slate-700/50 border-white/10 text-slate-400' };
}

const hasActiveJobs = computed(() =>
    props.jobs.data.some(j => j.status === 'pending' || j.status === 'running')
);

usePoll(4000, { only: ['jobs'] }, { autoStart: true });

function formatDate(val) {
    if (!val) { return '—'; }
    return new Date(val).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatDuration(seconds) {
    if (seconds === null || seconds === undefined) { return '—'; }
    if (seconds < 60) { return `${seconds}s`; }
    return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
}

const filterTabs = [
    { label: 'All', value: null },
    { label: 'Pending', value: 'pending' },
    { label: 'Running', value: 'running' },
    { label: 'Done', value: 'completed' },
    { label: 'Failed', value: 'failed' },
];

function filterByStatus(status) {
    router.get('/tenant/jobs', status ? { status } : {}, { preserveState: true, replace: true });
}

function goToPage(url) {
    if (url) { router.visit(url, { preserveScroll: true }); }
}
</script>

<template>
    <Head title="Job Queue" />

    <div class="flex flex-col flex-1 bg-[#030712]">
        <header class="h-16 border-b border-white/[0.05] flex items-center px-8 shrink-0 bg-[#030712]">
            <h1 class="font-grotesk text-lg font-semibold text-white">Job Queue</h1>
            <p class="text-slate-500 text-xs ml-3 hidden sm:block">Background tasks running on your account</p>
            <div class="ml-auto">
                <span
                    v-if="hasActiveJobs"
                    class="inline-flex items-center gap-1.5 text-xs text-blue-300 bg-blue-500/10 border border-blue-500/20 px-3 py-1.5 rounded-full font-mono"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse" />
                    Jobs running
                </span>
            </div>
        </header>

        <main class="flex-1 px-8 py-8 space-y-5">

            <!-- Status filter tabs -->
            <div class="flex gap-1">
                <button
                    v-for="tab in filterTabs"
                    :key="tab.label"
                    @click="filterByStatus(tab.value)"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors cursor-pointer font-mono',
                        filters.status === tab.value
                            ? 'bg-white/[0.08] text-white border border-white/[0.12]'
                            : 'text-slate-500 hover:text-slate-300 hover:bg-white/[0.04]',
                    ]"
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
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Started</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="jobs.data.length === 0">
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-slate-600 font-mono text-sm">
                                    No jobs found.
                                </td>
                            </tr>
                        </template>
                        <template v-else>
                            <template v-for="job in jobs.data" :key="job.id">
                                <tr class="border-b border-white/[0.04] last:border-0 hover:bg-white/[0.02] transition-colors">
                                    <td class="px-5 py-3.5">
                                        <span class="text-slate-200 font-medium">{{ job.display_name }}</span>
                                        <span class="ml-2 text-slate-600 text-xs font-mono">{{ job.job_class.split('\\').pop() }}</span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span
                                            :class="['inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full border', statusBadge(job.status).cls]"
                                        >
                                            {{ statusBadge(job.status).label }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-400 text-xs">{{ formatDate(job.created_at) }}</td>
                                    <td class="px-5 py-3.5 text-slate-400 text-xs">{{ formatDate(job.started_at) }}</td>
                                    <td class="px-5 py-3.5 text-slate-400 text-xs font-mono">{{ formatDuration(job.duration_seconds) }}</td>
                                </tr>
                                <!-- Error row -->
                                <tr v-if="job.status === 'failed' && job.error_message" class="border-b border-white/[0.04] last:border-0 bg-red-500/[0.03]">
                                    <td colspan="5" class="px-5 pb-3.5">
                                        <details class="group">
                                            <summary class="text-xs text-red-400 cursor-pointer hover:text-red-300 select-none font-mono">
                                                View error
                                            </summary>
                                            <pre class="mt-2 text-xs text-red-300 bg-black/40 border border-white/[0.06] rounded-xl p-4 overflow-auto max-h-64 font-mono whitespace-pre-wrap">{{ job.error_message }}</pre>
                                        </details>
                                    </td>
                                </tr>
                            </template>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="jobs.links && jobs.links.length > 3" class="flex items-center justify-between">
                <p class="text-xs text-slate-500 font-mono">
                    Showing {{ jobs.meta.from }}–{{ jobs.meta.to }} of {{ jobs.meta.total }} jobs
                </p>
                <div class="flex gap-1">
                    <button
                        v-for="link in jobs.links"
                        :key="link.label"
                        @click="goToPage(link.url)"
                        :disabled="!link.url"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-mono font-medium transition-colors cursor-pointer',
                            link.active
                                ? 'text-white border border-emerald-500/30'
                                : link.url
                                    ? 'text-slate-400 hover:text-slate-200 hover:bg-white/[0.04]'
                                    : 'text-slate-600 cursor-default',
                        ]"
                        :style="link.active ? 'background: linear-gradient(135deg, #10b981, #0d9488)' : ''"
                    />
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
