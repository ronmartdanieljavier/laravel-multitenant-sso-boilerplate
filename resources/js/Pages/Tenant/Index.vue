<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import TenantLayout from '../../Layouts/TenantLayout.vue';
import TourButton from '../Partials/TourButton.vue';
import { useTour } from '../../composables/useTour';

defineOptions({ layout: TenantLayout });

const page = usePage();
const user = page.props.auth?.user;

const greeting = () => {
    const h = new Date().getHours();
    if (h < 12) { return 'Good morning'; }
    if (h < 18) { return 'Good afternoon'; }
    return 'Good evening';
};

const apps = [
    { name: 'Admin Portal', description: 'Manage users, settings, and system configurations.', icon: '🛡️', color: 'violet', href: '#' },
    { name: 'Reports Suite', description: 'View analytics, generate reports, and export data.', icon: '📊', color: 'blue', href: '/tenant/reports' },
    { name: 'Tenant Hub', description: 'Access client records and manage your account.', icon: '🏢', color: 'emerald', href: '#' },
    { name: 'Billing', description: 'Review invoices, subscriptions, and payment history.', icon: '💳', color: 'amber', href: '#' },
];

const { startTour } = useTour('tenant-dashboard', [
    {
        element: '#tour-welcome',
        title: 'Welcome to your Dashboard',
        description: 'This is your personal workspace. You\'ll find quick access to all your applications and recent activity here.',
    },
    {
        element: '#tour-app-grid',
        title: 'Your Applications',
        description: 'Click any application card to launch it. Each card describes what the application does. Applications you haven\'t been granted access to will appear inactive.',
        side: 'bottom',
    },
    {
        element: '#tour-recent-activity',
        title: 'Recent Activity',
        description: 'A log of your recent sign-ins and actions across this tenant. Use this to track your session history.',
        side: 'top',
    },
]);

const colorMap = {
    violet: 'bg-violet-500/10 border-violet-500/20 hover:border-violet-400/40',
    blue:   'bg-blue-500/10 border-blue-500/20 hover:border-blue-400/40',
    emerald:'bg-emerald-500/10 border-emerald-500/20 hover:border-emerald-400/40',
    amber:  'bg-amber-500/10 border-amber-500/20 hover:border-amber-400/40',
};
</script>

<template>
    <Head title="Dashboard" />

    <main class="flex-1 px-8 py-10">
        <!-- Welcome -->
        <div id="tour-welcome" class="mb-10">
            <h1 class="text-2xl font-bold text-white">{{ greeting() }}, {{ user?.name?.split(' ')[0] ?? 'there' }} 👋</h1>
            <p class="text-slate-400 mt-1 text-sm">Select an application to continue your session.</p>
        </div>

        <!-- App grid -->
        <div id="tour-app-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
            <a
                v-for="app in apps"
                :key="app.name"
                :href="app.href"
                :class="colorMap[app.color]"
                class="group relative border rounded-2xl p-5 transition-all duration-200 cursor-pointer"
            >
                <div class="text-3xl mb-3">{{ app.icon }}</div>
                <h3 class="font-semibold text-white mb-1">{{ app.name }}</h3>
                <p class="text-slate-400 text-xs leading-relaxed">{{ app.description }}</p>
                <div class="absolute bottom-4 right-4 opacity-0 group-hover:opacity-100 transition text-slate-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </a>
        </div>

        <!-- Recent activity -->
        <div id="tour-recent-activity" class="bg-slate-900 border border-white/5 rounded-2xl p-6">
            <h2 class="font-semibold text-white mb-5 text-sm">Recent Activity</h2>
            <div class="space-y-4">
                <div v-for="i in 3" :key="i" class="flex items-center gap-4 text-sm">
                    <div class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></div>
                    <span class="text-slate-300">Signed in via SSO</span>
                    <span class="text-slate-500 ml-auto">{{ i * 2 }}h ago</span>
                </div>
            </div>
        </div>
    </main>

    <TourButton @click="startTour" />
</template>
