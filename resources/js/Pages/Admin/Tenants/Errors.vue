<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminTenantLayout from '../../../Layouts/AdminTenantLayout.vue';
import TourButton from '../../Partials/TourButton.vue';
import { useTour } from '../../../composables/useTour';

defineOptions({ layout: AdminTenantLayout });

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const props = defineProps({
    tenant: Object,
    logs: Array,
    filters: Object,
});

// ── Filters ───────────────────────────────────────────────────────────────────

const severity = ref(props.filters.severity ?? '');
const unresolvedOnly = ref(props.filters.unresolved ?? false);

function applyFilters() {
    router.get(`/admin/tenants/${props.tenant.id}/errors`, {
        ...(severity.value ? { severity: severity.value } : {}),
        ...(unresolvedOnly.value ? { unresolved: 1 } : {}),
    }, { preserveState: true, replace: true });
}

// ── Helpers ───────────────────────────────────────────────────────────────────

const severityConfig = {
    error: { label: 'Error', cls: 'bg-red-500/20 text-red-400' },
    warning: { label: 'Warning', cls: 'bg-amber-500/20 text-amber-300' },
    critical: { label: 'Critical', cls: 'bg-red-900/40 text-red-300 font-bold' },
};

function badge(s) {
    return severityConfig[s] ?? { label: s, cls: 'bg-slate-700 text-slate-400' };
}

