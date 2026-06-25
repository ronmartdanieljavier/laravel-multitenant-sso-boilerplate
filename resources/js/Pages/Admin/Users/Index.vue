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
    users: Array,
    apps: Array,
    tenants: Array,
    roles: Array,
});

// Invite modal
const showInviteModal = ref(false);

const inviteForm = useForm({
    name: '',
    email: '',
    apps: [],
});

function openInvite() {
    inviteForm.reset();
    inviteForm.clearErrors();
    showInviteModal.value = true;
}

function closeInvite() {
    showInviteModal.value = false;
}

function submitInvite() {
    inviteForm.post('/admin/users/invite', {
        onSuccess: () => {
            showInviteModal.value = false;
        },
    });
}

// Edit modal
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
    const url = new URL(window.location.href);
    if (url.searchParams.has('edit')) {
        url.searchParams.delete('edit');
        window.history.replaceState({}, '', url.toString());
    }
}

function submitEdit() {
    editForm.put(`/admin/users/${editingUser.value.id}`, {
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);

    const editId = params.get('edit');
    if (editId) {
        const user = props.users.find(u => u.id === parseInt(editId, 10));
        if (user) {
            openEdit(user);
        }
    }

    if (params.has('invite')) {
        openInvite();
        const url = new URL(window.location.href);
        url.searchParams.delete('invite');
        window.history.replaceState({}, '', url.toString());
    }
});

// Shared app permissions helpers (used for both invite and edit forms)
function addApp(form) {
    form.apps.push({ app_id: null, role: 'user', tenant_ids: [] });
}

function removeApp(form, index) {
    form.apps.splice(index, 1);
}

function toggleTenant(form, appIndex, tenantId) {
    const ids = form.apps[appIndex].tenant_ids;
    const pos = ids.indexOf(tenantId);
    if (pos === -1) {
        ids.push(tenantId);
    } else {
        ids.splice(pos, 1);
    }
}

function isTenantSelected(form, appIndex, tenantId) {
    return form.apps[appIndex]?.tenant_ids?.includes(tenantId) ?? false;
}

const usedAppIds = (form, currentIndex) =>
    form.apps.map((a, i) => i !== currentIndex ? a.app_id : null).filter(Boolean);

const { startTour } = useTour('admin-users', [
    {
        element: '#tour-users-header',
        title: 'User Management',
        description: 'Manage all admin portal users. Use "Invite User" to send an email invitation. Invited users receive a link to set their password and activate their account.',
    },
    {
        element: '#tour-users-table',
        title: 'Users Table',
        description: 'Each row shows a user\'s name, email, account status (Active / Invited / Inactive), and which applications they have access to with their assigned role.',
        side: 'top',
    },
]);
</script>

