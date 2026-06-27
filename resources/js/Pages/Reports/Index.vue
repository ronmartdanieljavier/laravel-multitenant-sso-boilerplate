<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useIdleTimeout } from '../../composables/useIdleTimeout';
import TenantLayout from '../../Layouts/TenantLayout.vue';

defineOptions({ layout: TenantLayout });

const page = usePage();
useIdleTimeout(page.props.idleTimeoutMinutes);

function logout() {
    router.post('/logout');
}

const activeTab = ref('overview');

const tabs = ['overview', 'users', 'sessions', 'exports'];

const summaryCards = [
    { label: 'Total Logins', value: '28,491', trend: '+14%' },
    { label: 'Unique Users', value: '3,210', trend: '+6%' },
    { label: 'SSO Tokens Issued', value: '18,030', trend: '+22%' },
    { label: 'Failed Attempts', value: '412', trend: '-5%', negative: true },
];

const reportRows = [
    { name: 'Monthly Login Summary', generated: '2026-06-01', format: 'PDF', size: '1.2 MB' },
    { name: 'User Activity Report', generated: '2026-05-30', format: 'CSV', size: '430 KB' },
    { name: 'SSO Token Audit', generated: '2026-05-25', format: 'XLSX', size: '890 KB' },
    { name: 'Tenant Access Log', generated: '2026-05-20', format: 'PDF', size: '2.1 MB' },
];

const formatColor = {
    PDF: 'bg-red-500/10 border-red-500/20 text-red-300',
    CSV: 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300',
    XLSX: 'bg-blue-500/10 border-blue-500/20 text-blue-300',
};
</script>

<template>
    <Head title="Reports" />

    <div class="min-h-screen bg-[#030712] text-slate-100">
        <!-- Top nav -->
        <header class="h-16 border-b border-white/[0.05] flex items-center px-8 justify-between bg-[#030712]">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                     style="background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 0 16px rgba(99,102,241,0.3)">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <span class="font-grotesk font-semibold text-white">Reports Suite</span>
            </div>
            <div class="flex items-center gap-3">
                <button
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white cursor-pointer transition-all duration-150"
                    style="background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 0 20px rgba(99,102,241,0.25)"
                >
                    Generate Report
                </button>
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold"
                     style="background: linear-gradient(135deg, #2563eb, #7c3aed)">
                    {{ page.props.auth.user?.name?.[0]?.toUpperCase() ?? 'U' }}
                </div>
                <button @click="logout" title="Sign out" class="text-slate-500 hover:text-white transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </button>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-8 py-8 space-y-6">
            <!-- Summary cards -->
            <div class="grid grid-cols-4 gap-4">
                <div v-for="card in summaryCards" :key="card.label"
                     class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5 border-t-2"
                     :class="card.negative ? 'border-t-red-500/40' : 'border-t-emerald-500/40'">
                    <div class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-1">{{ card.label }}</div>
                    <div class="text-3xl font-grotesk font-semibold text-white mt-1 tabular-nums">{{ card.value }}</div>
                    <div :class="card.negative ? 'text-red-400' : 'text-emerald-400'" class="text-xs mt-1 font-mono">
                        {{ card.trend }} vs last month
                    </div>
                </div>
            </div>

            <!-- Tabs + content -->
            <div class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
                <!-- Tabs -->
                <div class="flex border-b border-white/[0.06] px-5">
                    <button
                        v-for="tab in tabs"
                        :key="tab"
                        @click="activeTab = tab"
                        :class="activeTab === tab
                            ? 'border-blue-500 text-white'
                            : 'border-transparent text-slate-500 hover:text-slate-300'"
                        class="capitalize text-sm font-medium font-grotesk px-4 py-4 border-b-2 transition-colors -mb-px cursor-pointer"
                    >
                        {{ tab }}
                    </button>
                </div>

                <!-- Table -->
                <div class="p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="font-grotesk font-semibold text-white">Generated Reports</h3>
                        <input
                            type="text"
                            placeholder="Search reports…"
                            class="bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2 text-sm text-slate-300 placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent w-52 transition"
                        />
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-white/[0.06]">
                                <th class="text-left py-3 px-2 text-xs font-mono text-slate-500 uppercase tracking-wider font-medium">Report Name</th>
                                <th class="text-left py-3 px-2 text-xs font-mono text-slate-500 uppercase tracking-wider font-medium">Generated</th>
                                <th class="text-left py-3 px-2 text-xs font-mono text-slate-500 uppercase tracking-wider font-medium">Format</th>
                                <th class="text-left py-3 px-2 text-xs font-mono text-slate-500 uppercase tracking-wider font-medium">Size</th>
                                <th class="text-left py-3 px-2 text-xs font-mono text-slate-500 uppercase tracking-wider font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.04]">
                            <tr v-for="row in reportRows" :key="row.name" class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-3.5 px-2 font-medium text-slate-200">{{ row.name }}</td>
                                <td class="py-3.5 px-2 text-slate-400 text-xs font-mono">{{ row.generated }}</td>
                                <td class="py-3.5 px-2">
                                    <span :class="['inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full border', formatColor[row.format]]">{{ row.format }}</span>
                                </td>
                                <td class="py-3.5 px-2 text-slate-400 text-xs font-mono">{{ row.size }}</td>
                                <td class="py-3.5 px-2 flex items-center gap-3">
                                    <a href="#" class="text-emerald-400 hover:text-emerald-300 text-xs transition font-mono">Download</a>
                                    <a href="#" class="text-slate-500 hover:text-red-400 text-xs transition font-mono">Delete</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
