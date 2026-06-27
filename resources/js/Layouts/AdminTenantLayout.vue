<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const tenant = computed(() => page.props.tenant);

function logout() {
    router.post('/logout');
}

const tenantNav = computed(() => {
    const id = tenant.value?.id;
    if (!id) { return []; }
    return [
        { label: 'Settings', href: `/admin/tenants/${id}/settings` },
        { label: 'Users',    href: `/admin/tenants/${id}/users`    },
        { label: 'Reports',  href: `/admin/tenants/${id}/reports`  },
        { label: 'Errors',   href: `/admin/tenants/${id}/errors`   },
        { label: 'Jobs',     href: `/admin/tenants/${id}/jobs`     },
    ];
});

const currentPath = computed(() => page.url.split('?')[0]);

function isActive(href) {
    return currentPath.value === href || currentPath.value.startsWith(href + '/');
}

</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100 flex">
        <!-- Main admin sidebar -->
        <aside class="fixed inset-y-0 left-0 w-60 bg-slate-900 border-r border-white/5 flex flex-col z-20">
            <div class="h-16 flex items-center px-6 border-b border-white/5 shrink-0">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center mr-3 shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <span class="font-semibold text-white">SSO Admin</span>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <Link href="/admin" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">Dashboard</Link>
                <Link href="/admin/users" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">Users</Link>
                <Link href="/admin/apps" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">Apps</Link>
                <Link href="/admin/tenants" class="flex items-center gap-3 py-2 text-sm font-medium transition border-l-2 border-blue-500 rounded-r-lg pl-[10px] pr-3 text-white">Tenants</Link>
                <Link href="/admin/settings" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">Settings</Link>
            </nav>
            <div class="p-3 border-t border-white/5 shrink-0 space-y-1">
                <Link href="/apps" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-white hover:bg-white/5 transition">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    App Selection
                </Link>
                <button @click="logout" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-white hover:bg-white/5 transition">
                    Sign out
                </button>
            </div>
        </aside>

        <!-- Content area -->
        <div class="ml-60 flex-1 flex flex-col min-h-screen">
            <!-- Tenant sub-nav bar -->
            <div v-if="tenant" class="sticky top-0 z-10 bg-slate-900/95 backdrop-blur border-b border-white/5">
                <div class="px-8 h-12 flex items-center gap-1">
                    <Link href="/admin/tenants" class="text-xs text-slate-500 hover:text-slate-300 transition mr-2">
                        ← Tenants
                    </Link>
                    <span class="text-slate-700 text-xs mr-3">{{ tenant.name }}</span>
                    <div class="h-4 w-px bg-white/10 mr-3"></div>
                    <Link
                        v-for="item in tenantNav"
                        :key="item.href"
                        :href="item.href"
                        :class="[
                            'text-xs font-medium transition',
                            isActive(item.href)
                                ? 'px-3 py-1.5 text-white border-b-2 border-blue-500'
                                : 'px-3 py-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5',
                        ]"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </div>

            <!-- Page content -->
            <slot />
        </div>
    </div>
</template>
