<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

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
    PDF: 'bg-red-500/20 text-red-300',
    CSV: 'bg-emerald-500/20 text-emerald-300',
    XLSX: 'bg-blue-500/20 text-blue-300',
};
</script>

<template>
    <Head title="Reports" />

    <div class="min-h-screen bg-slate-950 text-slate-100">
        <!-- Top nav -->
        <header class="h-16 bg-slate-900 border-b border-white/5 flex items-center px-8 justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <span class="font-semibold text-white">Reports Suite</span>
            </div>
            <div class="flex items-center gap-3">
                <button class="bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition">
                    Generate Report
                </button>
                <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-xs font-bold">R</div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-8 py-8 space-y-8">
            <!-- Summary cards -->
            <div class="grid grid-cols-4 gap-4">
                <div v-for="card in summaryCards" :key="card.label"
                     class="bg-slate-900 border border-white/5 rounded-xl p-5">
                    <p class="text-slate-400 text-sm">{{ card.label }}</p>
                    <p class="text-2xl font-bold text-white mt-1">{{ card.value }}</p>
                    <p :class="card.negative ? 'text-red-400' : 'text-emerald-400'" class="text-xs mt-1 font-medium">
                        {{ card.trend }} vs last month
                    </p>
                </div>
            </div>

            <!-- Tabs + content -->
            <div class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
                <!-- Tabs -->
                <div class="flex border-b border-white/5 px-6">
                    <button
                        v-for="tab in tabs"
                        :key="tab"
                        @click="activeTab = tab"
                        :class="activeTab === tab ? 'border-blue-500 text-white' : 'border-transparent text-slate-400 hover:text-slate-200'"
                        class="capitalize text-sm font-medium px-4 py-4 border-b-2 transition -mb-px"
                    >
                        {{ tab }}
                    </button>
                </div>

                <!-- Table -->
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-white">Generated Reports</h3>
                        <input
                            type="text"
                            placeholder="Search reports…"
                            class="bg-white/5 border border-white/10 rounded-lg px-3 py-1.5 text-sm text-slate-300 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-blue-500 w-52"
                        />
                    </div>
                    <table class="w-full text-sm">
                        <thead class="text-slate-400 border-b border-white/5">
                            <tr>
                                <th class="text-left py-3 font-medium">Report Name</th>
                                <th class="text-left py-3 font-medium">Generated</th>
                                <th class="text-left py-3 font-medium">Format</th>
                                <th class="text-left py-3 font-medium">Size</th>
                                <th class="text-left py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="row in reportRows" :key="row.name" class="hover:bg-white/2 transition">
                                <td class="py-4 font-medium text-white">{{ row.name }}</td>
                                <td class="py-4 text-slate-400">{{ row.generated }}</td>
                                <td class="py-4">
                                    <span :class="formatColor[row.format]" class="text-xs px-2 py-0.5 rounded-full">{{ row.format }}</span>
                                </td>
                                <td class="py-4 text-slate-400">{{ row.size }}</td>
                                <td class="py-4 flex items-center gap-3">
                                    <a href="#" class="text-blue-400 hover:text-blue-300 text-xs transition">Download</a>
                                    <a href="#" class="text-slate-500 hover:text-slate-300 text-xs transition">Delete</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