<template>
    <Head title="User Management" />

    <div class="min-h-screen bg-slate-950 text-slate-100">
        <!-- Sidebar -->
        <aside class="fixed inset-y-0 left-0 w-60 bg-slate-900 border-r border-white/5 flex flex-col">
            <div class="h-16 flex items-center px-6 border-b border-white/5">
                <div class="w-8 h-8 bg-violet-600 rounded-lg flex items-center justify-center mr-3">
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
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition bg-violet-600/20 text-violet-300">
                    Users
                </Link>
                <Link href="/admin/apps"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Apps
                </Link>
                <Link href="/admin/tenants"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Tenants
                </Link>
                <Link href="/admin/settings"
                      class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Settings
                </Link>
            </nav>
            <div class="p-4 border-t border-white/5">
                <div class="flex items-center gap-3">
                    <Link href="/profile" class="flex items-center gap-3 flex-1 min-w-0 hover:opacity-80 transition">
                        <div v-if="page.props.auth.user?.profile_picture_url" class="w-8 h-8 rounded-full overflow-hidden shrink-0">
                            <img :src="page.props.auth.user.profile_picture_url" class="w-full h-full object-cover" alt="Profile" />
                        </div>
                        <div v-else class="w-8 h-8 rounded-full bg-violet-500 flex items-center justify-center text-xs font-bold text-white shrink-0">
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

            <!-- Header -->
            <header id="tour-users-header" class="h-16 bg-slate-900/50 border-b border-white/5 flex items-center justify-between px-8">
                <h2 class="text-lg font-semibold">User Management</h2>
                <button @click="openInvite"
                        class="bg-violet-600 hover:bg-violet-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                    Invite User
                </button>
            </header>

            <main class="p-8">
                <div id="tour-users-table" class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-white/5">
                        <h3 class="font-semibold text-white">Users</h3>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="text-slate-400 border-b border-white/5">
                            <tr>
                                <th class="text-left px-6 py-3 font-medium">User</th>
                                <th class="text-left px-6 py-3 font-medium">Status</th>
                                <th class="text-left px-6 py-3 font-medium">Apps</th>
                                <th class="text-left px-6 py-3 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="user in users" :key="user.id" class="hover:bg-white/2 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div v-if="user.profile_picture_url" class="w-8 h-8 rounded-full overflow-hidden shrink-0">
                                            <img :src="user.profile_picture_url" class="w-full h-full object-cover" alt="" />
                                        </div>
                                        <div v-else class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-300 shrink-0">
                                            {{ user.name?.[0]?.toUpperCase() ?? '?' }}
                                        </div>
                                        <div>
                                            <p class="font-medium text-white">{{ user.name }}</p>
                                            <p class="text-xs text-slate-400">{{ user.email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span v-if="user.is_active"
                                          class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300">
                                        Active
                                    </span>
                                    <span v-else
                                          class="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300">
                                        Pending
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="a in user.apps" :key="a.app_id"
                                              class="text-xs px-2 py-0.5 rounded-full bg-slate-700/60 text-slate-300">
                                            {{ a.app_name }}
                                            <span class="text-slate-500 ml-1">({{ a.role }})</span>
                                        </span>
                                        <span v-if="user.apps.length === 0" class="text-slate-600 text-xs">None</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <button @click="openEdit(user)"
                                            class="text-violet-400 hover:text-violet-300 text-xs transition">
                                        Edit
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="users.length === 0">
                                <td colspan="4" class="px-6 py-8 text-center text-slate-500">No users found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- Invite Modal -->
    <Teleport to="body">
        <div v-if="showInviteModal"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
             @click.self="closeInvite">
            <div class="bg-slate-900 border border-white/10 rounded-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-white/5">
                    <h3 class="font-semibold text-white">Invite User</h3>
                    <button @click="closeInvite" class="text-slate-400 hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitInvite" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Name</label>
                        <input v-model="inviteForm.name" type="text"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-violet-500"
                               :class="{ 'border-red-500': inviteForm.errors.name }"
                               placeholder="Full name" />
                        <p v-if="inviteForm.errors.name" class="text-red-400 text-xs mt-1">{{ inviteForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Email</label>
                        <input v-model="inviteForm.email" type="email"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-violet-500"
                               :class="{ 'border-red-500': inviteForm.errors.email }"
                               placeholder="user@example.com" />
                        <p v-if="inviteForm.errors.email" class="text-red-400 text-xs mt-1">{{ inviteForm.errors.email }}</p>
                    </div>

                    <!-- App permissions -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs text-slate-400">App Access</label>
                            <button type="button" @click="addApp(inviteForm)"
                                    class="text-xs text-violet-400 hover:text-violet-300 transition">
                                + Add App
                            </button>
                        </div>

                        <div v-for="(appEntry, index) in inviteForm.apps" :key="index"
                             class="bg-slate-800/60 border border-white/5 rounded-lg p-3 mb-2 space-y-2">
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <select v-model="appEntry.app_id"
                                            class="w-full bg-slate-700 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500">
                                        <option :value="null" disabled>Select app</option>
                                        <option v-for="app in apps" :key="app.id" :value="app.id"
                                                :disabled="usedAppIds(inviteForm, index).includes(app.id)">
                                            {{ app.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <select v-model="appEntry.role"
                                            class="bg-slate-700 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500">
                                        <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                                    </select>
                                </div>
                                <button type="button" @click="removeApp(inviteForm, index)"
                                        class="text-slate-500 hover:text-red-400 transition px-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div v-if="tenants.length > 0">
                                <p class="text-xs text-slate-500 mb-1">Tenants (optional)</p>
                                <div class="flex flex-wrap gap-1">
                                    <button v-for="tenant in tenants" :key="tenant.id"
                                            type="button"
                                            @click="toggleTenant(inviteForm, index, tenant.id)"
                                            :class="isTenantSelected(inviteForm, index, tenant.id)
                                                ? 'bg-violet-600/30 border-violet-500/50 text-violet-300'
                                                : 'bg-slate-700/40 border-white/5 text-slate-400 hover:border-white/20'"
                                            class="text-xs px-2 py-0.5 rounded-full border transition">
                                        {{ tenant.name }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p v-if="inviteForm.errors.apps" class="text-red-400 text-xs mt-1">{{ inviteForm.errors.apps }}</p>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit"
                                :disabled="inviteForm.processing"
                                class="bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                            Send Invitation
                        </button>
                        <button type="button" @click="closeInvite"
                                class="text-slate-400 hover:text-slate-200 text-sm transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Edit Modal -->
    <Teleport to="body">
        <div v-if="showEditModal"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
             @click.self="closeEdit">
            <div class="bg-slate-900 border border-white/10 rounded-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-white/5">
                    <h3 class="font-semibold text-white">Edit User</h3>
                    <button @click="closeEdit" class="text-slate-400 hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Name</label>
                        <input v-model="editForm.name" type="text"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-violet-500"
                               :class="{ 'border-red-500': editForm.errors.name }"
                               placeholder="Full name" />
                        <p v-if="editForm.errors.name" class="text-red-400 text-xs mt-1">{{ editForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Email</label>
                        <input v-model="editForm.email" type="email"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-violet-500"
                               :class="{ 'border-red-500': editForm.errors.email }"
                               placeholder="user@example.com" />
                        <p v-if="editForm.errors.email" class="text-red-400 text-xs mt-1">{{ editForm.errors.email }}</p>
                    </div>

                    <!-- App permissions -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs text-slate-400">App Access</label>
                            <button type="button" @click="addApp(editForm)"
                                    class="text-xs text-violet-400 hover:text-violet-300 transition">
                                + Add App
                            </button>
                        </div>

                        <div v-for="(appEntry, index) in editForm.apps" :key="index"
                             class="bg-slate-800/60 border border-white/5 rounded-lg p-3 mb-2 space-y-2">
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <select v-model="appEntry.app_id"
                                            class="w-full bg-slate-700 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500">
                                        <option :value="null" disabled>Select app</option>
                                        <option v-for="app in apps" :key="app.id" :value="app.id"
                                                :disabled="usedAppIds(editForm, index).includes(app.id)">
                                            {{ app.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <select v-model="appEntry.role"
                                            class="bg-slate-700 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500">
                                        <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                                    </select>
                                </div>
                                <button type="button" @click="removeApp(editForm, index)"
                                        class="text-slate-500 hover:text-red-400 transition px-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div v-if="tenants.length > 0">
                                <p class="text-xs text-slate-500 mb-1">Tenants (optional)</p>
                                <div class="flex flex-wrap gap-1">
                                    <button v-for="tenant in tenants" :key="tenant.id"
                                            type="button"
                                            @click="toggleTenant(editForm, index, tenant.id)"
                                            :class="isTenantSelected(editForm, index, tenant.id)
                                                ? 'bg-violet-600/30 border-violet-500/50 text-violet-300'
                                                : 'bg-slate-700/40 border-white/5 text-slate-400 hover:border-white/20'"
                                            class="text-xs px-2 py-0.5 rounded-full border transition">
                                        {{ tenant.name }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p v-if="editForm.errors.apps" class="text-red-400 text-xs mt-1">{{ editForm.errors.apps }}</p>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit"
                                :disabled="editForm.processing"
                                class="bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                            Save Changes
                        </button>
                        <button type="button" @click="closeEdit"
                                class="text-slate-400 hover:text-slate-200 text-sm transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <TourButton @click="startTour" />
</template>
