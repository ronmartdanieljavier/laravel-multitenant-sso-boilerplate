<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import TourButton from '../../Partials/TourButton.vue';
import { useTour } from '../../../composables/useTour';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

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
    editForm.read_replica_host = tenant.read_replica_host ?? '';
    editForm.read_replica_port = tenant.read_replica_port ?? 5432;
    editForm.read_replica_username = tenant.read_replica_username ?? '';
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

    <div class="flex flex-col flex-1">
        <!-- Flash success -->
        <div v-if="page.props.flash?.success"
             class="bg-emerald-500/10 border-b border-emerald-500/20 px-8 py-3 flex items-center gap-3">
            <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <p class="text-sm text-emerald-300">{{ page.props.flash.success }}</p>
        </div>

        <!-- Header -->
        <header id="tour-tenants-header" class="h-16 border-b border-white/[0.05] flex items-center px-8 shrink-0 bg-[#030712]">
            <h1 class="font-grotesk text-lg font-semibold text-white">Tenant Management</h1>
            <div class="ml-auto flex items-center gap-3">
                <button @click="migrateAll"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                    Run All Migrations
                </button>
                <button @click="toggleMaintenanceAll(true)"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-amber-400 bg-amber-500/[0.08] border border-amber-500/20 hover:bg-amber-500/15 cursor-pointer transition-all">
                    Maintenance: All On
                </button>
                <button @click="toggleMaintenanceAll(false)"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-emerald-400 bg-emerald-500/[0.08] border border-emerald-500/20 hover:bg-emerald-500/15 cursor-pointer transition-all">
                    Maintenance: All Off
                </button>
                <button @click="openCreate"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white cursor-pointer transition-all duration-150"
                        style="background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 0 20px rgba(99,102,241,0.25)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Tenant
                </button>
            </div>
        </header>

        <main class="p-8 space-y-6">
            <!-- Summary Cards -->
            <div id="tour-tenants-summary" class="grid grid-cols-5 gap-4">
                <div v-for="card in summaryCards" :key="card.key"
                     class="bg-white/[0.03] border border-white/[0.06] backdrop-blur-sm rounded-2xl p-5">
                    <p class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-2">{{ card.label }}</p>
                    <p :class="card.color" class="text-3xl font-semibold font-grotesk">{{ summary[card.key] }}</p>
                </div>
            </div>

            <!-- Tenants Table -->
            <div id="tour-tenants-table" class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-white/[0.06] flex items-center justify-between">
                    <h3 class="font-grotesk font-semibold text-white">
                        All Tenants
                        <span v-if="healthFilter" class="ml-2 text-xs font-normal text-slate-500">— filtered by</span>
                    </h3>
                    <button v-if="healthFilter"
                            @click="clearHealthFilter"
                            :class="{
                                'bg-emerald-500/10 border-emerald-500/20 text-emerald-300 hover:bg-emerald-500/20': healthFilter === 'healthy',
                                'bg-amber-500/10 border-amber-500/20 text-amber-300 hover:bg-amber-500/20': healthFilter === 'warning',
                                'bg-red-500/10 border-red-500/20 text-red-400 hover:bg-red-500/20': healthFilter === 'critical',
                            }"
                            class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full border capitalize transition cursor-pointer">
                        {{ healthFilter }}
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/[0.06]">
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Tenant</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Health</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Users</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Migrations</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Reports P/F</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Read Replica</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <tr v-for="tenant in filteredTenants" :key="tenant.id" class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-white">{{ tenant.name }}</p>
                                <p class="text-xs font-mono text-slate-500 mt-0.5">{{ tenant.slug }}</p>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-col gap-1">
                                    <span :class="tenant.is_active
                                        ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300'
                                        : 'bg-slate-500/10 border-slate-500/20 text-slate-400'"
                                          class="inline-flex items-center text-xs font-mono px-2 py-0.5 rounded-full border w-fit">
                                        {{ tenant.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                    <span v-if="tenant.is_maintenance"
                                          class="inline-flex items-center text-xs font-mono px-2 py-0.5 rounded-full border border-amber-500/20 bg-amber-500/10 text-amber-300 w-fit">
                                        Maintenance
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span :class="healthBadge[tenant.health_status]"
                                      class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full capitalize"
                                      :style="tenant.health_status === 'healthy' ? 'background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2)' :
                                              tenant.health_status === 'warning' ? 'background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2)' :
                                              'background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2)'">
                                    {{ tenant.health_status }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="text-slate-300 text-sm">{{ tenant.user_count }}</p>
                                <span class="inline-flex items-center gap-1 text-xs mt-0.5"
                                      :class="tenant.logged_in_count > 0 ? 'text-emerald-400' : 'text-slate-600'">
                                    <span class="w-1.5 h-1.5 rounded-full inline-block"
                                          :class="tenant.logged_in_count > 0 ? 'bg-emerald-400' : 'bg-slate-600'"></span>
                                    {{ tenant.logged_in_count }} online
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-400 font-mono text-xs">{{ tenant.migration_count }}</td>
                            <td class="px-5 py-3.5 font-mono text-xs">
                                <span class="text-slate-400">{{ tenant.pending_reports }}</span>
                                <span class="text-slate-600 mx-1">/</span>
                                <span :class="tenant.failed_reports > 0 ? 'text-red-400' : 'text-slate-400'">{{ tenant.failed_reports }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span :class="tenant.has_read_replica
                                    ? 'bg-blue-500/10 border-blue-500/20 text-blue-300'
                                    : 'bg-slate-500/10 border-slate-500/20 text-slate-500'"
                                      class="inline-flex items-center text-xs font-mono px-2 py-0.5 rounded-full border">
                                    {{ tenant.has_read_replica ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-col gap-2">
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <button @click="openEdit(tenant)"
                                                class="text-xs px-2 py-1 rounded-md bg-white/[0.04] border border-white/[0.08] text-slate-300 hover:bg-white/[0.07] transition cursor-pointer">
                                            Edit
                                        </button>
                                        <Link :href="`/admin/tenants/${tenant.id}/settings`"
                                              class="text-xs px-2 py-1 rounded-md bg-blue-500/10 border border-blue-500/20 text-blue-300 hover:bg-blue-500/20 transition">
                                            Settings
                                        </Link>
                                        <Link :href="`/admin/tenants/${tenant.id}/users`"
                                              class="text-xs px-2 py-1 rounded-md bg-white/[0.04] border border-white/[0.08] text-slate-300 hover:bg-white/[0.07] transition">
                                            Users
                                        </Link>
                                        <Link :href="`/admin/tenants/${tenant.id}/reports`"
                                              class="text-xs px-2 py-1 rounded-md bg-violet-500/10 border border-violet-500/20 text-violet-300 hover:bg-violet-500/20 transition">
                                            Reports
                                        </Link>
                                        <Link :href="`/admin/tenants/${tenant.id}/errors`"
                                              class="text-xs px-2 py-1 rounded-md bg-red-500/10 border border-red-500/20 text-red-400 hover:bg-red-500/20 transition">
                                            Errors
                                        </Link>
                                    </div>
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <button @click="migrateTenant(tenant)"
                                                class="text-xs px-2 py-1 rounded-md bg-white/[0.04] border border-white/[0.08] text-slate-300 hover:bg-white/[0.07] transition cursor-pointer">
                                            Migrate
                                        </button>
                                        <button @click="toggleActive(tenant)"
                                                :class="tenant.is_active
                                                    ? 'bg-amber-500/[0.08] border-amber-500/20 text-amber-300 hover:bg-amber-500/15'
                                                    : 'bg-emerald-500/[0.08] border-emerald-500/20 text-emerald-300 hover:bg-emerald-500/15'"
                                                class="text-xs px-2 py-1 rounded-md border transition cursor-pointer">
                                            {{ tenant.is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <button @click="toggleMaintenance(tenant)"
                                                :class="tenant.is_maintenance
                                                    ? 'bg-emerald-500/[0.08] border-emerald-500/20 text-emerald-300 hover:bg-emerald-500/15'
                                                    : 'bg-amber-500/[0.08] border-amber-500/20 text-amber-300 hover:bg-amber-500/15'"
                                                class="text-xs px-2 py-1 rounded-md border transition cursor-pointer">
                                            {{ tenant.is_maintenance ? 'End Maint.' : 'Maintenance' }}
                                        </button>
                                        <button @click="deleteTenant(tenant)"
                                                class="text-xs px-2 py-1 rounded-md bg-red-500/[0.08] border border-red-500/20 text-red-400 hover:bg-red-500/15 transition cursor-pointer">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="tenants.length === 0">
                            <td colspan="8" class="px-5 py-12 text-center text-slate-500 font-mono text-sm">No tenants found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreateModal"
         class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-[#0d1117] border border-white/[0.08] rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="px-6 py-4 border-b border-white/[0.06] flex items-center justify-between">
                <h3 class="font-grotesk font-semibold text-white">Add Tenant</h3>
                <button @click="closeCreate" class="text-slate-500 hover:text-white transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form @submit.prevent="submitCreate" class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Name *</label>
                        <input v-model="createForm.name" @input="autoSlug" type="text" placeholder="Acme Corp"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        <p v-if="createForm.errors.name" class="text-red-400 text-xs mt-1">{{ createForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Slug *</label>
                        <input v-model="createForm.slug" type="text" placeholder="acme-corp"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        <p v-if="createForm.errors.slug" class="text-red-400 text-xs mt-1">{{ createForm.errors.slug }}</p>
                    </div>
                </div>
                <p class="text-xs font-mono text-slate-500 uppercase tracking-wide pt-2">Database Connection</p>
                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Host</label>
                        <input v-model="createForm.db_host" type="text" placeholder="127.0.0.1"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        <p v-if="createForm.errors.db_host" class="text-red-400 text-xs mt-1">{{ createForm.errors.db_host }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Port</label>
                        <input v-model="createForm.db_port" type="number" placeholder="5432"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        <p v-if="createForm.errors.db_port" class="text-red-400 text-xs mt-1">{{ createForm.errors.db_port }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Database Name</label>
                        <input v-model="createForm.db_name" type="text" placeholder="tenant_acme"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        <p v-if="createForm.errors.db_name" class="text-red-400 text-xs mt-1">{{ createForm.errors.db_name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Username</label>
                        <input v-model="createForm.db_username" type="text"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        <p v-if="createForm.errors.db_username" class="text-red-400 text-xs mt-1">{{ createForm.errors.db_username }}</p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Password</label>
                    <input v-model="createForm.db_password" type="password"
                           class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                    <p v-if="createForm.errors.db_password" class="text-red-400 text-xs mt-1">{{ createForm.errors.db_password }}</p>
                </div>
                <button type="button" @click="showCreateReadReplica = !showCreateReadReplica"
                        class="text-xs text-blue-400 hover:text-blue-300 transition flex items-center gap-1.5 cursor-pointer">
                    <svg :class="showCreateReadReplica ? 'rotate-90' : ''" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    {{ showCreateReadReplica ? 'Hide' : 'Add' }} Read Replica
                </button>
                <div v-if="showCreateReadReplica" class="space-y-4 border border-white/[0.06] rounded-xl p-4">
                    <p class="text-xs font-mono text-slate-500 uppercase tracking-wide">Read Replica</p>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Host</label>
                            <input v-model="createForm.read_replica_host" type="text"
                                   class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Port</label>
                            <input v-model="createForm.read_replica_port" type="number"
                                   class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Username</label>
                            <input v-model="createForm.read_replica_username" type="text"
                                   class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Password</label>
                            <input v-model="createForm.read_replica_password" type="password"
                                   class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="closeCreate"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                        Cancel
                    </button>
                    <button type="submit" :disabled="createForm.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white cursor-pointer transition-all duration-150 disabled:opacity-50"
                            style="background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 0 20px rgba(99,102,241,0.25)">
                        {{ createForm.processing ? 'Creating…' : 'Create Tenant' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="showEditModal"
         class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-[#0d1117] border border-white/[0.08] rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="px-6 py-4 border-b border-white/[0.06] flex items-center justify-between">
                <h3 class="font-grotesk font-semibold text-white">Edit Tenant</h3>
                <button @click="closeEdit" class="text-slate-500 hover:text-white transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Name *</label>
                        <input v-model="editForm.name" type="text"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        <p v-if="editForm.errors.name" class="text-red-400 text-xs mt-1">{{ editForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Slug *</label>
                        <input v-model="editForm.slug" type="text"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        <p v-if="editForm.errors.slug" class="text-red-400 text-xs mt-1">{{ editForm.errors.slug }}</p>
                    </div>
                </div>
                <p class="text-xs font-mono text-slate-500 uppercase tracking-wide pt-2">Database Connection</p>
                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Host</label>
                        <input v-model="editForm.db_host" type="text"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        <p v-if="editForm.errors.db_host" class="text-red-400 text-xs mt-1">{{ editForm.errors.db_host }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Port</label>
                        <input v-model="editForm.db_port" type="number"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        <p v-if="editForm.errors.db_port" class="text-red-400 text-xs mt-1">{{ editForm.errors.db_port }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Database Name</label>
                        <input v-model="editForm.db_name" type="text"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        <p v-if="editForm.errors.db_name" class="text-red-400 text-xs mt-1">{{ editForm.errors.db_name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Username</label>
                        <input v-model="editForm.db_username" type="text"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        <p v-if="editForm.errors.db_username" class="text-red-400 text-xs mt-1">{{ editForm.errors.db_username }}</p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">
                        Password
                        <span v-if="editingTenant?.is_password_set" class="text-slate-600 normal-case">(leave blank to keep existing)</span>
                    </label>
                    <input v-model="editForm.db_password" type="password"
                           class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                    <p v-if="editForm.errors.db_password" class="text-red-400 text-xs mt-1">{{ editForm.errors.db_password }}</p>
                </div>
                <button type="button" @click="showEditReadReplica = !showEditReadReplica"
                        class="text-xs text-blue-400 hover:text-blue-300 transition flex items-center gap-1.5 cursor-pointer">
                    <svg :class="showEditReadReplica ? 'rotate-90' : ''" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    {{ showEditReadReplica ? 'Hide' : 'Edit' }} Read Replica
                </button>
                <div v-if="showEditReadReplica" class="space-y-4 border border-white/[0.06] rounded-xl p-4">
                    <p class="text-xs font-mono text-slate-500 uppercase tracking-wide">Read Replica</p>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Host</label>
                            <input v-model="editForm.read_replica_host" type="text"
                                   class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Port</label>
                            <input v-model="editForm.read_replica_port" type="number"
                                   class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Username</label>
                            <input v-model="editForm.read_replica_username" type="text"
                                   class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Password <span class="text-slate-600 normal-case">(blank = keep)</span></label>
                            <input v-model="editForm.read_replica_password" type="password"
                                   class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="closeEdit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                        Cancel
                    </button>
                    <button type="submit" :disabled="editForm.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white cursor-pointer transition-all duration-150 disabled:opacity-50"
                            style="background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 0 20px rgba(99,102,241,0.25)">
                        {{ editForm.processing ? 'Saving…' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <TourButton @click="startTour" />
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
