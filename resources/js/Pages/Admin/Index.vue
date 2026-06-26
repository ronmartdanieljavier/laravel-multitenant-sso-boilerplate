<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useIdleTimeout } from '../../composables/useIdleTimeout';
import TourButton from '../Partials/TourButton.vue';
import { useTour } from '../../composables/useTour';

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

    <div class="min-h-screen bg-slate-950 text-slate-100">
        <!-- Sidebar -->
        <aside class="fixed inset-y-0 left-0 w-60 bg-slate-900 border-r border-white/5 flex flex-col">
            <div class="h-16 flex items-center px-6 border-b border-white/5">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <span class="font-semibold text-white">SSO Admin</span>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1">
                <a href="#" class="flex items-center gap-3 py-2 text-sm font-medium transition border-l-2 border-blue-500 rounded-r-lg pl-[10px] pr-3 text-white">
                    Dashboard
                </a>
                <Link href="/admin/users"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Users
                </Link>
                <Link href="/admin/apps"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Apps
                </Link>
                <Link href="/admin/tenants"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Tenants
                </Link>
                <Link href="/admin/settings"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Settings
                </Link>
            </nav>
            <!-- App selection -->
            <div class="px-3 pb-1 shrink-0">
                <Link href="/apps" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-white hover:bg-white/5 transition">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    App Selection
                </Link>
            </div>

            <!-- User / sign-out -->
            <div class="p-4 border-t border-white/5">
                <div class="flex items-center gap-3">
                    <Link href="/profile" class="flex items-center gap-3 flex-1 min-w-0 hover:opacity-80 transition">
                        <div v-if="page.props.auth.user?.profile_picture_url" class="w-8 h-8 rounded-full overflow-hidden shrink-0">
                            <img :src="page.props.auth.user.profile_picture_url" class="w-full h-full object-cover" alt="Profile" />
                        </div>
                        <div v-else class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-xs font-bold text-white shrink-0">
                            {{ page.props.auth.user?.name?.[0]?.toUpperCase() ?? 'A' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ page.props.auth.user?.name ?? 'Admin' }}</p>
                            <p class="text-xs text-slate-400 truncate">{{ page.props.auth.user?.email }}</p>
                        </div>
                    </Link>
                    <button @click="logout" title="Sign out" class="text-slate-400 hover:text-white transition shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <div class="ml-60">
            <!-- Missing settings banner -->
            <div v-if="missingSettings.length > 0"
                 class="bg-amber-500/10 border-b border-amber-500/20 px-8 py-3 flex items-center gap-3">
                <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                <p class="text-sm text-amber-300">
                    Required settings not configured:
                    <span class="font-medium">{{ missingSettings.join(', ') }}</span>.
                    <Link href="/admin/settings" class="underline hover:text-amber-200 ml-1">Go to Settings</Link>
                </p>
            </div>

            <!-- Header -->
            <header class="h-16 bg-slate-900/50 border-b border-white/5 flex items-center justify-between px-8">
                <h2 class="text-lg font-semibold">Dashboard</h2>
                <div class="flex items-center gap-2">
                    <Link href="/admin/settings"
                          class="text-slate-400 hover:text-slate-200 hover:bg-white/5 text-sm font-medium px-4 py-1.5 rounded-lg transition border border-white/10">
                        Settings
                    </Link>
                    <Link href="/admin/tenants?add=1"
                          class="text-slate-400 hover:text-slate-200 hover:bg-white/5 text-sm font-medium px-4 py-1.5 rounded-lg transition border border-white/10">
                        + Add Tenant
                    </Link>
                    <Link href="/admin/users?invite=1"
                          class="bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition">
                        + Invite User
                    </Link>
                </div>
            </header>

            <main class="p-8 space-y-8">
                <!-- Stats -->
                <div id="tour-admin-stats" class="grid grid-cols-4 gap-4">
                    <div v-for="card in statCards" :key="card.label"
                         :class="card.label === 'Total Users' && stats.pending_invitation_users > 0
                             ? 'bg-slate-900 border border-amber-500/30 rounded-xl p-5'
                             : 'bg-slate-900 border border-white/5 rounded-xl p-5'">
                        <p class="text-slate-400 text-sm">{{ card.label }}</p>
                        <p class="text-2xl font-bold text-white mt-1">{{ card.value.toLocaleString() }}</p>
                        <p v-if="card.sub"
                           :class="card.label === 'Total Users' && stats.pending_invitation_users > 0
                               ? 'text-amber-400 text-xs mt-1 font-medium'
                               : 'text-slate-500 text-xs mt-1'">
                            {{ card.sub }}
                        </p>
                    </div>
                </div>

                <!-- Pending Invitations -->
                <div v-if="pendingUsers.length > 0" id="tour-admin-pending" class="bg-slate-900 border border-amber-500/20 rounded-xl overflow-hidden">
                    <button @click="pendingOpen = !pendingOpen"
                            class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-white/2 transition">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse inline-block" />
                            <h3 class="font-semibold text-amber-300">Pending Invitations</h3>
                            <span class="bg-amber-500/20 text-amber-300 text-xs font-bold px-2 py-0.5 rounded-full">
                                {{ pendingUsers.length }}
                            </span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 transition-transform"
                             :class="pendingOpen ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div v-if="pendingOpen" class="border-t border-white/5">
                        <table class="w-full text-sm">
                            <thead class="text-slate-400 border-b border-white/5">
                                <tr>
                                    <th class="text-left px-6 py-3 font-medium">Name</th>
                                    <th class="text-left px-6 py-3 font-medium">Email</th>
                                    <th class="text-left px-6 py-3 font-medium">Invited</th>
                                    <th class="text-left px-6 py-3 font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="user in pendingUsers" :key="user.id" class="hover:bg-white/2 transition">
                                    <td class="px-6 py-4 font-medium text-white">{{ user.name }}</td>
                                    <td class="px-6 py-4 text-slate-400">{{ user.email }}</td>
                                    <td class="px-6 py-4 text-slate-500 text-xs">
                                        {{ formatSentAt(user.invitation_sent_at) }}
                                    </td>
                                    <td class="px-6 py-4 flex items-center gap-3">
                                        <button @click="resendInvitation(user)"
                                                :disabled="resending === user.id"
                                                class="text-xs font-medium text-amber-400 hover:text-amber-300 disabled:opacity-50 disabled:cursor-not-allowed transition">
                                            {{ resending === user.id ? 'Sending…' : 'Resend' }}
                                        </button>
                                        <Link :href="`/admin/users?edit=${user.id}`"
                                              class="text-xs text-blue-400 hover:text-blue-300 transition">
                                            Edit
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tenant Health Summary -->
                <div id="tour-admin-health" class="bg-slate-900 border border-white/5 rounded-xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-white">Tenant Health</h3>
                        <Link href="/admin/tenants" class="text-xs text-slate-400 hover:text-blue-300 transition">
                            View all {{ healthSummary.total }} tenants →
                        </Link>
                    </div>
                    <div class="flex items-center gap-3">
                        <!-- Proportional bar -->
                        <div class="flex-1 flex h-2 rounded-full overflow-hidden bg-slate-800">
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
                        <!-- Status badges -->
                        <div class="flex items-center gap-2 shrink-0">
                            <Link :href="`/admin/tenants?health=healthy`"
                                  class="flex items-center gap-1.5 bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 text-xs font-medium px-3 py-1 rounded-full transition">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block" />
                                {{ healthSummary.healthy }} Healthy
                            </Link>
                            <Link :href="`/admin/tenants?health=warning`"
                                  class="flex items-center gap-1.5 bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 text-xs font-medium px-3 py-1 rounded-full transition">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block" />
                                {{ healthSummary.warning }} Warning
                            </Link>
                            <Link :href="`/admin/tenants?health=critical`"
                                  class="flex items-center gap-1.5 bg-red-500/15 hover:bg-red-500/25 text-red-400 text-xs font-medium px-3 py-1 rounded-full transition">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block" />
                                {{ healthSummary.critical }} Critical
                            </Link>
                            <Link href="/admin/tenants"
                                  class="flex items-center gap-1.5 bg-orange-500/15 hover:bg-orange-500/25 text-orange-300 text-xs font-medium px-3 py-1 rounded-full transition">
                                <span class="w-1.5 h-1.5 rounded-full bg-orange-400 inline-block" />
                                {{ healthSummary.maintenance }} Maintenance
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                        <h3 class="font-semibold text-white">Recent Users</h3>
                        <Link href="/admin/users" class="text-xs text-slate-400 hover:text-blue-300 transition">
                            View all →
                        </Link>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="text-slate-400 border-b border-white/5">
                            <tr>
                                <th class="text-left px-6 py-3 font-medium">Name</th>
                                <th class="text-left px-6 py-3 font-medium">Email</th>
                                <th class="text-left px-6 py-3 font-medium">Status</th>
                                <th class="text-left px-6 py-3 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="user in recentUsers" :key="user.id" class="hover:bg-white/2 transition">
                                <td class="px-6 py-4 font-medium text-white">{{ user.name }}</td>
                                <td class="px-6 py-4 text-slate-400">{{ user.email }}</td>
                                <td class="px-6 py-4">
                                    <span v-if="user.is_active"
                                          class="bg-emerald-500/20 text-emerald-300 text-xs px-2 py-0.5 rounded-full">
                                        Active
                                    </span>
                                    <span v-else
                                          class="bg-amber-500/20 text-amber-300 text-xs px-2 py-0.5 rounded-full">
                                        Pending Invitation
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <Link :href="`/admin/users?edit=${user.id}`"
                                          class="text-blue-400 hover:text-blue-300 text-xs transition">
                                        Edit
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="recentUsers.length === 0">
                                <td colspan="4" class="px-6 py-8 text-center text-slate-500 text-sm">No users yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Unresolved Error Logs -->
                <div id="tour-admin-errors" :class="unresolvedErrors.total > 0 ? 'border-red-500/20' : 'border-white/5'"
                     class="bg-slate-900 border rounded-xl overflow-hidden">
                    <button @click="errorsOpen = !errorsOpen"
                            class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-white/2 transition">
                        <div class="flex items-center gap-3">
                            <h3 class="font-semibold text-white">Unresolved Error Logs</h3>
                            <span v-if="unresolvedErrors.total > 0"
                                  class="bg-red-500/20 text-red-400 text-xs font-bold px-2 py-0.5 rounded-full">
                                {{ unresolvedErrors.total }}
                            </span>
                            <span v-else class="text-slate-500 text-xs">All clear</span>
                            <!-- Severity pills (always visible) -->
                            <template v-if="unresolvedErrors.total > 0">
                                <span v-if="unresolvedErrors.critical"
                                      class="bg-red-500/20 text-red-400 text-xs px-2 py-0.5 rounded-full">
                                    {{ unresolvedErrors.critical }} critical
                                </span>
                                <span v-if="unresolvedErrors.error"
                                      class="bg-orange-500/20 text-orange-300 text-xs px-2 py-0.5 rounded-full">
                                    {{ unresolvedErrors.error }} error
                                </span>
                                <span v-if="unresolvedErrors.warning"
                                      class="bg-amber-500/20 text-amber-300 text-xs px-2 py-0.5 rounded-full">
                                    {{ unresolvedErrors.warning }} warning
                                </span>
                            </template>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 transition-transform"
                             :class="errorsOpen ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div v-if="errorsOpen" class="border-t border-white/5">
                        <div v-if="unresolvedErrors.total === 0"
                             class="px-6 py-8 text-center text-slate-500 text-sm">
                            No unresolved errors across any tenant.
                        </div>
                        <table v-else class="w-full text-sm">
                            <thead class="text-slate-400 border-b border-white/5">
                                <tr>
                                    <th class="text-left px-6 py-3 font-medium">Tenant</th>
                                    <th class="text-left px-6 py-3 font-medium">Critical</th>
                                    <th class="text-left px-6 py-3 font-medium">Error</th>
                                    <th class="text-left px-6 py-3 font-medium">Warning</th>
                                    <th class="text-left px-6 py-3 font-medium">Total</th>
                                    <th class="text-left px-6 py-3 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="t in unresolvedErrors.by_tenant" :key="t.tenant_id"
                                    class="hover:bg-white/2 transition">
                                    <td class="px-6 py-4 font-medium text-white">{{ t.tenant_name }}</td>
                                    <td class="px-6 py-4">
                                        <span v-if="t.critical"
                                              class="bg-red-500/20 text-red-400 text-xs px-2 py-0.5 rounded-full">
                                            {{ t.critical }}
                                        </span>
                                        <span v-else class="text-slate-600">—</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span v-if="t.error"
                                              class="bg-orange-500/20 text-orange-300 text-xs px-2 py-0.5 rounded-full">
                                            {{ t.error }}
                                        </span>
                                        <span v-else class="text-slate-600">—</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span v-if="t.warning"
                                              class="bg-amber-500/20 text-amber-300 text-xs px-2 py-0.5 rounded-full">
                                            {{ t.warning }}
                                        </span>
                                        <span v-else class="text-slate-600">—</span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-300 font-medium">{{ t.total }}</td>
                                    <td class="px-6 py-4">
                                        <Link :href="`/admin/tenants/${t.tenant_id}/errors`"
                                              class="text-blue-400 hover:text-blue-300 text-xs transition">
                                            View errors →
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Report Queue Health -->
                <div :class="reportQueue.failed > 0 ? 'border-red-500/20' : 'border-white/5'"
                     class="bg-slate-900 border rounded-xl overflow-hidden">
                    <button @click="reportQueueOpen = !reportQueueOpen"
                            class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-white/2 transition">
                        <div class="flex items-center gap-3">
                            <h3 class="font-semibold text-white">Report Queue</h3>
                            <span v-if="reportQueue.pending > 0"
                                  class="bg-blue-600/20 text-blue-300 text-xs px-2 py-0.5 rounded-full">
                                {{ reportQueue.pending }} pending
                            </span>
                            <span v-if="reportQueue.processing > 0"
                                  class="bg-blue-500/20 text-blue-300 text-xs px-2 py-0.5 rounded-full">
                                {{ reportQueue.processing }} processing
                            </span>
                            <span v-if="reportQueue.failed > 0"
                                  class="bg-red-500/20 text-red-400 text-xs font-bold px-2 py-0.5 rounded-full">
                                {{ reportQueue.failed }} failed
                            </span>
                            <span v-if="reportQueue.pending === 0 && reportQueue.processing === 0 && reportQueue.failed === 0"
                                  class="text-slate-500 text-xs">Queue empty</span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 transition-transform"
                             :class="reportQueueOpen ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div v-if="reportQueueOpen" class="border-t border-white/5">
                        <div v-if="reportQueue.by_tenant.length === 0"
                             class="px-6 py-8 text-center text-slate-500 text-sm">
                            No pending, processing, or failed reports.
                        </div>
                        <table v-else class="w-full text-sm">
                            <thead class="text-slate-400 border-b border-white/5">
                                <tr>
                                    <th class="text-left px-6 py-3 font-medium">Tenant</th>
                                    <th class="text-left px-6 py-3 font-medium">Pending</th>
                                    <th class="text-left px-6 py-3 font-medium">Processing</th>
                                    <th class="text-left px-6 py-3 font-medium text-red-400">Failed</th>
                                    <th class="text-left px-6 py-3 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="t in reportQueue.by_tenant" :key="t.tenant_id"
                                    class="hover:bg-white/2 transition">
                                    <td class="px-6 py-4 font-medium text-white">{{ t.tenant_name }}</td>
                                    <td class="px-6 py-4">
                                        <span v-if="t.pending"
                                              class="bg-blue-600/20 text-blue-300 text-xs px-2 py-0.5 rounded-full">
                                            {{ t.pending }}
                                        </span>
                                        <span v-else class="text-slate-600">—</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span v-if="t.processing"
                                              class="bg-blue-500/20 text-blue-300 text-xs px-2 py-0.5 rounded-full">
                                            {{ t.processing }}
                                        </span>
                                        <span v-else class="text-slate-600">—</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span v-if="t.failed"
                                              class="bg-red-500/20 text-red-400 text-xs font-bold px-2 py-0.5 rounded-full">
                                            {{ t.failed }}
                                        </span>
                                        <span v-else class="text-slate-600">—</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <Link :href="`/admin/tenants/${t.tenant_id}/reports`"
                                              class="text-blue-400 hover:text-blue-300 text-xs transition">
                                            View reports →
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tenant Migration Compliance -->
                <div :class="migrationCompliance.behind_count > 0 ? 'border-amber-500/20' : 'border-white/5'"
                     class="bg-slate-900 border rounded-xl overflow-hidden">
                    <button @click="migrationOpen = !migrationOpen"
                            class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-white/2 transition">
                        <div class="flex items-center gap-3">
                            <h3 class="font-semibold text-white">Migration Compliance</h3>
                            <span class="bg-emerald-500/20 text-emerald-300 text-xs px-2 py-0.5 rounded-full">
                                {{ migrationCompliance.up_to_date }} up-to-date
                            </span>
                            <span v-if="migrationCompliance.behind_count > 0"
                                  class="bg-amber-500/20 text-amber-300 text-xs font-bold px-2 py-0.5 rounded-full">
                                {{ migrationCompliance.behind_count }} behind
                            </span>
                            <span v-if="migrationCompliance.behind_count === 0" class="text-slate-500 text-xs">
                                All tenants up-to-date
                            </span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 transition-transform"
                             :class="migrationOpen ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div v-if="migrationOpen" class="border-t border-white/5">
                        <div v-if="migrationCompliance.behind.length === 0"
                             class="px-6 py-8 text-center text-slate-500 text-sm">
                            All {{ migrationCompliance.total }} tenants are fully migrated
                            ({{ migrationCompliance.available_migrations }} migrations applied).
                        </div>
                        <table v-else class="w-full text-sm">
                            <thead class="text-slate-400 border-b border-white/5">
                                <tr>
                                    <th class="text-left px-6 py-3 font-medium">Tenant</th>
                                    <th class="text-left px-6 py-3 font-medium">Applied</th>
                                    <th class="text-left px-6 py-3 font-medium">Available</th>
                                    <th class="text-left px-6 py-3 font-medium">Behind by</th>
                                    <th class="text-left px-6 py-3 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="t in migrationCompliance.behind" :key="t.id"
                                    class="hover:bg-white/2 transition">
                                    <td class="px-6 py-4 font-medium text-white">{{ t.name }}</td>
                                    <td class="px-6 py-4 text-slate-300">{{ t.applied }}</td>
                                    <td class="px-6 py-4 text-slate-300">{{ t.available }}</td>
                                    <td class="px-6 py-4">
                                        <span class="bg-amber-500/20 text-amber-300 text-xs font-bold px-2 py-0.5 rounded-full">
                                            {{ t.available - t.applied }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
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
    </div>

    <TourButton @click="startTour" />
</template>
