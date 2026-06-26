<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import TourButton from '../../Partials/TourButton.vue';
import { useTour } from '../../../composables/useTour';

const page = usePage();
const missingSettings = page.props.missingRequiredSettings ?? [];

function logout() {
    router.post('/logout');
}

const props = defineProps({
    tenants: Array,
    summary: Object,
});

// ── Summary cards ────────────────────────────────────────────────────────────

const summaryCards = [
    { label: 'Total Tenants', key: 'total', color: 'text-white' },
    { label: 'Healthy', key: 'healthy', color: 'text-emerald-400' },
    { label: 'Warning', key: 'warning', color: 'text-amber-400' },
    { label: 'Critical', key: 'critical', color: 'text-red-400' },
    { label: 'In Maintenance', key: 'maintenance', color: 'text-orange-400' },
];

const healthBadge = {
    healthy: 'bg-emerald-500/20 text-emerald-300',
    warning: 'bg-amber-500/20 text-amber-300',
    critical: 'bg-red-500/20 text-red-400',
};

const healthFilter = computed(() => new URLSearchParams(window.location.search).get('health') ?? null);

const filteredTenants = computed(() =>
    healthFilter.value
        ? props.tenants.filter(t => t.health_status === healthFilter.value)
        : props.tenants,
);

function clearHealthFilter() {
    router.get('/admin/tenants', {}, { preserveState: true, replace: true });
}

function formatDate(value) {
    if (!value) return 'Never';
    const d = new Date(value);
    const diff = Math.floor((Date.now() - d) / 86400000);
    if (diff === 0) return 'Today';
    if (diff === 1) return 'Yesterday';
    return `${diff}d ago`;
}

// ── Create modal ─────────────────────────────────────────────────────────────

const showCreateModal = ref(false);
const showCreateReadReplica = ref(false);

const createForm = useForm({
    name: '',
    slug: '',
    db_host: '',
    db_port: 5432,
    db_name: '',
    db_username: '',
    db_password: '',
    read_replica_host: '',
    read_replica_port: 5432,
    read_replica_username: '',
    read_replica_password: '',
});

function openCreate() {
    createForm.reset();
    createForm.clearErrors();
    showCreateReadReplica.value = false;
    showCreateModal.value = true;
}

function closeCreate() {
    showCreateModal.value = false;
}

function autoSlug() {
    createForm.slug = createForm.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

function submitCreate() {
    createForm.post('/admin/tenants', {
        onSuccess: () => { showCreateModal.value = false; },
    });
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.has('add')) {
        openCreate();
        const url = new URL(window.location.href);
        url.searchParams.delete('add');
        window.history.replaceState({}, '', url.toString());
    }
});

// ── Edit modal ────────────────────────────────────────────────────────────────

const showEditModal = ref(false);
const editingTenant = ref(null);
const showEditReadReplica = ref(false);

const editForm = useForm({
    name: '',
    slug: '',
    db_host: '',
    db_port: 5432,
    db_name: '',
    db_username: '',
    db_password: '',
    read_replica_host: '',
    read_replica_port: 5432,
    read_replica_username: '',
    read_replica_password: '',
});

function openEdit(tenant) {
    editingTenant.value = tenant;
    editForm.name = tenant.name;
    editForm.slug = tenant.slug;
    editForm.db_host = tenant.db_host ?? '';
    editForm.db_port = tenant.db_port ?? 5432;
    editForm.db_name = tenant.db_name ?? '';
    editForm.db_username = tenant.db_username ?? '';
    editForm.db_password = '';
    editForm.read_replica_host = '';
    editForm.read_replica_port = 5432;
    editForm.read_replica_username = '';
    editForm.read_replica_password = '';
    showEditReadReplica.value = tenant.has_read_replica;
    editForm.clearErrors();
    showEditModal.value = true;
}

function closeEdit() {
    showEditModal.value = false;
    editingTenant.value = null;
}

