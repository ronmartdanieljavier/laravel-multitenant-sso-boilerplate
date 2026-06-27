<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useIdleTimeout } from '../../composables/useIdleTimeout';
import TourButton from '../Partials/TourButton.vue';
import { useTour } from '../../composables/useTour';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    healthSummary: {
        type: Object,
        required: true,
    },
    recentUsers: {
        type: Array,
        required: true,
    },
    pendingUsers: {
        type: Array,
        required: true,
    },
    unresolvedErrors: {
        type: Object,
        required: true,
    },
    reportQueue: {
        type: Object,
        required: true,
    },
    migrationCompliance: {
        type: Object,
        required: true,
    },
});

const errorsOpen = ref(props.unresolvedErrors.total > 0);
const reportQueueOpen = ref(
    props.reportQueue.pending > 0 || props.reportQueue.processing > 0 || props.reportQueue.failed > 0,
);
const migrationOpen = ref(props.migrationCompliance.behind_count > 0);
const migrating = ref(null);

function runMigration(tenant) {
    migrating.value = tenant.id;
    router.post(`/admin/tenants/${tenant.id}/migrate`, {}, {
        onFinish: () => { migrating.value = null; },
    });
}

const pendingOpen = ref(props.pendingUsers.length > 0);
const resending = ref(null);

function resendInvitation(user) {
    resending.value = user.id;
    router.post(`/admin/users/${user.id}/resend-invitation`, {}, {
        onFinish: () => { resending.value = null; },
    });
}

function formatSentAt(value) {
    if (!value) return '—';
    const d = new Date(value);
    const diff = Math.floor((Date.now() - d) / 86400000);
    if (diff === 0) return 'Today';
    if (diff === 1) return 'Yesterday';
    return `${diff}d ago`;
}

const page = usePage();
useIdleTimeout(page.props.idleTimeoutMinutes);

function logout() {
    router.post('/logout');
}

const missingSettings = page.props.missingRequiredSettings ?? [];

const adminDashboardSteps = [
    {
        element: '#tour-admin-stats',
        title: 'System Overview',
        description: 'Key system metrics at a glance: total users, active tenants, report jobs in the queue, and unresolved errors across all tenants.',
    },
    {
        element: '#tour-admin-health',
        title: 'Tenant Health',
        description: 'A breakdown of all tenants by health status. Click a status badge to jump to the filtered tenant list. Tenants in Warning or Critical state need attention.',
        side: 'top',
    },
    ...(props.pendingUsers.length > 0 ? [{
        element: '#tour-admin-pending',
        title: 'Pending Invitations',
        description: 'Users who have been invited but haven\'t accepted yet. You can resend their invitation email from here.',
        side: 'top',
    }] : []),
    {
        element: '#tour-admin-errors',
        title: 'Unresolved Errors',
        description: 'Recent unresolved exceptions across all tenants. Click an error code to see the full stack trace and mark it as resolved.',
        side: 'top',
    },
];

const { startTour } = useTour('admin-dashboard', adminDashboardSteps);

