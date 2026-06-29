<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminTenantLayout from '../../../Layouts/AdminTenantLayout.vue';
import TourButton from '../../Partials/TourButton.vue';
import { useTour } from '../../../composables/useTour';

defineOptions({ layout: AdminTenantLayout });

const page = usePage();

const props = defineProps({
    tenant: Object,
    users: Array,
    apps: Array,
    tenants: Array,
});

const flash = computed(() => page.props.flash ?? {});

// ── Edit user modal (reuse UserManagement pattern) ───────────────────────────

const showEditModal = ref(false);
const editingUser = ref(null);

const editForm = useForm({
    name: '',
    email: '',
    apps: [],
});

function openEdit(user) {
    editingUser.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.apps = user.apps.map(a => ({
        app_id: a.app_id,
        role: a.role,
        tenant_ids: [...a.tenant_ids],
    }));
    editForm.clearErrors();
    showEditModal.value = true;
}

function closeEdit() {
    showEditModal.value = false;
    editingUser.value = null;
}

function submitEdit() {
    editForm.put(`/admin/users/${editingUser.value.id}`, {
        onSuccess: () => { showEditModal.value = false; },
    });
}

function addApp(form) {
    form.apps.push({ app_id: null, role: 'user', tenant_ids: [] });
}

function removeApp(form, index) {
    form.apps.splice(index, 1);
}

function toggleTenant(form, appIndex, tenantId) {
    const ids = form.apps[appIndex].tenant_ids;
    const pos = ids.indexOf(tenantId);
    if (pos === -1) { ids.push(tenantId); } else { ids.splice(pos, 1); }
}

function isTenantSelected(form, appIndex, tenantId) {
    return form.apps[appIndex]?.tenant_ids?.includes(tenantId) ?? false;
}

const usedAppIds = (form, currentIndex) =>
    form.apps.map((a, i) => i !== currentIndex ? a.app_id : null).filter(Boolean);

// ── Status helpers ────────────────────────────────────────────────────────────

function statusBadge(user) {
    if (!user.is_active && user.invitation_sent_at) {
        return { label: 'Invited', cls: 'bg-amber-500/20 text-amber-300' };
    }
    if (user.is_active) {
        return { label: 'Active', cls: 'bg-emerald-500/20 text-emerald-300' };
    }
    return { label: 'Inactive', cls: 'bg-slate-700 text-slate-400' };
}

// ── Force logout ──────────────────────────────────────────────────────────────

function forceLogout(user) {
    if (!confirm(`Force logout "${user.name}"? Their active session will be immediately terminated.`)) return;
    router.delete(`/admin/tenants/${props.tenant.id}/users/${user.id}/session`);
}

const { startTour } = useTour('admin-tenant-users', [
    {
        element: '#tour-tenant-users-header',
        title: 'Tenant Users',
        description: 'All users who have access to this tenant. Users with a green "Online" badge are currently logged in.',
    },
    {
        element: '#tour-tenant-users-table',
        title: 'User Table',
        description: 'Shows each user\'s name, email, account status (Active / Invited / Inactive), current session state, and which apps they can access with their assigned role.',
        side: 'top',
    },
]);
</script>