function submitEdit() {
    editForm.put(`/admin/tenants/${editingTenant.value.id}`, {
        onSuccess: () => { showEditModal.value = false; },
    });
}

// ── Toggle active ─────────────────────────────────────────────────────────────

function toggleActive(tenant) {
    const newState = !tenant.is_active;
    const confirmMsg = newState
        ? `Activate "${tenant.name}"?`
        : `Deactivate "${tenant.name}"? All user tokens for this tenant will be revoked.`;

    if (!confirm(confirmMsg)) return;

    router.patch(`/admin/tenants/${tenant.id}/active`, { is_active: newState });
}

// ── Maintenance mode ──────────────────────────────────────────────────────────

function toggleMaintenance(tenant) {
    const enabling = !tenant.is_maintenance;
    const confirmMsg = enabling
        ? `Enable maintenance mode for "${tenant.name}"? All user tokens for this tenant will be revoked and users will be logged out.`
        : `Disable maintenance mode for "${tenant.name}"?`;

    if (!confirm(confirmMsg)) return;

    router.patch(`/admin/tenants/${tenant.id}/maintenance`, { is_maintenance: enabling });
}

function toggleMaintenanceAll(enable) {
    const confirmMsg = enable
        ? 'Enable maintenance mode for ALL tenants? All user tokens will be revoked and users will be logged out.'
        : 'Disable maintenance mode for all tenants?';

    if (!confirm(confirmMsg)) return;

    router.patch('/admin/tenants/maintenance/all', { is_maintenance: enable });
}

// ── Run migrations ────────────────────────────────────────────────────────────

function migrateTenant(tenant) {
    if (!confirm(`Run migrations for "${tenant.name}"?`)) return;
    router.post(`/admin/tenants/${tenant.id}/migrate`);
}

function migrateAll() {
    if (!confirm('Run migrations for ALL tenants?')) return;
    router.post('/admin/tenants/migrate-all');
}

// ── Delete ────────────────────────────────────────────────────────────────────

function deleteTenant(tenant) {
    if (!confirm(`Delete "${tenant.name}"? This will DROP the tenant database and remove all related records. This cannot be undone.`)) return;
    router.delete(`/admin/tenants/${tenant.id}`);
}

const { startTour } = useTour('admin-tenants', [
    {
        element: '#tour-tenants-header',
        title: 'Tenant Management',
        description: 'Manage all tenants in the system. Use "Add Tenant" to provision a new tenant database, or run migrations across all tenants at once.',
    },
    {
        element: '#tour-tenants-summary',
        title: 'Health Summary',
        description: 'A quick overview of all tenants by health status. Click a status card to filter the table below.',
        side: 'bottom',
    },
    {
        element: '#tour-tenants-table',
        title: 'Tenants Table',
        description: 'Each row is a tenant. You can see their health status, user count, pending migrations, and report queue. Click the tenant name to view detailed settings, users, and error logs.',
        side: 'top',
    },
]);
</script>

