<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useIdleTimeout } from '../composables/useIdleTimeout';
import TenantSwitcher from '../Pages/Partials/TenantSwitcher.vue';

const page = usePage();
const tenant = computed(() => page.props.tenant);
const user = computed(() => page.props.auth?.user);
const availableTenants = computed(() => page.props.availableTenants ?? []);

useIdleTimeout(page.props.idleTimeoutMinutes);

function logout() {
    router.post('/logout');
}

const nav = [
    {
        label: 'Dashboard',
        href: '/tenant',
        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    },
    {
        label: 'Report Queue',
        href: '/tenant/reports',
        icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    },
];

const currentPath = computed(() => page.url.split('?')[0]);

function isActive(href) {
    return currentPath.value === href;
}
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100 flex">
        <!-- Sidebar -->
        <aside class="fixed inset-y-0 left-0 w-60 bg-slate-900 border-r border-white/5 flex flex-col z-20">
            <!-- Logo / tenant name -->
            <div class="px-5 pt-5 pb-3 border-b border-white/5 shrink-0">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-white truncate">{{ tenant?.name ?? 'Tenant Portal' }}</p>
                        <p class="text-xs text-slate-500 truncate">{{ tenant?.slug }}</p>
                    </div>
                </div>

                <!-- Tenant switcher sits just below the tenant name -->
                <TenantSwitcher :tenants="availableTenants" />
            </div>

            <!-- Nav links -->
            <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
                <Link
                    v-for="item in nav"
                    :key="item.href"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition',
                        isActive(item.href)
                            ? 'bg-emerald-600/20 text-emerald-300'
                            : 'text-slate-400 hover:text-slate-200 hover:bg-white/5',
                    ]"
                >
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                    </svg>
                    {{ item.label }}
                </Link>
            </nav>

            <!-- User / sign-out -->
            <div class="p-3 border-t border-white/5 shrink-0">
                <div class="flex items-center gap-2 px-2 py-2">
                    <Link href="/profile" class="flex items-center gap-3 min-w-0 flex-1 group">
                        <div v-if="user?.profile_picture_url" class="w-8 h-8 rounded-full overflow-hidden shrink-0">
                            <img :src="user.profile_picture_url" class="w-full h-full object-cover" alt="Profile" />
                        </div>
                        <div v-else class="w-8 h-8 rounded-full bg-emerald-600 flex items-center justify-center text-xs font-bold text-white shrink-0">
                            {{ user?.name?.[0]?.toUpperCase() ?? 'U' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate group-hover:text-emerald-300 transition">{{ user?.name }}</p>
                            <p class="text-xs text-slate-400 truncate">{{ user?.email }}</p>
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

        <!-- Page content -->
        <div class="ml-60 flex-1 flex flex-col min-h-screen">
            <slot />
        </div>
    </div>
</template>
