<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useIdleTimeout } from '../../composables/useIdleTimeout';

const page = usePage();
useIdleTimeout(page.props.idleTimeoutMinutes);

function logout() {
    router.post('/logout');
}

const apps = [
    { name: 'Admin Portal', description: 'Manage users, settings, and system configurations.', icon: '🛡️', color: 'violet', href: '#' },
    { name: 'Reports Suite', description: 'View analytics, generate reports, and export data.', icon: '📊', color: 'blue', href: '#' },
    { name: 'Tenant Hub', description: 'Access client records and manage your account.', icon: '🏢', color: 'emerald', href: '#' },
    { name: 'Billing', description: 'Review invoices, subscriptions, and payment history.', icon: '💳', color: 'amber', href: '#' },
];

const colorMap = {
    violet: 'bg-violet-500/10 border-violet-500/20 hover:border-violet-400/40',
    blue: 'bg-blue-500/10 border-blue-500/20 hover:border-blue-400/40',
    emerald: 'bg-emerald-500/10 border-emerald-500/20 hover:border-emerald-400/40',
    amber: 'bg-amber-500/10 border-amber-500/20 hover:border-amber-400/40',
};
</script>

<template>
    <Head title="Tenant Portal" />

    <div class="min-h-screen bg-gradient-to-br from-slate-950 to-slate-900 text-slate-100">
        <!-- Top nav -->
        <header class="border-b border-white/5 bg-slate-900/70 backdrop-blur sticky top-0 z-10">
            <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <span class="font-semibold text-white">Tenant Portal</span>
                </div>
                <div class="flex items-center gap-4">
                    <Link href="/profile" class="flex items-center gap-2 hover:opacity-80 transition">
                        <span class="text-sm text-slate-400">{{ page.props.auth.user?.name }}</span>
                        <div v-if="page.props.auth.user?.profile_picture_url" class="w-8 h-8 rounded-full overflow-hidden shrink-0">
                            <img :src="page.props.auth.user.profile_picture_url" class="w-full h-full object-cover" alt="Profile" />
                        </div>
                        <div v-else class="w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center text-xs font-bold">
                            {{ page.props.auth.user?.name?.[0]?.toUpperCase() ?? 'U' }}
                        </div>
                    </Link>
                    <button @click="logout" title="Sign out" class="text-slate-400 hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-6 py-14">
            <!-- Welcome -->
            <div class="mb-12">
                <h1 class="text-3xl font-bold text-white">Good morning, Bob 👋</h1>
                <p class="text-slate-400 mt-2">Select an application to continue your session.</p>
            </div>

            <!-- App grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-14">
                <a
                    v-for="app in apps"
                    :key="app.name"
                    :href="app.href"
                    :class="colorMap[app.color]"
                    class="group relative border rounded-2xl p-6 transition-all duration-200 cursor-pointer"
                >
                    <div class="text-3xl mb-4">{{ app.icon }}</div>
                    <h3 class="font-semibold text-white text-lg mb-1">{{ app.name }}</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">{{ app.description }}</p>
                    <div class="absolute bottom-5 right-5 opacity-0 group-hover:opacity-100 transition text-slate-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>
            </div>

            <!-- Recent activity -->
            <div class="bg-slate-800/50 border border-white/5 rounded-2xl p-6">
                <h2 class="font-semibold text-white mb-5">Recent Activity</h2>
                <div class="space-y-4">
                    <div v-for="i in 3" :key="i" class="flex items-center gap-4 text-sm">
                        <div class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></div>
                        <span class="text-slate-300">Signed in via SSO</span>
                        <span class="text-slate-500 ml-auto">{{ i * 2 }}h ago</span>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
