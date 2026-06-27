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
    error:    { label: 'Error',    cls: 'bg-red-500/10 border-red-500/20 text-red-300' },
    warning:  { label: 'Warning',  cls: 'bg-amber-500/10 border-amber-500/20 text-amber-300' },
    critical: { label: 'Critical', cls: 'bg-red-900/30 border-red-500/20 text-red-300' },
};

function badge(s) {
    return severityConfig[s] ?? { label: s, cls: 'bg-slate-700/50 border-white/10 text-slate-400' };
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

    <div class="flex flex-col flex-1 bg-[#030712]">
        <header class="h-16 border-b border-white/[0.05] flex items-center px-8 shrink-0 bg-[#030712]">
            <h1 class="font-grotesk text-lg font-semibold text-white">Error Logs</h1>
            <p class="text-slate-500 text-xs ml-3 hidden sm:block">Exceptions captured in your tenant's context</p>
        </header>

        <main class="flex-1 px-8 py-8 space-y-5">

            <div v-if="flash.success" class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 rounded-xl px-4 py-3 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ flash.success }}
            </div>

            <!-- Stats -->
            <div id="tour-el-stats" class="grid grid-cols-4 gap-4">
                <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5 border-t-2 border-t-slate-700/40">
                    <div class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-1">Total</div>
                    <div class="text-3xl font-grotesk font-semibold text-white">{{ stats.total }}</div>
                </div>
                <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5 border-t-2" :class="stats.unresolved > 0 ? 'border-t-amber-500/40' : 'border-t-slate-700/40'">
                    <div class="text-xs font-mono uppercase tracking-wider mb-1" :class="stats.unresolved > 0 ? 'text-amber-400' : 'text-slate-500'">Unresolved</div>
                    <div class="text-3xl font-grotesk font-semibold" :class="stats.unresolved > 0 ? 'text-amber-400' : 'text-white'">{{ stats.unresolved }}</div>
                </div>
                <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5 border-t-2" :class="stats.critical > 0 ? 'border-t-red-500/40' : 'border-t-slate-700/40'">
                    <div class="text-xs font-mono uppercase tracking-wider mb-1" :class="stats.critical > 0 ? 'text-red-400' : 'text-slate-500'">Critical</div>
                    <div class="text-3xl font-grotesk font-semibold" :class="stats.critical > 0 ? 'text-red-400' : 'text-white'">{{ stats.critical }}</div>
                </div>
                <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5 border-t-2 border-t-slate-700/40">
                    <div class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-1">Errors</div>
                    <div class="text-3xl font-grotesk font-semibold text-white">{{ stats.errors }}</div>
                </div>
            </div>

            <!-- Filters -->
            <div id="tour-el-filters" class="flex items-center gap-4">
                <select v-model="severity" @change="applyFilters"
                        class="bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-transparent transition">
                    <option value="">All severities</option>
                    <option value="critical">Critical</option>
                    <option value="error">Error</option>
                    <option value="warning">Warning</option>
                </select>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="unresolvedOnly" @change="applyFilters" class="accent-emerald-500 w-4 h-4" />
                    <span class="text-sm text-slate-400">Unresolved only</span>
                </label>
            </div>

            <!-- Empty -->
            <div v-if="logs.length === 0" class="bg-white/[0.02] border border-white/[0.06] rounded-2xl p-12 text-center">
                <svg class="w-10 h-10 text-slate-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-slate-500 text-sm">No error logs found.</p>
            </div>

            <!-- Table -->
            <div v-else id="tour-el-table" class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/[0.06]">
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Error Code</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Exception</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Message</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Severity</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Occurred</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <tr v-for="log in logs" :key="log.id"
                            :class="log.resolved ? 'opacity-50' : ''"
                            class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-5 py-3.5">
                                <Link :href="`/tenant/errors/${log.id}`"
                                      class="font-mono text-xs text-emerald-400 hover:text-emerald-300 transition">
                                    {{ log.error_code }}
                                </Link>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs text-slate-400">
                                {{ shortClass(log.exception_class) }}
                            </td>
                            <td class="px-5 py-3.5 text-sm text-slate-400 max-w-xs">
                                <span class="truncate block" :title="log.message">{{ log.message }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span :class="['inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full border', badge(log.severity).cls]">
                                    {{ badge(log.severity).label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span v-if="log.resolved"
                                      class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-300">
                                    Resolved
                                </span>
                                <span v-else
                                      class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-300">
                                    Open
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-500">{{ formatDate(log.created_at) }}</td>
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