function formatDate(val) {
    if (!val) { return '—'; }
    const d = new Date(val);
    return d.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function shortClass(cls) {
    const parts = cls.split('\\');
    return parts[parts.length - 1];
}

// ── Actions ───────────────────────────────────────────────────────────────────

function resolve(log) {
    router.patch(`/admin/tenants/${props.tenant.id}/errors/${log.id}/resolve`);
}

function unresolve(log) {
    router.patch(`/admin/tenants/${props.tenant.id}/errors/${log.id}/unresolve`);
}

function deleteLog(log) {
    if (!confirm(`Delete error log ${log.error_code}?`)) { return; }
    router.delete(`/admin/tenants/${props.tenant.id}/errors/${log.id}`);
}

// ── Stats ─────────────────────────────────────────────────────────────────────

const stats = computed(() => ({
    total: props.logs.length,
    unresolved: props.logs.filter(l => !l.resolved).length,
    critical: props.logs.filter(l => l.severity === 'critical').length,
    errors: props.logs.filter(l => l.severity === 'error').length,
}));

const { startTour } = useTour('admin-tenant-errors', [
    {
        element: '#tour-errors-stats',
        title: 'Error Summary',
        description: 'Quick stats showing the total, unresolved, critical, and error-level exceptions recorded for this tenant.',
    },
    {
        element: '#tour-errors-filters',
        title: 'Filter Errors',
        description: 'Filter the error log by severity (Critical, Error, Warning) or show only unresolved items to focus on what needs attention.',
        side: 'bottom',
    },
    {
        element: '#tour-errors-table',
        title: 'Error Log Table',
        description: 'Each row is a captured exception. Click the error code to see the full stack trace. Use the Resolve / Unresolve actions to track your investigation progress.',
        side: 'top',
    },
]);
</script>


<template>
    <Head :title="`${tenant.name} — Error Logs`" />

    <main class="p-8">
        <div v-if="flash.success" class="mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl px-4 py-3 text-sm">
            {{ flash.success }}
        </div>

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-slate-500 mb-6 font-mono">
            <Link href="/admin/tenants" class="hover:text-slate-300 transition">Tenants</Link>
            <span class="text-slate-700">/</span>
            <span class="text-slate-300">{{ tenant.name }}</span>
            <span class="text-slate-700">/</span>
            <span class="text-slate-400">Errors</span>
        </div>

        <!-- Header -->
        <div class="flex items-start justify-between mb-6">
            <div>
                <h1 class="font-grotesk text-2xl font-bold text-white">{{ tenant.name }} — Error Logs</h1>
                <p class="text-sm text-slate-400 mt-1">All exceptions recorded in this tenant's request context.</p>
            </div>
            <div class="flex gap-2">
                <Link :href="`/admin/tenants/${tenant.id}/settings`"
                      class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                    Settings
                </Link>
                <Link :href="`/admin/tenants/${tenant.id}/users`"
                      class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                    Users
                </Link>
            </div>
        </div>

        <!-- Stats -->
        <div id="tour-errors-stats" class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5">
                <p class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-2">Total</p>
                <p class="text-3xl font-semibold font-grotesk text-white">{{ stats.total }}</p>
            </div>
            <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5">
                <p class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-2">Unresolved</p>
                <p class="text-3xl font-semibold font-grotesk" :class="stats.unresolved > 0 ? 'text-amber-400' : 'text-white'">{{ stats.unresolved }}</p>
            </div>
            <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5">
                <p class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-2">Critical</p>
                <p class="text-3xl font-semibold font-grotesk" :class="stats.critical > 0 ? 'text-red-400' : 'text-white'">{{ stats.critical }}</p>
            </div>
            <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5">
                <p class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-2">Errors</p>
                <p class="text-3xl font-semibold font-grotesk text-white">{{ stats.errors }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div id="tour-errors-filters" class="flex items-center gap-4 mb-5">
            <select v-model="severity" @change="applyFilters"
                    class="bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition">
                <option value="">All severities</option>
                <option value="critical">Critical</option>
                <option value="error">Error</option>
                <option value="warning">Warning</option>
            </select>
            <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" v-model="unresolvedOnly" @change="applyFilters" class="accent-blue-500 w-4 h-4" />
                <span class="text-sm text-slate-300">Unresolved only</span>
            </label>
        </div>

        <!-- Empty state -->
        <div v-if="logs.length === 0" class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-12 text-center">
            <p class="text-slate-400">No error logs found.</p>
        </div>

        <!-- Table -->
        <div v-else id="tour-errors-table" class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/[0.06]">
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Error Code</th>
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Exception</th>
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Message</th>
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Severity</th>
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Occurred</th>
                        <th class="px-5 py-3 text-right text-xs font-mono text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/[0.04]">
                    <tr v-for="log in logs" :key="log.id"
                        :class="log.resolved ? 'opacity-50' : ''"
                        class="hover:bg-white/[0.02] transition-colors">
                        <td class="px-5 py-3.5">
                            <Link :href="`/admin/tenants/${tenant.id}/errors/${log.id}`"
                                  class="font-mono text-xs text-blue-400 hover:text-blue-300 transition">
                                {{ log.error_code }}
                            </Link>
                        </td>
                        <td class="px-5 py-3.5 text-xs text-slate-300 font-mono">
                            {{ shortClass(log.exception_class) }}
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-300 max-w-xs">
                            <span class="truncate block" :title="log.message">{{ log.message }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span :class="badge(log.severity).cls"
                                  class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full border">
                                {{ badge(log.severity).label }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span v-if="log.resolved"
                                  class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-300">
                                Resolved
                            </span>
                            <span v-else
                                  class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-300">
                                Open
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-xs text-slate-500 font-mono">{{ formatDate(log.created_at) }}</td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <Link :href="`/admin/tenants/${tenant.id}/errors/${log.id}`"
                                      class="text-xs text-slate-400 hover:text-slate-200 transition">
                                    Detail
                                </Link>
                                <button v-if="!log.resolved" @click="resolve(log)"
                                        class="text-xs text-emerald-400 hover:text-emerald-300 transition cursor-pointer">
                                    Resolve
                                </button>
                                <button v-else @click="unresolve(log)"
                                        class="text-xs text-amber-400 hover:text-amber-300 transition cursor-pointer">
                                    Reopen
                                </button>
                                <button @click="deleteLog(log)"
                                        class="text-xs text-red-400 hover:text-red-300 transition cursor-pointer">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

    <TourButton @click="startTour" />
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