const statCards = [
    {
        label: 'Total Users',
        value: props.stats.total_users,
        sub: `${props.stats.active_users} active · ${props.stats.pending_invitation_users} pending`,
    },
    {
        label: 'Active Apps',
        value: props.stats.active_apps,
        sub: null,
    },
    {
        label: 'Active Tenants',
        value: props.stats.active_tenants,
        sub: null,
    },
    {
        label: 'SSO Sessions',
        value: props.stats.active_sso_sessions,
        sub: 'live Sanctum tokens',
    },
];
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="flex flex-col flex-1">
        <!-- Header -->
        <header class="h-16 border-b border-white/[0.05] flex items-center px-8 shrink-0 bg-[#030712]">
            <h1 class="font-grotesk text-lg font-semibold text-white">Dashboard</h1>
            <div class="ml-auto flex items-center gap-3">
                <Link href="/admin/settings"
                      class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                    Settings
                </Link>
                <Link href="/admin/tenants?add=1"
                      class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                    + Add Tenant
                </Link>
                <Link href="/admin/users?invite=1"
                      class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white cursor-pointer transition-all duration-150"
                      style="background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 0 20px rgba(99,102,241,0.25)">
                    + Invite User
                </Link>
            </div>
        </header>

        <main class="p-8 space-y-6 overflow-y-auto">
            <!-- Missing settings banner -->
            <div v-if="missingSettings.length > 0"
                 class="flex items-center gap-3 bg-amber-500/[0.08] border border-amber-500/20 rounded-xl px-4 py-3 text-amber-300 text-sm">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                <span>Missing required settings: <span class="font-medium">{{ missingSettings.join(', ') }}</span></span>
                <Link href="/admin/settings" class="ml-auto text-xs underline underline-offset-2 hover:text-amber-200 transition">Configure →</Link>
            </div>

            <!-- Stat cards -->
            <div id="tour-admin-stats" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div v-for="card in statCards" :key="card.label"
                     class="bg-white/[0.03] backdrop-blur-sm border border-white/[0.06] rounded-2xl p-5 border-t-2"
                     :class="card.label === 'Total Users' && stats.pending_invitation_users > 0
                         ? 'border-t-amber-500/40'
                         : 'border-t-blue-500/40'">
                    <div class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-1">{{ card.label }}</div>
                    <div class="text-3xl font-grotesk font-semibold text-white">{{ card.value.toLocaleString() }}</div>
                    <div v-if="card.sub"
                         class="text-xs mt-1"
                         :class="card.label === 'Total Users' && stats.pending_invitation_users > 0
                             ? 'text-amber-400'
                             : 'text-slate-500'">
                        {{ card.sub }}
                    </div>
                </div>
            </div>

            <!-- Pending Invitations -->
            <div v-if="pendingUsers.length > 0" id="tour-admin-pending"
                 class="bg-white/[0.02] border border-amber-500/20 rounded-2xl overflow-hidden">
                <button @click="pendingOpen = !pendingOpen"
                        class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse inline-block" />
                        <h3 class="font-grotesk font-semibold text-amber-300">Pending Invitations</h3>
                        <span class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-300">
                            {{ pendingUsers.length }}
                        </span>
                    </div>
                    <svg class="w-4 h-4 text-slate-500 transition-transform" :class="pendingOpen ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div v-if="pendingOpen" class="border-t border-white/[0.06]">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-white/[0.06]">
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Name</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Email</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Invited</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.04]">
                            <tr v-for="user in pendingUsers" :key="user.id" class="hover:bg-white/[0.02] transition-colors">
                                <td class="px-5 py-3.5 text-slate-200 font-medium">{{ user.name }}</td>
                                <td class="px-5 py-3.5 text-slate-400">{{ user.email }}</td>
                                <td class="px-5 py-3.5 text-slate-500 text-xs font-mono">{{ formatSentAt(user.invitation_sent_at) }}</td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <button @click="resendInvitation(user)"
                                                :disabled="resending === user.id"
                                                class="text-xs font-medium text-amber-400 hover:text-amber-300 disabled:opacity-50 disabled:cursor-not-allowed transition">
                                            {{ resending === user.id ? 'Sending…' : 'Resend' }}
                                        </button>
                                        <Link :href="`/admin/users?edit=${user.id}`"
                                              class="text-xs text-blue-400 hover:text-blue-300 transition">
                                            Edit
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tenant Health Summary -->
            <div id="tour-admin-health" class="bg-white/[0.03] backdrop-blur-sm border border-white/[0.06] rounded-2xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-grotesk text-base font-semibold text-white">Tenant Health</h2>
                    <Link href="/admin/tenants" class="text-xs text-slate-500 hover:text-blue-400 transition font-mono">
                        View all {{ healthSummary.total }} →
                    </Link>
                </div>
                <div class="flex h-1.5 rounded-full overflow-hidden bg-white/[0.05] mb-4">
                    <div v-if="healthSummary.healthy"
                         :style="{ width: (healthSummary.healthy / healthSummary.total * 100) + '%' }"
                         class="bg-emerald-500 transition-all" />
                    <div v-if="healthSummary.warning"
                         :style="{ width: (healthSummary.warning / healthSummary.total * 100) + '%' }"
                         class="bg-amber-400 transition-all" />
                    <div v-if="healthSummary.critical"
                         :style="{ width: (healthSummary.critical / healthSummary.total * 100) + '%' }"
                         class="bg-red-500 transition-all" />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="`/admin/tenants?health=healthy`"
                          class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 hover:bg-emerald-500/20 transition">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block" />
                        {{ healthSummary.healthy }} Healthy
                    </Link>
                    <Link :href="`/admin/tenants?health=warning`"
                          class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-300 hover:bg-amber-500/20 transition">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block" />
                        {{ healthSummary.warning }} Warning
                    </Link>
                    <Link :href="`/admin/tenants?health=critical`"
                          class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-red-500/10 border border-red-500/20 text-red-400 hover:bg-red-500/20 transition">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block" />
                        {{ healthSummary.critical }} Critical
                    </Link>
                    <Link href="/admin/tenants"
                          class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-orange-500/10 border border-orange-500/20 text-orange-300 hover:bg-orange-500/20 transition">
                        <span class="w-1.5 h-1.5 rounded-full bg-orange-400 inline-block" />
                        {{ healthSummary.maintenance }} Maintenance
                    </Link>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-white/[0.06] flex items-center justify-between">
                    <h2 class="font-grotesk text-base font-semibold text-white">Recent Users</h2>
                    <Link href="/admin/users" class="text-xs text-slate-500 hover:text-blue-400 transition font-mono">View all →</Link>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/[0.06]">
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Name</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Email</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <tr v-for="user in recentUsers" :key="user.id" class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-5 py-3.5 text-slate-200 font-medium">{{ user.name }}</td>
                            <td class="px-5 py-3.5 text-slate-400">{{ user.email }}</td>
                            <td class="px-5 py-3.5">
                                <span v-if="user.is_active"
                                      class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-300">
                                    Active
                                </span>
                                <span v-else
                                      class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-300">
                                    Pending
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <Link :href="`/admin/users?edit=${user.id}`"
                                      class="text-xs text-blue-400 hover:text-blue-300 transition">
                                    Edit
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="recentUsers.length === 0">
                            <td colspan="4" class="px-5 py-10 text-center text-slate-600 text-sm">No users yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Unresolved Error Logs -->
            <div id="tour-admin-errors"
                 class="bg-white/[0.02] border rounded-2xl overflow-hidden"
                 :class="unresolvedErrors.total > 0 ? 'border-red-500/20' : 'border-white/[0.06]'">
                <button @click="errorsOpen = !errorsOpen"
                        class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h3 class="font-grotesk font-semibold text-white">Unresolved Error Logs</h3>
                        <span v-if="unresolvedErrors.total > 0"
                              class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-red-500/10 border border-red-500/20 text-red-400">
                            {{ unresolvedErrors.total }}
                        </span>
                        <span v-else class="text-slate-600 text-xs font-mono">All clear</span>
                        <template v-if="unresolvedErrors.total > 0">
                            <span v-if="unresolvedErrors.critical"
                                  class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-red-500/10 border border-red-500/20 text-red-400">
                                {{ unresolvedErrors.critical }} critical
                            </span>
                            <span v-if="unresolvedErrors.error"
                                  class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-orange-500/10 border border-orange-500/20 text-orange-300">
                                {{ unresolvedErrors.error }} error
                            </span>
                            <span v-if="unresolvedErrors.warning"
                                  class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-300">
                                {{ unresolvedErrors.warning }} warning
                            </span>
                        </template>
                    </div>
                    <svg class="w-4 h-4 text-slate-500 transition-transform shrink-0 ml-3" :class="errorsOpen ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div v-if="errorsOpen" class="border-t border-white/[0.06]">
                    <div v-if="unresolvedErrors.total === 0" class="px-5 py-10 text-center text-slate-600 text-sm">
                        No unresolved errors across any tenant.
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-white/[0.06]">
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Tenant</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Critical</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Error</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Warning</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Total</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.04]">
                            <tr v-for="t in unresolvedErrors.by_tenant" :key="t.tenant_id"
                                class="hover:bg-white/[0.02] transition-colors">
                                <td class="px-5 py-3.5 text-slate-200 font-medium">{{ t.tenant_name }}</td>
                                <td class="px-5 py-3.5">
                                    <span v-if="t.critical" class="text-xs font-mono px-2 py-0.5 rounded-full bg-red-500/10 border border-red-500/20 text-red-400">{{ t.critical }}</span>
                                    <span v-else class="text-slate-600">—</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span v-if="t.error" class="text-xs font-mono px-2 py-0.5 rounded-full bg-orange-500/10 border border-orange-500/20 text-orange-300">{{ t.error }}</span>
                                    <span v-else class="text-slate-600">—</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span v-if="t.warning" class="text-xs font-mono px-2 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-300">{{ t.warning }}</span>
                                    <span v-else class="text-slate-600">—</span>
                                </td>
                                <td class="px-5 py-3.5 text-slate-300 font-medium font-mono text-xs">{{ t.total }}</td>
                                <td class="px-5 py-3.5">
                                    <Link :href="`/admin/tenants/${t.tenant_id}/errors`"
                                          class="text-xs text-blue-400 hover:text-blue-300 transition">
                                        View →
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Report Queue -->
            <div class="bg-white/[0.02] border rounded-2xl overflow-hidden"
                 :class="reportQueue.failed > 0 ? 'border-red-500/20' : 'border-white/[0.06]'">
                <button @click="reportQueueOpen = !reportQueueOpen"
                        class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h3 class="font-grotesk font-semibold text-white">Report Queue</h3>
                        <span v-if="reportQueue.pending > 0"
                              class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-300">
                            {{ reportQueue.pending }} pending
                        </span>
                        <span v-if="reportQueue.processing > 0"
                              class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-300">
                            {{ reportQueue.processing }} processing
                        </span>
                        <span v-if="reportQueue.failed > 0"
                              class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-red-500/10 border border-red-500/20 text-red-400">
                            {{ reportQueue.failed }} failed
                        </span>
                        <span v-if="reportQueue.pending === 0 && reportQueue.processing === 0 && reportQueue.failed === 0"
                              class="text-slate-600 text-xs font-mono">Queue empty</span>
                    </div>
                    <svg class="w-4 h-4 text-slate-500 transition-transform shrink-0 ml-3" :class="reportQueueOpen ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div v-if="reportQueueOpen" class="border-t border-white/[0.06]">
                    <div v-if="reportQueue.by_tenant.length === 0" class="px-5 py-10 text-center text-slate-600 text-sm">
                        No pending, processing, or failed reports.
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-white/[0.06]">
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Tenant</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Pending</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Processing</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-red-500/60 uppercase tracking-wider">Failed</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.04]">
                            <tr v-for="t in reportQueue.by_tenant" :key="t.tenant_id"
                                class="hover:bg-white/[0.02] transition-colors">
                                <td class="px-5 py-3.5 text-slate-200 font-medium">{{ t.tenant_name }}</td>
                                <td class="px-5 py-3.5">
                                    <span v-if="t.pending" class="text-xs font-mono px-2 py-0.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-300">{{ t.pending }}</span>
                                    <span v-else class="text-slate-600">—</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span v-if="t.processing" class="text-xs font-mono px-2 py-0.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-300">{{ t.processing }}</span>
                                    <span v-else class="text-slate-600">—</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span v-if="t.failed" class="text-xs font-mono px-2 py-0.5 rounded-full bg-red-500/10 border border-red-500/20 text-red-400">{{ t.failed }}</span>
                                    <span v-else class="text-slate-600">—</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <Link :href="`/admin/tenants/${t.tenant_id}/reports`"
                                          class="text-xs text-blue-400 hover:text-blue-300 transition">
                                        View →
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Migration Compliance -->
            <div class="bg-white/[0.02] border rounded-2xl overflow-hidden"
                 :class="migrationCompliance.behind_count > 0 ? 'border-amber-500/20' : 'border-white/[0.06]'">
                <button @click="migrationOpen = !migrationOpen"
                        class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h3 class="font-grotesk font-semibold text-white">Migration Compliance</h3>
                        <span class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-300">
                            {{ migrationCompliance.up_to_date }} up-to-date
                        </span>
                        <span v-if="migrationCompliance.behind_count > 0"
                              class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-300">
                            {{ migrationCompliance.behind_count }} behind
                        </span>
                        <span v-if="migrationCompliance.behind_count === 0" class="text-slate-600 text-xs font-mono">All tenants up-to-date</span>
                    </div>
                    <svg class="w-4 h-4 text-slate-500 transition-transform shrink-0 ml-3" :class="migrationOpen ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div v-if="migrationOpen" class="border-t border-white/[0.06]">
                    <div v-if="migrationCompliance.behind.length === 0" class="px-5 py-10 text-center text-slate-600 text-sm">
                        All {{ migrationCompliance.total }} tenants are fully migrated ({{ migrationCompliance.available_migrations }} migrations applied).
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-white/[0.06]">
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Tenant</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Applied</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Available</th>
                                <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Behind</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.04]">
                            <tr v-for="t in migrationCompliance.behind" :key="t.id"
                                class="hover:bg-white/[0.02] transition-colors">
                                <td class="px-5 py-3.5 text-slate-200 font-medium">{{ t.name }}</td>
                                <td class="px-5 py-3.5 text-slate-400 font-mono text-xs">{{ t.applied }}</td>
                                <td class="px-5 py-3.5 text-slate-400 font-mono text-xs">{{ t.available }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="text-xs font-mono px-2 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-300">
                                        {{ t.available - t.applied }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <button @click="runMigration(t)"
                                            :disabled="migrating === t.id"
                                            class="text-xs font-medium text-blue-400 hover:text-blue-300 disabled:opacity-50 disabled:cursor-not-allowed transition">
                                        {{ migrating === t.id ? 'Running…' : 'Run migrations' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <TourButton @click="startTour" />
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
