<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const missingSettings = computed(() => page.props.missingRequiredSettings ?? []);
const currentPath = computed(() => page.url.split('?')[0]);

function isActive(href) {
    return currentPath.value === href || currentPath.value.startsWith(href + '/');
}

const nav = [
    {
        label: 'Dashboard',
        href: '/admin',
        icon: 'M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661z',
    },
    {
        label: 'Users',
        href: '/admin/users',
        icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    },
    {
        label: 'Apps',
        href: '/admin/apps',
        icon: 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
    },
    {
        label: 'Tenants',
        href: '/admin/tenants',
        icon: 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z',
    },
    {
        label: 'Jobs',
        href: '/admin/jobs',
        icon: 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z',
    },
    {
        label: 'Horizon',
        href: '/horizon',
        icon: 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
        external: true,
    },
    {
        label: 'Settings',
        href: '/admin/settings',
        icon: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    },
];

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="layout-root">
        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- Aurora orb -->
            <div class="aurora-orb aurora-orb--admin" aria-hidden="true"></div>

            <!-- Brand -->
            <div class="sidebar-brand">
                <div class="brand-logo brand-logo--admin">
                    <svg class="brand-logo-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div>
                    <p class="brand-name font-grotesk">SSO Admin</p>
                    <p class="brand-subtitle font-mono">Management Console</p>
                </div>
            </div>

            <!-- Nav -->
            <nav class="sidebar-nav">
                <template v-for="item in nav" :key="item.href">
                    <a
                        v-if="item.external"
                        :href="item.href"
                        class="nav-item font-sans nav-item--inactive"
                    >
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="item.icon" />
                        </svg>
                        {{ item.label }}
                    </a>
                    <Link
                        v-else
                        :href="item.href"
                        :class="['nav-item font-sans', isActive(item.href) ? 'nav-item--active-admin' : 'nav-item--inactive']"
                    >
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="item.icon" />
                        </svg>
                        {{ item.label }}
                    </Link>
                </template>
            </nav>

            <!-- Switch App -->
            <div class="sidebar-switch">
                <Link href="/apps" class="switch-link font-sans">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                    </svg>
                    Switch to App
                </Link>
            </div>

            <!-- User footer -->
            <div class="sidebar-footer">
                <div class="user-row">
                    <Link href="/profile" class="user-info">
                        <div v-if="user?.profile_picture_url" class="avatar">
                            <img :src="user.profile_picture_url" class="w-full h-full object-cover" alt="Profile" />
                        </div>
                        <div v-else class="avatar avatar--admin font-grotesk">
                            {{ user?.name?.[0]?.toUpperCase() ?? 'A' }}
                        </div>
                        <div class="user-text">
                            <p class="user-name font-sans">{{ user?.name ?? 'Admin' }}</p>
                            <p class="user-email font-mono">{{ user?.email }}</p>
                        </div>
                    </Link>
                    <button @click="logout" title="Sign out" class="logout-btn">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Content area -->
        <div class="content-area">
            <!-- Missing settings banner -->
            <div v-if="missingSettings.length > 0" class="missing-settings-banner">
                <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                <p class="text-sm text-amber-300 font-sans">
                    Required settings not configured:
                    <span class="font-medium">{{ missingSettings.join(', ') }}</span>.
                    <Link href="/admin/settings" class="underline hover:text-amber-200 ml-1">Configure now</Link>
                </p>
            </div>
            <slot />
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@400;500&family=DM+Mono:wght@400;500&display=swap');

.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-sans { font-family: 'DM Sans', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }

/* ── Root ── */
.layout-root {
    min-height: 100vh;
    background-color: #030712;
    color: #f1f5f9;
    display: flex;
}

/* ── Sidebar ── */
.sidebar {
    position: fixed;
    inset-block: 0;
    left: 0;
    width: 240px;
    background: rgba(255, 255, 255, 0.02);
    border-right: 1px solid rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(24px);
    display: flex;
    flex-direction: column;
    z-index: 20;
    overflow: hidden;
}

/* Aurora orb */
.aurora-orb {
    position: absolute;
    width: 320px;
    height: 320px;
    border-radius: 50%;
    pointer-events: none;
    filter: blur(80px);
    opacity: 0.18;
    top: -100px;
    left: -80px;
}
.aurora-orb--admin {
    background: radial-gradient(circle, #3b82f6, #6d28d9);
}

/* Brand */
.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 20px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    flex-shrink: 0;
    position: relative;
}
.brand-logo {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.brand-logo--admin {
    background: linear-gradient(135deg, #3b82f6, #6d28d9);
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3);
}
.brand-logo-icon {
    width: 18px;
    height: 18px;
    color: white;
}
.brand-name {
    font-size: 14px;
    font-weight: 700;
    color: white;
    line-height: 1;
    letter-spacing: -0.01em;
}
.brand-subtitle {
    font-size: 10px;
    color: #64748b;
    margin-top: 3px;
    line-height: 1;
    letter-spacing: 0.02em;
}

/* Nav */
.sidebar-nav {
    flex: 1;
    padding: 12px 10px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    overflow-y: auto;
    position: relative;
}
.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.15s ease;
    text-decoration: none;
    border: 1px solid transparent;
}
.nav-item--active-admin {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.2);
    color: #93c5fd;
}
.nav-item--inactive {
    color: #64748b;
}
.nav-item--inactive:hover {
    background: rgba(255, 255, 255, 0.04);
    color: #cbd5e1;
    border-color: rgba(255, 255, 255, 0.04);
}
.nav-icon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

/* Switch link */
.sidebar-switch {
    padding: 6px 10px;
    flex-shrink: 0;
}
.switch-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 500;
    color: #475569;
    text-decoration: none;
    transition: all 0.15s ease;
}
.switch-link:hover {
    background: rgba(255, 255, 255, 0.04);
    color: #94a3b8;
}

/* Footer */
.sidebar-footer {
    padding: 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    flex-shrink: 0;
}
.user-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
    padding: 6px 8px;
    border-radius: 10px;
    text-decoration: none;
    transition: background 0.15s ease;
}
.user-info:hover {
    background: rgba(255, 255, 255, 0.04);
}
.avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    color: white;
    outline: 2px solid rgba(255, 255, 255, 0.1);
}
.avatar--admin {
    background: rgba(59, 130, 246, 0.7);
}
.user-text {
    flex: 1;
    min-width: 0;
}
.user-name {
    font-size: 12px;
    font-weight: 500;
    color: #cbd5e1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    line-height: 1.2;
}
.user-email {
    font-size: 10px;
    color: #475569;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-top: 2px;
    letter-spacing: 0.01em;
}
.logout-btn {
    padding: 6px;
    border-radius: 8px;
    color: #475569;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.logout-btn:hover {
    background: rgba(255, 255, 255, 0.05);
    color: #cbd5e1;
}

/* Content area */
.content-area {
    margin-left: 240px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    background-color: #030712;
    overflow: auto;
}

/* Missing settings banner */
.missing-settings-banner {
    background: rgba(245, 158, 11, 0.08);
    border-bottom: 1px solid rgba(245, 158, 11, 0.2);
    padding: 10px 32px;
    display: flex;
    align-items: center;
    gap: 10px;
}
</style>