<template>
    <Head :title="`${tenant.name} — Users`" />

    <main class="p-8">
        <div v-if="flash.success" class="mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl px-4 py-3 text-sm">
            {{ flash.success }}
        </div>

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-slate-500 mb-6 font-mono">
            <Link href="/admin/tenants" class="hover:text-slate-300 transition">Tenants</Link>
            <span class="text-slate-700">/</span>
            <span class="text-slate-300">{{ tenant.name }}</span>
            <span class="text-slate-700">/</span>
            <span class="text-slate-400">Users</span>
        </div>

        <!-- Header -->
        <div id="tour-tenant-users-header" class="flex items-center justify-between mb-8">
            <div>
                <h1 class="font-grotesk text-2xl font-bold text-white">{{ tenant.name }} — Users</h1>
                <p class="text-sm text-slate-400 mt-1">
                    {{ users.length }} user{{ users.length !== 1 ? 's' : '' }} in this tenant
                    <span v-if="users.filter(u => u.is_logged_in).length > 0"
                          class="ml-2 inline-flex items-center gap-1 text-emerald-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span>
                        {{ users.filter(u => u.is_logged_in).length }} online
                    </span>
                </p>
            </div>
            <Link :href="`/admin/tenants/${tenant.id}/settings`"
                  class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                Tenant Settings
            </Link>
        </div>

        <!-- Empty state -->
        <div v-if="users.length === 0" class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-12 text-center">
            <p class="text-slate-400">No users in this tenant yet.</p>
            <Link href="/admin/users" class="mt-3 inline-block text-sm text-blue-400 hover:text-blue-300 transition">
                Invite users from User Management →
            </Link>
        </div>

        <!-- User table -->
        <div v-else id="tour-tenant-users-table" class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/[0.06]">
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">User</th>
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Session</th>
                        <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Apps</th>
                        <th class="px-5 py-3 text-right text-xs font-mono text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/[0.04]">
                    <tr v-for="user in users" :key="user.id" class="hover:bg-white/[0.02] transition-colors">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div v-if="user.profile_picture_url" class="w-8 h-8 rounded-full overflow-hidden shrink-0">
                                    <img :src="user.profile_picture_url" class="w-full h-full object-cover" alt="" />
                                </div>
                                <div v-else class="w-8 h-8 rounded-full bg-blue-500/20 text-blue-300 flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ user.name[0].toUpperCase() }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-white">{{ user.name }}</p>
                                    <p class="text-xs text-slate-400 font-mono">{{ user.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span :class="statusBadge(user).cls" class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full border">
                                {{ statusBadge(user).label }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span v-if="user.is_logged_in"
                                  class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-300">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block animate-pulse"></span>
                                Online
                            </span>
                            <span v-else class="text-xs text-slate-600 font-mono">—</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="app in user.apps" :key="app.app_id"
                                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/[0.04] border border-white/[0.08] text-xs text-slate-300">
                                    {{ app.app_name }}
                                    <span class="text-slate-600">·</span>
                                    <span class="text-blue-400 capitalize">{{ app.role }}</span>
                                </span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <button v-if="user.is_logged_in"
                                        @click="forceLogout(user)"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-red-400 bg-red-500/[0.08] border border-red-500/20 hover:bg-red-500/15 cursor-pointer transition-all">
                                    Force Logout
                                </button>
                                <button @click="openEdit(user)"
                                        class="text-sm text-slate-400 hover:text-blue-300 transition cursor-pointer">
                                    Edit
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Edit User Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div class="bg-[#0d1117] border border-white/[0.08] rounded-2xl w-full max-w-xl max-h-[85vh] overflow-y-auto shadow-2xl">
            <div class="flex items-center justify-between p-6 border-b border-white/[0.06]">
                <h2 class="font-grotesk text-lg font-semibold text-white">Edit User</h2>
                <button @click="closeEdit" class="text-slate-500 hover:text-white transition cursor-pointer">✕</button>
            </div>
            <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Name</label>
                    <input v-model="editForm.name" type="text" required
                           class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                    <p v-if="editForm.errors.name" class="text-xs text-red-400 mt-1">{{ editForm.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Email</label>
                    <input v-model="editForm.email" type="email" required
                           class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition" />
                    <p v-if="editForm.errors.email" class="text-xs text-red-400 mt-1">{{ editForm.errors.email }}</p>
                </div>

                <div class="border-t border-white/[0.06] pt-4">
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-xs font-mono text-slate-400 uppercase tracking-wide">App Permissions</label>
                        <button type="button" @click="addApp(editForm)"
                                class="text-xs text-blue-400 hover:text-blue-300 transition cursor-pointer">+ Add app</button>
                    </div>
                    <div v-for="(appEntry, index) in editForm.apps" :key="index" class="bg-white/[0.03] border border-white/[0.06] rounded-xl p-3 mb-2 space-y-2">
                        <div class="flex gap-2">
                            <select v-model="appEntry.app_id"
                                    class="flex-1 bg-white/[0.04] border border-white/[0.08] rounded-xl px-3 py-1.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition">
                                <option :value="null">Select app…</option>
                                <option v-for="app in apps" :key="app.id" :value="app.id"
                                        :disabled="usedAppIds(editForm, index).includes(app.id)">
                                    {{ app.name }}
                                </option>
                            </select>
                            <select v-model="appEntry.role"
                                    class="w-28 bg-white/[0.04] border border-white/[0.08] rounded-xl px-3 py-1.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition">
                                <option v-for="role in ['admin', 'user', 'viewer']" :key="role" :value="role">{{ role }}</option>
                            </select>
                            <button type="button" @click="removeApp(editForm, index)"
                                    class="text-slate-500 hover:text-red-400 transition px-1 cursor-pointer">✕</button>
                        </div>
                        <div v-if="tenants.length" class="flex flex-wrap gap-1 mt-1">
                            <button type="button" v-for="t in tenants" :key="t.id"
                                    @click="toggleTenant(editForm, index, t.id)"
                                    :class="isTenantSelected(editForm, index, t.id)
                                        ? 'bg-blue-500/20 border-blue-500/40 text-blue-300'
                                        : 'bg-white/[0.04] border-white/[0.08] text-slate-400 hover:text-slate-200'"
                                    class="px-2 py-0.5 rounded-md border text-xs transition cursor-pointer">
                                {{ t.name }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="closeEdit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                        Cancel
                    </button>
                    <button type="submit" :disabled="editForm.processing"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-medium text-white cursor-pointer transition-all duration-150 disabled:opacity-50"
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
