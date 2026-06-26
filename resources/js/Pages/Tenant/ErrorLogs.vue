<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TenantLayout from '../../Layouts/TenantLayout.vue';
import TourButton from '../Partials/TourButton.vue';
import { useTour } from '../../composables/useTour';

defineOptions({ layout: TenantLayout });

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const props = defineProps({
    logs: Array,
    filters: Object,
});

// ── Filters ───────────────────────────────────────────────────────────────────

const severity = ref(props.filters.severity ?? '');
const unresolvedOnly = ref(props.filters.unresolved ?? false);

function applyFilters() {
    router.get('/tenant/errors', {
        ...(severity.value ? { severity: severity.value } : {}),
        ...(unresolvedOnly.value ? { unresolved: 1 } : {}),
    }, { preserveState: true, replace: true });
}

// ── Helpers ───────────────────────────────────────────────────────────────────

const severityConfig = {
    error:    { label: 'Error',    cls: 'bg-red-500/20 text-red-400' },
    warning:  { label: 'Warning',  cls: 'bg-amber-500/20 text-amber-300' },
    critical: { label: 'Critical', cls: 'bg-red-900/40 text-red-300 font-bold' },
};

function badge(s) {
    return severityConfig[s] ?? { label: s, cls: 'bg-slate-700 text-slate-400' };
}

function formatDate(val) {
    if (!val) { return '—'; }
    return new Date(val).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function shortClass(cls) {
    const parts = cls.split('\\');
    return parts[parts.length - 1];
}

// ── Stats ─────────────────────────────────────────────────────────────────────

const stats = computed(() => ({
    total: props.logs.length,
    unresolved: props.logs.filter(l => !l.resolved).length,
    critical: props.logs.filter(l => l.severity === 'critical').length,
    errors: props.logs.filter(l => l.severity === 'error').length,
}));

const { startTour } = useTour('tenant-error-logs', [
    {
        element: '#tour-el-stats',
        title: 'Error Summary',
        description: 'Quick counts of total, unresolved, critical, and error-level exceptions recorded for your tenant.',
    },
    {
        element: '#tour-el-filters',
        title: 'Filter Errors',
        description: 'Narrow the list by severity or show only open (unresolved) items.',
        side: 'bottom',
    },
    {
        element: '#tour-el-table',
        title: 'Error Log',
        description: 'Each row is a captured exception. Click the error code to view full details. Quote the code when contacting support.',
        side: 'top',
    },
]);
</script>

<template>
    <Head title="Error Logs" />

    <main class="flex-1 px-8 py-10 max-w-5xl">
        <div v-if="flash.success" class="mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg px-4 py-3 text-sm">
            {{ flash.success }}
        </div>

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-xl font-semibold text-white">Error Logs</h1>
            <p class="text-sm text-slate-500 mt-0.5">Exceptions captured in your tenant's request context. Quote the error code when contacting support.</p>
        </div>

        <!-- Stats -->
        <div id="tour-el-stats" class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-slate-900 border border-white/5 rounded-xl p-4">
                <p class="text-xs text-slate-500 mb-1">Total</p>
                <p class="text-2xl font-semibold text-white">{{ stats.total }}</p>
            </div>
            <div class="bg-slate-900 border border-white/5 rounded-xl p-4">
                <p class="text-xs text-slate-500 mb-1">Unresolved</p>
                <p class="text-2xl font-semibold" :class="stats.unresolved > 0 ? 'text-amber-400' : 'text-white'">{{ stats.unresolved }}</p>
            </div>
            <div class="bg-slate-900 border border-white/5 rounded-xl p-4">
                <p class="text-xs text-slate-500 mb-1">Critical</p>
                <p class="text-2xl font-semibold" :class="stats.critical > 0 ? 'text-red-400' : 'text-white'">{{ stats.critical }}</p>
            </div>
            <div class="bg-slate-900 border border-white/5 rounded-xl p-4">
                <p class="text-xs text-slate-500 mb-1">Errors</p>
                <p class="text-2xl font-semibold text-white">{{ stats.errors }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div id="tour-el-filters" class="flex items-center gap-4 mb-4">
            <select v-model="severity" @change="applyFilters"
                    class="bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500">
                <option value="">All severities</option>
                <option value="critical">Critical</option>
                <option value="error">Error</option>
                <option value="warning">Warning</option>
            </select>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="unresolvedOnly" @change="applyFilters" class="accent-emerald-500" />
                <span class="text-sm text-slate-300">Unresolved only</span>
            </label>
        </div>

        <!-- Empty -->
        <div v-if="logs.length === 0" class="bg-slate-900 border border-white/5 rounded-xl p-12 text-center">
            <p class="text-slate-400">No error logs found.</p>
        </div>

        <!-- Table -->
        <div v-else id="tour-el-table" class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/5 text-xs text-slate-500 uppercase tracking-wide">
                        <th class="text-left px-4 py-3 font-medium">Error Code</th>
                        <th class="text-left px-4 py-3 font-medium">Exception</th>
                        <th class="text-left px-4 py-3 font-medium">Message</th>
                        <th class="text-left px-4 py-3 font-medium">Severity</th>
                        <th class="text-left px-4 py-3 font-medium">Status</th>
                        <th class="text-left px-4 py-3 font-medium">Occurred</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <tr v-for="log in logs" :key="log.id"
                        :class="log.resolved ? 'opacity-60' : ''"
                        class="hover:bg-white/[0.02] transition">
                        <td class="px-4 py-3">
                            <Link :href="`/tenant/errors/${log.id}`"
                                  class="font-mono text-xs text-emerald-400 hover:text-emerald-300 transition">
                                {{ log.error_code }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-300">
                            {{ shortClass(log.exception_class) }}
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-300 max-w-xs">
                            <span class="truncate block" :title="log.message">{{ log.message }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="badge(log.severity).cls"
                                  class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                                {{ badge(log.severity).label }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="log.resolved"
                                  class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-emerald-500/20 text-emerald-400">
                                Resolved
                            </span>
                            <span v-else
                                  class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-amber-500/20 text-amber-300">
                                Open
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ formatDate(log.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

    <TourButton @click="startTour" />
</template>
