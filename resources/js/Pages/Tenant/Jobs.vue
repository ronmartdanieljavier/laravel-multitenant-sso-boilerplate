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

    <div class="flex-1 px-8 py-10">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-xl font-semibold text-slate-100">Job Queue</h1>
                <p class="text-sm text-slate-400 mt-0.5">Background tasks running on your account</p>
            </div>
            <span
                v-if="hasActiveJobs"
                class="inline-flex items-center gap-1.5 text-xs text-blue-300 bg-blue-500/10 border border-blue-500/20 px-3 py-1.5 rounded-full"
            >
                <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse" />
                Jobs running
            </span>
        </div>

        <!-- Status filter tabs -->
        <div class="flex gap-1 mb-6">
            <button
                v-for="tab in filterTabs"
                :key="tab.label"
                @click="filterByStatus(tab.value)"
                :class="[
                    'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                    filters.status === tab.value
                        ? 'bg-slate-700 text-slate-100'
                        : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800',
                ]"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Table -->
        <div class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/5">
                        <th class="text-left text-xs font-medium text-slate-400 px-5 py-3">Job</th>
                        <th class="text-left text-xs font-medium text-slate-400 px-5 py-3">Status</th>
                        <th class="text-left text-xs font-medium text-slate-400 px-5 py-3">Queued</th>
                        <th class="text-left text-xs font-medium text-slate-400 px-5 py-3">Started</th>
                        <th class="text-left text-xs font-medium text-slate-400 px-5 py-3">Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="jobs.data.length === 0">
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-slate-500">
                                No jobs found.
                            </td>
                        </tr>
                    </template>
                    <template v-else>
                        <template v-for="job in jobs.data" :key="job.id">
                            <tr class="border-b border-white/5 last:border-0 hover:bg-white/[0.02] transition-colors">
                                <td class="px-5 py-3.5">
                                    <span class="text-slate-200 font-medium">{{ job.display_name }}</span>
                                    <span class="ml-2 text-slate-600 text-xs">{{ job.job_class.split('\\').pop() }}</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span
                                        :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(job.status).cls]"
                                    >
                                        {{ statusBadge(job.status).label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-slate-400">{{ formatDate(job.created_at) }}</td>
                                <td class="px-5 py-3.5 text-slate-400">{{ formatDate(job.started_at) }}</td>
                                <td class="px-5 py-3.5 text-slate-400">{{ formatDuration(job.duration_seconds) }}</td>
                            </tr>
                            <!-- Error row -->
                            <tr v-if="job.status === 'failed' && job.error_message" class="border-b border-white/5 last:border-0 bg-red-500/5">
                                <td colspan="5" class="px-5 pb-3.5">
                                    <details class="group">
                                        <summary class="text-xs text-red-400 cursor-pointer hover:text-red-300 select-none">
                                            View error
                                        </summary>
                                        <pre class="mt-2 text-xs text-red-300 bg-red-500/10 border border-red-500/20 rounded-lg p-3 overflow-x-auto whitespace-pre-wrap">{{ job.error_message }}</pre>
                                    </details>
                                </td>
                            </tr>
                        </template>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="jobs.links && jobs.links.length > 3" class="flex items-center justify-between mt-4">
            <p class="text-xs text-slate-500">
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
                        'px-3 py-1.5 rounded text-xs font-medium transition-colors',
                        link.active
                            ? 'bg-emerald-600 text-white'
                            : link.url
                                ? 'text-slate-400 hover:text-slate-200 hover:bg-slate-800'
                                : 'text-slate-600 cursor-default',
                    ]"
                />
            </div>
        </div>
    </div>
</template>
