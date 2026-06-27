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
    admin: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    tenant: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
};

const appGradients = {
    admin: 'linear-gradient(135deg, #2563eb, #7c3aed)',
    tenant: 'linear-gradient(135deg, #10b981, #0d9488)',
};

const appGlows = {
    admin: '0 0 24px rgba(99,102,241,0.3)',
    tenant: '0 0 24px rgba(16,185,129,0.3)',
};

const appAccentColor = {
    admin: 'text-blue-400 group-hover:text-blue-300',
    tenant: 'text-emerald-400 group-hover:text-emerald-300',
};

function select(slug) {
    router.post('/apps/select', { slug });
}
</script>

<template>
    <Head title="Choose App" />

    <div class="min-h-screen bg-[#030712] flex items-center justify-center p-4 relative overflow-hidden">
        <!-- Ambient glow -->
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[600px] rounded-full blur-3xl opacity-30"
                 style="background: radial-gradient(ellipse, rgba(99,102,241,0.15) 0%, transparent 70%)" />
            <div class="absolute bottom-0 right-0 w-[400px] h-[400px] rounded-full blur-3xl opacity-20"
                 style="background: radial-gradient(ellipse, rgba(16,185,129,0.1) 0%, transparent 70%)" />
        </div>

        <div class="w-full max-w-lg relative">
            <!-- Brand header -->
            <div class="text-center mb-10">
                <div class="w-14 h-14 rounded-2xl mx-auto mb-5 flex items-center justify-center"
                     style="background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 0 32px rgba(99,102,241,0.4)">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                    </svg>
                </div>
                <h1 class="font-grotesk text-2xl font-bold text-white">Welcome, {{ user.name.split(' ')[0] }}</h1>
                <p class="text-slate-500 text-sm mt-1.5">Choose an application to continue</p>
            </div>

            <!-- App Cards -->
            <div :class="apps.length === 1 ? 'max-w-xs mx-auto' : 'grid grid-cols-2 gap-3'">
                <button
                    v-for="app in apps"
                    :key="app.slug"
                    @click="select(app.slug)"
                    class="group bg-white/[0.03] hover:bg-white/[0.05] border border-white/[0.06] hover:border-white/[0.12] rounded-2xl p-5 text-left transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500/30 cursor-pointer"
                    :class="apps.length === 1 ? 'w-full' : ''"
                >
                    <div
                        class="w-11 h-11 rounded-xl flex items-center justify-center mb-4 transition-all duration-200"
                        :style="{
                            background: appGradients[app.slug] ?? 'linear-gradient(135deg, #475569, #334155)',
                            boxShadow: appGlows[app.slug] ?? '0 0 16px rgba(71,85,105,0.2)',
                        }"
                    >
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="appIcons[app.slug] ?? appIcons.tenant" />
                        </svg>
                    </div>
                    <h2 class="font-grotesk text-white font-semibold text-base">{{ app.name }}</h2>
                    <p class="text-slate-500 text-xs mt-0.5 capitalize font-mono">{{ app.role }}</p>
                    <div class="flex items-center gap-1 mt-4 text-xs font-medium opacity-0 group-hover:opacity-100 transition-all duration-200"
                         :class="appAccentColor[app.slug] ?? 'text-slate-400'">
                        Open app
                        <svg class="w-3 h-3 translate-x-0 group-hover:translate-x-0.5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </button>
            </div>

            <p class="text-center text-slate-600 text-xs mt-8 font-mono">
                Signed in as <span class="text-slate-500">{{ user.email }}</span>
                &mdash;
                <button @click="router.post('/logout')" class="text-slate-500 hover:text-slate-300 underline transition cursor-pointer">Sign out</button>
            </p>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@400;500&family=DM+Mono:wght@400;500&display=swap');
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