<template>
    <Head title="Tenant Management" />

    <div class="min-h-screen bg-slate-950 text-slate-100">
        <!-- Sidebar -->
        <aside class="fixed inset-y-0 left-0 w-60 bg-slate-900 border-r border-white/5 flex flex-col">
            <div class="h-16 flex items-center px-6 border-b border-white/5">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <span class="font-semibold text-white">SSO Admin</span>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1">
                <Link href="/admin"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Dashboard
                </Link>
                <Link href="/admin/users"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Users
                </Link>
                <Link href="/admin/apps"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Apps
                </Link>
                <Link href="/admin/tenants"
                      class="flex items-center gap-3 py-2 text-sm font-medium transition border-l-2 border-blue-500 rounded-r-lg pl-[10px] pr-3 text-white">
                    Tenants
                </Link>
                <Link href="/admin/settings"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Settings
                </Link>
            </nav>
            <!-- App selection -->
            <div class="px-3 pb-1 shrink-0">
                <Link href="/apps" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-white hover:bg-white/5 transition">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    App Selection
                </Link>
            </div>
            <div class="p-4 border-t border-white/5">
                <div class="flex items-center gap-3">
                    <Link href="/profile" class="flex items-center gap-3 flex-1 min-w-0 hover:opacity-80 transition">
                        <div v-if="page.props.auth.user?.profile_picture_url" class="w-8 h-8 rounded-full overflow-hidden shrink-0">
                            <img :src="page.props.auth.user.profile_picture_url" class="w-full h-full object-cover" alt="Profile" />
                        </div>
                        <div v-else class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-xs font-bold text-white shrink-0">
                            {{ page.props.auth.user?.name?.[0]?.toUpperCase() ?? 'A' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ page.props.auth.user?.name ?? 'Admin' }}</p>
                            <p class="text-xs text-slate-400 truncate">{{ page.props.auth.user?.email }}</p>
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

        <!-- Main -->
        <div class="ml-60">
            <!-- Missing settings banner -->
            <div v-if="missingSettings.length > 0"
                 class="bg-amber-500/10 border-b border-amber-500/20 px-8 py-3 flex items-center gap-3">
                <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                <p class="text-sm text-amber-300">
                    Required settings not configured:
                    <span class="font-medium">{{ missingSettings.join(', ') }}</span>.
                    <Link href="/admin/settings" class="underline hover:text-amber-200 ml-1">Go to Settings</Link>
                </p>
            </div>

            <!-- Flash success -->
            <div v-if="page.props.flash?.success"
                 class="bg-emerald-500/10 border-b border-emerald-500/20 px-8 py-3 flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <p class="text-sm text-emerald-300">{{ page.props.flash.success }}</p>
            </div>

            <!-- Header -->
            <header id="tour-tenants-header" class="h-16 bg-slate-900/50 border-b border-white/5 flex items-center justify-between px-8">
                <h2 class="text-lg font-semibold">Tenant Management</h2>
                <div class="flex items-center gap-3">
                    <button @click="migrateAll"
                            class="text-sm px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 hover:text-white transition">
                        Run All Migrations
                    </button>
                    <button @click="toggleMaintenanceAll(true)"
                            class="text-sm px-3 py-1.5 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 transition">
                        Maintenance: All On
                    </button>
                    <button @click="toggleMaintenanceAll(false)"
                            class="text-sm px-3 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 transition">
                        Maintenance: All Off
                    </button>
                    <button @click="openCreate"
                            class="text-sm px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white transition">
                        + Add Tenant
                    </button>
                </div>
            </header>

            <main class="p-8 space-y-8">
                <!-- Summary Cards -->
                <div id="tour-tenants-summary" class="grid grid-cols-5 gap-4">
                    <div v-for="card in summaryCards" :key="card.key"
                         class="bg-slate-900 border border-white/5 rounded-xl p-5">
                        <p class="text-slate-400 text-sm">{{ card.label }}</p>
                        <p :class="card.color" class="text-2xl font-bold mt-1">{{ summary[card.key] }}</p>
                    </div>
                </div>

                <!-- Tenants Table -->
                <div id="tour-tenants-table" class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                        <h3 class="font-semibold text-white">
                            All Tenants
                            <span v-if="healthFilter" class="ml-2 text-xs font-normal text-slate-400">
                                — filtered by
                            </span>
                        </h3>
                        <button v-if="healthFilter"
                                @click="clearHealthFilter"
                                :class="{
                                    'bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30': healthFilter === 'healthy',
                                    'bg-amber-500/20 text-amber-300 hover:bg-amber-500/30': healthFilter === 'warning',
                                    'bg-red-500/20 text-red-400 hover:bg-red-500/30': healthFilter === 'critical',
                                }"
                                class="flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full capitalize transition">
                            {{ healthFilter }}
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="text-slate-400 border-b border-white/5">
                            <tr>
                                <th class="text-left px-6 py-3 font-medium">Tenant</th>
                                <th class="text-left px-6 py-3 font-medium">Status</th>
                                <th class="text-left px-6 py-3 font-medium">Health</th>
                                <th class="text-left px-6 py-3 font-medium">Users</th>
                                <th class="text-left px-6 py-3 font-medium">Migrations</th>
                                <th class="text-left px-6 py-3 font-medium">Reports (P/F)</th>
                                <th class="text-left px-6 py-3 font-medium">Read Replica</th>
                                <th class="text-left px-6 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="tenant in filteredTenants" :key="tenant.id" class="hover:bg-white/2 transition">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-white">{{ tenant.name }}</p>
                                    <p class="text-xs text-slate-500">{{ tenant.slug }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <span :class="tenant.is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-500/20 text-slate-400'"
                                              class="text-xs px-2 py-0.5 rounded-full w-fit">
                                            {{ tenant.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                        <span v-if="tenant.is_maintenance"
                                              class="text-xs px-2 py-0.5 rounded-full w-fit bg-amber-500/20 text-amber-300">
                                            Maintenance
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="healthBadge[tenant.health_status]"
                                          class="text-xs px-2 py-0.5 rounded-full capitalize">
                                        {{ tenant.health_status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-slate-300">{{ tenant.user_count }} total</span>
                                        <span class="inline-flex items-center gap-1 text-xs"
                                              :class="tenant.logged_in_count > 0 ? 'text-emerald-400' : 'text-slate-600'">
                                            <span class="w-1.5 h-1.5 rounded-full inline-block"
                                                  :class="tenant.logged_in_count > 0 ? 'bg-emerald-400' : 'bg-slate-600'"></span>
                                            {{ tenant.logged_in_count }} online
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-400">{{ tenant.migration_count }}</td>
                                <td class="px-6 py-4">
                                    <span class="text-slate-400">{{ tenant.pending_reports }}</span>
                                    <span class="text-slate-600 mx-1">/</span>
                                    <span :class="tenant.failed_reports > 0 ? 'text-red-400' : 'text-slate-400'">{{ tenant.failed_reports }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="tenant.has_read_replica ? 'bg-blue-600/20 text-blue-300' : 'bg-slate-500/20 text-slate-500'"
                                          class="text-xs px-2 py-0.5 rounded-full">
                                        {{ tenant.has_read_replica ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1.5">
                                        <!-- Navigation links -->
                                        <div class="flex items-center gap-1">
                                            <button @click="openEdit(tenant)"
                                                    class="text-xs px-2 py-1 rounded bg-slate-700 hover:bg-slate-600 text-slate-300 hover:text-white transition">
                                                Edit
                                            </button>
                                            <Link :href="`/admin/tenants/${tenant.id}/settings`"
                                                  class="text-xs px-2 py-1 rounded bg-blue-600/20 text-blue-300 hover:bg-blue-500/30 transition">
                                                Settings
                                            </Link>
                                            <Link :href="`/admin/tenants/${tenant.id}/users`"
                                                  class="text-xs px-2 py-1 rounded bg-slate-700 hover:bg-slate-600 text-slate-300 hover:text-white transition">
                                                Users
                                            </Link>
                                            <Link :href="`/admin/tenants/${tenant.id}/reports`"
                                                  class="text-xs px-2 py-1 rounded bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 transition">
                                                Reports
                                            </Link>
                                            <Link :href="`/admin/tenants/${tenant.id}/errors`"
                                                  class="text-xs px-2 py-1 rounded bg-red-500/20 text-red-400 hover:bg-red-500/30 transition">
                                                Errors
                                            </Link>
                                        </div>
                                        <!-- Admin actions -->
                                        <div class="flex items-center gap-1">
                                            <button @click="migrateTenant(tenant)"
                                                    class="text-xs px-2 py-1 rounded bg-slate-700 hover:bg-slate-600 text-slate-300 hover:text-white transition">
                                                Migrate
                                            </button>
                                            <button @click="toggleActive(tenant)"
                                                    :class="tenant.is_active
                                                        ? 'bg-amber-500/20 text-amber-300 hover:bg-amber-500/30'
                                                        : 'bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30'"
                                                    class="text-xs px-2 py-1 rounded transition">
                                                {{ tenant.is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                            <button @click="toggleMaintenance(tenant)"
                                                    :class="tenant.is_maintenance
                                                        ? 'bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30'
                                                        : 'bg-amber-500/20 text-amber-300 hover:bg-amber-500/30'"
                                                    class="text-xs px-2 py-1 rounded transition">
                                                {{ tenant.is_maintenance ? 'End Maint.' : 'Maintenance' }}
                                            </button>
                                            <button @click="deleteTenant(tenant)"
                                                    class="text-xs px-2 py-1 rounded bg-red-500/20 text-red-400 hover:bg-red-500/30 transition">
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="tenants.length === 0">
                                <td colspan="8" class="px-6 py-8 text-center text-slate-500">No tenants found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreateModal"
         class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
        <div class="bg-slate-900 border border-white/10 rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
                <h3 class="font-semibold text-white">Add Tenant</h3>
                <button @click="closeCreate" class="text-slate-400 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form @submit.prevent="submitCreate" class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Name *</label>
                        <input v-model="createForm.name" @input="autoSlug"
                               type="text" placeholder="Acme Corp"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        <p v-if="createForm.errors.name" class="text-red-400 text-xs mt-1">{{ createForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Slug *</label>
                        <input v-model="createForm.slug"
                               type="text" placeholder="acme-corp"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        <p v-if="createForm.errors.slug" class="text-red-400 text-xs mt-1">{{ createForm.errors.slug }}</p>
                    </div>
                </div>

                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide pt-2">Database Connection</p>
                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <label class="block text-xs text-slate-400 mb-1">Host</label>
                        <input v-model="createForm.db_host"
                               type="text" placeholder="127.0.0.1"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        <p v-if="createForm.errors.db_host" class="text-red-400 text-xs mt-1">{{ createForm.errors.db_host }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Port</label>
                        <input v-model="createForm.db_port"
                               type="number" placeholder="5432"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        <p v-if="createForm.errors.db_port" class="text-red-400 text-xs mt-1">{{ createForm.errors.db_port }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Database Name</label>
                        <input v-model="createForm.db_name"
                               type="text" placeholder="tenant_acme"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        <p v-if="createForm.errors.db_name" class="text-red-400 text-xs mt-1">{{ createForm.errors.db_name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Username</label>
                        <input v-model="createForm.db_username"
                               type="text"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        <p v-if="createForm.errors.db_username" class="text-red-400 text-xs mt-1">{{ createForm.errors.db_username }}</p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Password</label>
                    <input v-model="createForm.db_password"
                           type="password"
                           class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                    <p v-if="createForm.errors.db_password" class="text-red-400 text-xs mt-1">{{ createForm.errors.db_password }}</p>
                </div>

                <!-- Read Replica toggle -->
                <button type="button" @click="showCreateReadReplica = !showCreateReadReplica"
                        class="text-xs text-blue-400 hover:text-blue-300 transition flex items-center gap-1">
                    <svg :class="showCreateReadReplica ? 'rotate-90' : ''" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    {{ showCreateReadReplica ? 'Hide' : 'Add' }} Read Replica
                </button>

                <div v-if="showCreateReadReplica" class="space-y-4 border border-white/5 rounded-lg p-4">
                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Read Replica</p>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <label class="block text-xs text-slate-400 mb-1">Host</label>
                            <input v-model="createForm.read_replica_host" type="text"
                                   class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Port</label>
                            <input v-model="createForm.read_replica_port" type="number"
                                   class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Username</label>
                            <input v-model="createForm.read_replica_username" type="text"
                                   class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Password</label>
                            <input v-model="createForm.read_replica_password" type="password"
                                   class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="closeCreate"
                            class="px-4 py-2 text-sm rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 hover:text-white transition">
                        Cancel
                    </button>
                    <button type="submit" :disabled="createForm.processing"
                            class="px-4 py-2 text-sm rounded-lg bg-blue-600 hover:bg-blue-500 text-white transition disabled:opacity-50">
                        {{ createForm.processing ? 'Creating…' : 'Create Tenant' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="showEditModal"
         class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
        <div class="bg-slate-900 border border-white/10 rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
                <h3 class="font-semibold text-white">Edit Tenant</h3>
                <button @click="closeEdit" class="text-slate-400 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Name *</label>
                        <input v-model="editForm.name" type="text"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        <p v-if="editForm.errors.name" class="text-red-400 text-xs mt-1">{{ editForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Slug *</label>
                        <input v-model="editForm.slug" type="text"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        <p v-if="editForm.errors.slug" class="text-red-400 text-xs mt-1">{{ editForm.errors.slug }}</p>
                    </div>
                </div>

                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide pt-2">Database Connection</p>
                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <label class="block text-xs text-slate-400 mb-1">Host</label>
                        <input v-model="editForm.db_host" type="text"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        <p v-if="editForm.errors.db_host" class="text-red-400 text-xs mt-1">{{ editForm.errors.db_host }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Port</label>
                        <input v-model="editForm.db_port" type="number"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        <p v-if="editForm.errors.db_port" class="text-red-400 text-xs mt-1">{{ editForm.errors.db_port }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Database Name</label>
                        <input v-model="editForm.db_name" type="text"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        <p v-if="editForm.errors.db_name" class="text-red-400 text-xs mt-1">{{ editForm.errors.db_name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Username</label>
                        <input v-model="editForm.db_username" type="text"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        <p v-if="editForm.errors.db_username" class="text-red-400 text-xs mt-1">{{ editForm.errors.db_username }}</p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">
                        Password
                        <span v-if="editingTenant?.is_password_set" class="text-slate-500 ml-1">(leave blank to keep existing)</span>
                    </label>
                    <input v-model="editForm.db_password" type="password"
                           class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                    <p v-if="editForm.errors.db_password" class="text-red-400 text-xs mt-1">{{ editForm.errors.db_password }}</p>
                </div>

                <!-- Read Replica toggle -->
                <button type="button" @click="showEditReadReplica = !showEditReadReplica"
                        class="text-xs text-blue-400 hover:text-blue-300 transition flex items-center gap-1">
                    <svg :class="showEditReadReplica ? 'rotate-90' : ''" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    {{ showEditReadReplica ? 'Hide' : 'Edit' }} Read Replica
                </button>

                <div v-if="showEditReadReplica" class="space-y-4 border border-white/5 rounded-lg p-4">
                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Read Replica</p>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <label class="block text-xs text-slate-400 mb-1">Host</label>
                            <input v-model="editForm.read_replica_host" type="text"
                                   class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Port</label>
                            <input v-model="editForm.read_replica_port" type="number"
                                   class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Username</label>
                            <input v-model="editForm.read_replica_username" type="text"
                                   class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Password <span class="text-slate-500">(blank = keep existing)</span></label>
                            <input v-model="editForm.read_replica_password" type="password"
                                   class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="closeEdit"
                            class="px-4 py-2 text-sm rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 hover:text-white transition">
                        Cancel
                    </button>
                    <button type="submit" :disabled="editForm.processing"
                            class="px-4 py-2 text-sm rounded-lg bg-blue-600 hover:bg-blue-500 text-white transition disabled:opacity-50">
                        {{ editForm.processing ? 'Saving…' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <TourButton @click="startTour" />
</template>
