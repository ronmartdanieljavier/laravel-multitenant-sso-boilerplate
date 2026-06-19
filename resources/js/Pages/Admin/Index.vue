<script setup>
import { Head } from '@inertiajs/vue3';

const stats = [
    { label: 'Total Users', value: '4,291', change: '+12%', up: true },
    { label: 'Active Apps', value: '18', change: '+2', up: true },
    { label: 'Active Clients', value: '134', change: '-3', up: false },
    { label: 'SSO Sessions', value: '9,820', change: '+8%', up: true },
];

const recentUsers = [
    { name: 'Alice Reyes', email: 'alice@acme.com', role: 'Admin', status: 'Active' },
    { name: 'Bob Santos', email: 'bob@globex.com', role: 'Client', status: 'Active' },
    { name: 'Carol Tan', email: 'carol@initech.com', role: 'Reports', status: 'Inactive' },
    { name: 'Dan Cruz', email: 'dan@umbrella.com', role: 'Admin', status: 'Active' },
];
</script>

<template>
    <Head title="Admin Dashboard" />

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
                <a v-for="item in ['Dashboard', 'Users', 'Apps', 'Clients', 'Settings']" :key="item"
                   href="#"
                   :class="item === 'Dashboard' ? 'bg-violet-600/20 text-violet-300' : 'text-slate-400 hover:text-slate-200 hover:bg-white/5'"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition">
                    {{ item }}
                </a>
            </nav>
            <div class="p-4 border-t border-white/5">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-violet-500 flex items-center justify-center text-xs font-bold text-white">A</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">Super Admin</p>
                        <p class="text-xs text-slate-400 truncate">admin@system.com</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <div class="ml-60">
            <!-- Header -->
            <header class="h-16 bg-slate-900/50 border-b border-white/5 flex items-center justify-between px-8">
                <h2 class="text-lg font-semibold">Dashboard</h2>
                <button class="bg-violet-600 hover:bg-violet-500 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition">
                    + Invite User
                </button>
            </header>

            <main class="p-8 space-y-8">
                <!-- Stats -->
                <div class="grid grid-cols-4 gap-4">
                    <div v-for="stat in stats" :key="stat.label"
                         class="bg-slate-900 border border-white/5 rounded-xl p-5">
                        <p class="text-slate-400 text-sm">{{ stat.label }}</p>
                        <p class="text-2xl font-bold text-white mt-1">{{ stat.value }}</p>
                        <p :class="stat.up ? 'text-emerald-400' : 'text-red-400'" class="text-xs mt-1 font-medium">
                            {{ stat.change }} from last month
                        </p>
                    </div>
                </div>

                <!-- Recent Users Table -->
                <div class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-white/5">
                        <h3 class="font-semibold text-white">Recent Users</h3>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="text-slate-400 border-b border-white/5">
                            <tr>
                                <th class="text-left px-6 py-3 font-medium">Name</th>
                                <th class="text-left px-6 py-3 font-medium">Email</th>
                                <th class="text-left px-6 py-3 font-medium">Role</th>
                                <th class="text-left px-6 py-3 font-medium">Status</th>
                                <th class="text-left px-6 py-3 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="user in recentUsers" :key="user.email" class="hover:bg-white/2 transition">
                                <td class="px-6 py-4 font-medium text-white">{{ user.name }}</td>
                                <td class="px-6 py-4 text-slate-400">{{ user.email }}</td>
                                <td class="px-6 py-4">
                                    <span class="bg-violet-500/20 text-violet-300 text-xs px-2 py-0.5 rounded-full">{{ user.role }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="user.status === 'Active' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-500/20 text-slate-400'"
                                          class="text-xs px-2 py-0.5 rounded-full">
                                        {{ user.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="#" class="text-violet-400 hover:text-violet-300 text-xs transition">Edit</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
</template>
