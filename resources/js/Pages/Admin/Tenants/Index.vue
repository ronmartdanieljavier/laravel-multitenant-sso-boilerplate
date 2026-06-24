<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const missingSettings = page.props.missingRequiredSettings ?? [];

function logout() {
    router.post('/logout');
}

const props = defineProps({
    tenants: Array,
    summary: Object,
});

function formatDate(value) {
    if (!value) return 'Never';
    const d = new Date(value);
    const diff = Math.floor((Date.now() - d) / 86400000);
    if (diff === 0) return 'Today';
    if (diff === 1) return 'Yesterday';
    return `${diff}d ago`;
}

const summaryCards = [
    { label: 'Total Tenants', key: 'total', color: 'text-white' },
    { label: 'Healthy', key: 'healthy', color: 'text-emerald-400' },
    { label: 'Warning', key: 'warning', color: 'text-amber-400' },
    { label: 'Critical', key: 'critical', color: 'text-red-400' },
];

const healthBadge = {
    healthy: 'bg-emerald-500/20 text-emerald-300',
    warning: 'bg-amber-500/20 text-amber-300',
    critical: 'bg-red-500/20 text-red-400',
};
</script>

<template>
    <Head title="Tenant Health Dashboard" />

    <div class="min-h-screen bg-slate-950 text-slate-100">
        <!-- Sidebar -->
        <aside class="fixed inset-y-0 left-0 w-60 bg-slate-900 border-r border-white/5 flex flex-col">
            <div class="h-16 flex items-center px-6 border-b border-white/5">
                <div class="w-8 h-8 bg-violet-600 rounded-lg flex items-center justify-center mr-3">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <span class="font-semibold text-white">SSO Admin</span>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1">
                <Link href="/admin"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Dashboard
                </Link>
                <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Users
                </a>
                <Link href="/admin/apps"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Apps
                </Link>
                <Link href="/admin/tenants"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition bg-violet-600/20 text-violet-300">
                    Tenants
                </Link>
                <Link href="/admin/settings"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Settings
                </Link>
            </nav>
            <div class="p-4 border-t border-white/5">
                <div class="flex items-center gap-3">
                    <Link href="/profile" class="flex items-center gap-3 flex-1 min-w-0 hover:opacity-80 transition">
                        <div v-if="page.props.auth.user?.profile_picture_url" class="w-8 h-8 rounded-full overflow-hidden shrink-0">
                            <img :src="page.props.auth.user.profile_picture_url" class="w-full h-full object-cover" alt="Profile" />
                        </div>
                        <div v-else class="w-8 h-8 rounded-full bg-violet-500 flex items-center justify-center text-xs font-bold text-white shrink-0">
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
                <h2 class="text-lg font-semibold">Tenant Health</h2>
            </header>

            <main class="p-8 space-y-8">
                <!-- Summary Cards -->
                <div class="grid grid-cols-4 gap-4">
                    <div v-for="card in summaryCards" :key="card.key"
                         class="bg-slate-900 border border-white/5 rounded-xl p-5">
                        <p class="text-slate-400 text-sm">{{ card.label }}</p>
                        <p :class="card.color" class="text-2xl font-bold mt-1">{{ summary[card.key] }}</p>
                    </div>
                </div>

                <!-- Tenants Table -->
                <div class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-white/5">
                        <h3 class="font-semibold text-white">All Tenants</h3>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="text-slate-400 border-b border-white/5">
                            <tr>
                                <th class="text-left px-6 py-3 font-medium">Tenant</th>
                                <th class="text-left px-6 py-3 font-medium">Status</th>
                                <th class="text-left px-6 py-3 font-medium">Health</th>
                                <th class="text-left px-6 py-3 font-medium">Users</th>
                                <th class="text-left px-6 py-3 font-medium">Last Migration</th>
                                <th class="text-left px-6 py-3 font-medium">Reports (P/F)</th>
                                <th class="text-left px-6 py-3 font-medium">Read Replica</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="tenant in tenants" :key="tenant.id" class="hover:bg-white/2 transition">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-white">{{ tenant.name }}</p>
                                    <p class="text-xs text-slate-500">{{ tenant.slug }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="tenant.is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-500/20 text-slate-400'"
                                          class="text-xs px-2 py-0.5 rounded-full">
                                        {{ tenant.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="healthBadge[tenant.health_status]"
                                          class="text-xs px-2 py-0.5 rounded-full capitalize">
                                        {{ tenant.health_status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-300">{{ tenant.user_count }}</td>
                                <td class="px-6 py-4 text-slate-400">{{ formatDate(tenant.last_migration) }}</td>
                                <td class="px-6 py-4">
                                    <span class="text-slate-400">{{ tenant.pending_reports }}</span>
                                    <span class="text-slate-600 mx-1">/</span>
                                    <span :class="tenant.failed_reports > 0 ? 'text-red-400' : 'text-slate-400'">{{ tenant.failed_reports }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="tenant.has_read_replica ? 'bg-violet-500/20 text-violet-300' : 'bg-slate-500/20 text-slate-500'"
                                          class="text-xs px-2 py-0.5 rounded-full">
                                        {{ tenant.has_read_replica ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="tenants.length === 0">
                                <td colspan="7" class="px-6 py-8 text-center text-slate-500">No tenants found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
</template>
