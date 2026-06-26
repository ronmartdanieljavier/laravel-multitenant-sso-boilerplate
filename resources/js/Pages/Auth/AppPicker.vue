<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { useIdleTimeout } from '../../composables/useIdleTimeout';

const page = usePage();
useIdleTimeout(page.props.idleTimeoutMinutes);

const props = defineProps({
    apps: Array,
    user: Object,
});

const appIcons = {
    admin: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />`,
    tenant: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />`,
};

const appColors = {
    admin: 'bg-blue-700',
    tenant: 'bg-blue-600',
};

function select(slug) {
    router.post('/apps/select', { slug });
}
</script>

<template>
    <Head title="Choose App" />

    <div class="min-h-screen bg-slate-950 flex items-center justify-center p-4">
        <div class="w-full max-w-lg">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-blue-600 rounded-xl mb-5">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-semibold text-white tracking-tight">Welcome, {{ user.name }}</h1>
                <p class="text-slate-500 mt-1 text-sm">Choose an app to continue</p>
            </div>

            <!-- App Cards -->
            <div :class="apps.length === 1 ? 'grid-cols-1 max-w-xs mx-auto' : 'grid-cols-2'" class="grid gap-3">
                <button
                    v-for="app in apps"
                    :key="app.slug"
                    @click="select(app.slug)"
                    class="group bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 rounded-xl p-5 text-left transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <div :class="appColors[app.slug] ?? 'bg-slate-700'" class="w-10 h-10 rounded-lg flex items-center justify-center mb-4 transition group-hover:scale-105">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="appIcons[app.slug] ?? appIcons.tenant" />
                    </div>
                    <h2 class="text-white font-medium text-base">{{ app.name }}</h2>
                    <p class="text-slate-500 text-xs mt-0.5 capitalize">{{ app.role }}</p>
                    <div class="flex items-center gap-1 mt-4 text-blue-400 text-xs font-medium opacity-0 group-hover:opacity-100 transition">
                        Open
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </button>
            </div>

            <p class="text-center text-slate-600 text-xs mt-7">
                Signed in as <span class="text-slate-500">{{ user.email }}</span>
                &mdash;
                <button @click="router.post('/logout')" class="text-slate-500 hover:text-slate-300 underline transition">Sign out</button>
            </p>
        </div>
    </div>
</template>
