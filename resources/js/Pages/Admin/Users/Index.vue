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

    <div class="flex flex-col flex-1">
        <!-- Header -->
        <header id="tour-users-header" class="h-16 border-b border-white/[0.05] flex items-center px-8 shrink-0 bg-[#030712]">
            <h1 class="font-grotesk text-lg font-semibold text-white">User Management</h1>
            <div class="ml-auto">
                <button @click="openInvite"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white cursor-pointer transition-all duration-150"
                        style="background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 0 20px rgba(99,102,241,0.25)">
                    + Invite User
                </button>
            </div>
        </header>

        <main class="p-8 overflow-y-auto">
            <div id="tour-users-table" class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-white/[0.06]">
                    <h2 class="font-grotesk text-base font-semibold text-white">Users</h2>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/[0.06]">
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">User</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Apps</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div v-if="user.profile_picture_url" class="w-8 h-8 rounded-full overflow-hidden shrink-0 ring-1 ring-white/10">
                                        <img :src="user.profile_picture_url" class="w-full h-full object-cover" alt="" />
                                    </div>
                                    <div v-else class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-600 to-violet-600 flex items-center justify-center text-xs font-bold text-white shrink-0">
                                        {{ user.name?.[0]?.toUpperCase() ?? '?' }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-200">{{ user.name }}</p>
                                        <p class="text-xs text-slate-500 font-mono">{{ user.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span v-if="user.is_active"
                                      class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-300">
                                    Active
                                </span>
                                <span v-else
                                      class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-300">
                                    Pending
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="a in user.apps" :key="a.app_id"
                                          class="inline-flex items-center text-xs font-mono px-2 py-0.5 rounded-full bg-white/[0.05] border border-white/[0.08] text-slate-300">
                                        {{ a.app_name }}<span class="text-slate-600 ml-1">/{{ a.role }}</span>
                                    </span>
                                    <span v-if="user.apps.length === 0" class="text-slate-600 text-xs font-mono">None</span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <button @click="openEdit(user)"
                                        class="text-xs text-blue-400 hover:text-blue-300 transition">
                                    Edit
                                </button>
                            </td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td colspan="4" class="px-5 py-12 text-center text-slate-600">No users found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Invite Modal -->
    <Teleport to="body">
        <div v-if="showInviteModal"
             class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50"
             @click.self="closeInvite">
            <div class="bg-[#0d1117] border border-white/[0.08] rounded-2xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-white/[0.06]">
                    <h3 class="font-grotesk font-semibold text-white">Invite User</h3>
                    <button @click="closeInvite" class="text-slate-500 hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitInvite" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Name</label>
                        <input v-model="inviteForm.name" type="text"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition"
                               :class="{ 'border-red-500/50': inviteForm.errors.name }"
                               placeholder="Full name" />
                        <p v-if="inviteForm.errors.name" class="text-red-400 text-xs mt-1">{{ inviteForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Email</label>
                        <input v-model="inviteForm.email" type="email"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition"
                               :class="{ 'border-red-500/50': inviteForm.errors.email }"
                               placeholder="user@example.com" />
                        <p v-if="inviteForm.errors.email" class="text-red-400 text-xs mt-1">{{ inviteForm.errors.email }}</p>
                    </div>

                    <!-- App permissions -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-medium text-slate-400 font-mono uppercase tracking-wide">App Access</label>
                            <button type="button" @click="addApp(inviteForm)"
                                    class="text-xs text-blue-400 hover:text-blue-300 transition">
                                + Add App
                            </button>
                        </div>

                        <div v-for="(appEntry, index) in inviteForm.apps" :key="index"
                             class="bg-white/[0.03] border border-white/[0.06] rounded-xl p-3.5 mb-2 space-y-2.5">
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <select v-model="appEntry.app_id"
                                            class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition">
                                        <option :value="null" disabled>Select app</option>
                                        <option v-for="app in apps" :key="app.id" :value="app.id"
                                                :disabled="usedAppIds(inviteForm, index).includes(app.id)">
                                            {{ app.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <select v-model="appEntry.role"
                                            class="bg-white/[0.04] border border-white/[0.08] rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition">
                                        <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                                    </select>
                                </div>
                                <button type="button" @click="removeApp(inviteForm, index)"
                                        class="text-slate-600 hover:text-red-400 transition px-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div v-if="tenants.length > 0">
                                <p class="text-xs text-slate-600 mb-1.5 font-mono">Tenants (optional)</p>
                                <div class="flex flex-wrap gap-1">
                                    <button v-for="tenant in tenants" :key="tenant.id"
                                            type="button"
                                            @click="toggleTenant(inviteForm, index, tenant.id)"
                                            :class="isTenantSelected(inviteForm, index, tenant.id)
                                                ? 'bg-blue-600/20 border-blue-500/40 text-blue-300'
                                                : 'bg-white/[0.03] border-white/[0.06] text-slate-500 hover:border-white/20'"
                                            class="text-xs px-2.5 py-1 rounded-full border transition font-mono">
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
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50 cursor-pointer transition-all duration-150"
                                style="background: linear-gradient(135deg, #2563eb, #7c3aed)">
                            {{ inviteForm.processing ? 'Sending…' : 'Send Invitation' }}
                        </button>
                        <button type="button" @click="closeInvite"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
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
             class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50"
             @click.self="closeEdit">
            <div class="bg-[#0d1117] border border-white/[0.08] rounded-2xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-white/[0.06]">
                    <h3 class="font-grotesk font-semibold text-white">Edit User</h3>
                    <button @click="closeEdit" class="text-slate-500 hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Name</label>
                        <input v-model="editForm.name" type="text"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition"
                               :class="{ 'border-red-500/50': editForm.errors.name }"
                               placeholder="Full name" />
                        <p v-if="editForm.errors.name" class="text-red-400 text-xs mt-1">{{ editForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Email</label>
                        <input v-model="editForm.email" type="email"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition"
                               :class="{ 'border-red-500/50': editForm.errors.email }"
                               placeholder="user@example.com" />
                        <p v-if="editForm.errors.email" class="text-red-400 text-xs mt-1">{{ editForm.errors.email }}</p>
                    </div>

                    <!-- App permissions -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-medium text-slate-400 font-mono uppercase tracking-wide">App Access</label>
                            <button type="button" @click="addApp(editForm)"
                                    class="text-xs text-blue-400 hover:text-blue-300 transition">
                                + Add App
                            </button>
                        </div>

                        <div v-for="(appEntry, index) in editForm.apps" :key="index"
                             class="bg-white/[0.03] border border-white/[0.06] rounded-xl p-3.5 mb-2 space-y-2.5">
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <select v-model="appEntry.app_id"
                                            class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition">
                                        <option :value="null" disabled>Select app</option>
                                        <option v-for="app in apps" :key="app.id" :value="app.id"
                                                :disabled="usedAppIds(editForm, index).includes(app.id)">
                                            {{ app.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <select v-model="appEntry.role"
                                            class="bg-white/[0.04] border border-white/[0.08] rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition">
                                        <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                                    </select>
                                </div>
                                <button type="button" @click="removeApp(editForm, index)"
                                        class="text-slate-600 hover:text-red-400 transition px-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div v-if="tenants.length > 0">
                                <p class="text-xs text-slate-600 mb-1.5 font-mono">Tenants (optional)</p>
                                <div class="flex flex-wrap gap-1">
                                    <button v-for="tenant in tenants" :key="tenant.id"
                                            type="button"
                                            @click="toggleTenant(editForm, index, tenant.id)"
                                            :class="isTenantSelected(editForm, index, tenant.id)
                                                ? 'bg-blue-600/20 border-blue-500/40 text-blue-300'
                                                : 'bg-white/[0.03] border-white/[0.06] text-slate-500 hover:border-white/20'"
                                            class="text-xs px-2.5 py-1 rounded-full border transition font-mono">
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
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50 cursor-pointer transition-all duration-150"
                                style="background: linear-gradient(135deg, #2563eb, #7c3aed)">
                            {{ editForm.processing ? 'Saving…' : 'Save Changes' }}
                        </button>
                        <button type="button" @click="closeEdit"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <TourButton @click="startTour" />
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
