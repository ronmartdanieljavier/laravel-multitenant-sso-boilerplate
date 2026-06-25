<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminTenantLayout from '../../../Layouts/AdminTenantLayout.vue';

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
</script>

<template>
    <Head :title="`${tenant.name} — Users`" />

    <main class="p-8">
            <div v-if="flash.success" class="mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg px-4 py-3 text-sm">
                {{ flash.success }}
            </div>

            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
                <Link href="/admin/tenants" class="hover:text-slate-300 transition">Tenants</Link>
                <span>/</span>
                <span class="text-slate-300">{{ tenant.name }}</span>
                <span>/</span>
                <span class="text-slate-400">Users</span>
            </div>

            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-white">{{ tenant.name }} — Users</h1>
                    <p class="text-sm text-slate-400 mt-1">{{ users.length }} user{{ users.length !== 1 ? 's' : '' }} in this tenant</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="`/admin/tenants/${tenant.id}/settings`"
                          class="px-4 py-2 text-sm rounded-lg border border-white/10 text-slate-300 hover:bg-white/5 transition">
                        Tenant Settings
                    </Link>
                </div>
            </div>

            <!-- User table -->
            <div v-if="users.length === 0" class="bg-slate-900 border border-white/5 rounded-xl p-12 text-center">
                <p class="text-slate-400">No users in this tenant yet.</p>
                <Link href="/admin/users" class="mt-3 inline-block text-sm text-violet-400 hover:text-violet-300 transition">
                    Invite users from User Management →
                </Link>
            </div>

            <div v-else class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-white/5">
                            <th class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase tracking-wide">User</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase tracking-wide">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase tracking-wide">Apps</th>
                            <th class="px-6 py-4 text-right text-xs font-medium text-slate-400 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-white/2 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div v-if="user.profile_picture_url" class="w-8 h-8 rounded-full overflow-hidden shrink-0">
                                        <img :src="user.profile_picture_url" class="w-full h-full object-cover" alt="" />
                                    </div>
                                    <div v-else class="w-8 h-8 rounded-full bg-violet-500/20 text-violet-300 flex items-center justify-center text-xs font-bold shrink-0">
                                        {{ user.name[0].toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-white">{{ user.name }}</p>
                                        <p class="text-xs text-slate-400">{{ user.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span :class="statusBadge(user).cls" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                                    {{ statusBadge(user).label }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="app in user.apps" :key="app.app_id"
                                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-800 text-xs text-slate-300">
                                        {{ app.app_name }}
                                        <span class="text-slate-500">·</span>
                                        <span class="text-violet-400 capitalize">{{ app.role }}</span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button @click="openEdit(user)"
                                        class="text-sm text-slate-400 hover:text-violet-300 transition">
                                    Edit
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
    </main>

    <!-- Edit User Modal (same as global user management) -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60">
        <div class="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-xl max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b border-white/5">
                <h2 class="text-lg font-semibold text-white">Edit User</h2>
                <button @click="closeEdit" class="text-slate-400 hover:text-white transition">✕</button>
            </div>

            <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Name</label>
                    <input v-model="editForm.name" type="text" required
                           class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                    <p v-if="editForm.errors.name" class="text-xs text-red-400 mt-1">{{ editForm.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Email</label>
                    <input v-model="editForm.email" type="email" required
                           class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500" />
                    <p v-if="editForm.errors.email" class="text-xs text-red-400 mt-1">{{ editForm.errors.email }}</p>
                </div>

                <!-- App permissions -->
                <div class="border-t border-white/5 pt-4">
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-xs text-slate-400">App Permissions</label>
                        <button type="button" @click="addApp(editForm)"
                                class="text-xs text-violet-400 hover:text-violet-300 transition">+ Add app</button>
                    </div>

                    <div v-for="(appEntry, index) in editForm.apps" :key="index" class="bg-slate-800/50 rounded-lg p-3 mb-2 space-y-2">
                        <div class="flex gap-2">
                            <select v-model="appEntry.app_id"
                                    class="flex-1 bg-slate-800 border border-white/10 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-violet-500">
                                <option :value="null">Select app…</option>
                                <option v-for="app in apps" :key="app.id" :value="app.id"
                                        :disabled="usedAppIds(editForm, index).includes(app.id)">
                                    {{ app.name }}
                                </option>
                            </select>
                            <select v-model="appEntry.role"
                                    class="w-28 bg-slate-800 border border-white/10 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-violet-500">
                                <option v-for="role in ['admin', 'user', 'viewer']" :key="role" :value="role">
                                    {{ role }}
                                </option>
                            </select>
                            <button type="button" @click="removeApp(editForm, index)"
                                    class="text-slate-500 hover:text-red-400 transition px-1">✕</button>
                        </div>

                        <div v-if="tenants.length" class="flex flex-wrap gap-1 mt-1">
                            <button type="button" v-for="t in tenants" :key="t.id"
                                    @click="toggleTenant(editForm, index, t.id)"
                                    :class="isTenantSelected(editForm, index, t.id)
                                        ? 'bg-violet-600/30 border-violet-500/50 text-violet-300'
                                        : 'bg-slate-800 border-white/10 text-slate-400 hover:text-slate-200'"
                                    class="px-2 py-0.5 rounded border text-xs transition">
                                {{ t.name }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="closeEdit"
                            class="px-4 py-2 text-sm text-slate-400 hover:text-white transition">Cancel</button>
                    <button type="submit" :disabled="editForm.processing"
                            class="bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg transition">
                        {{ editForm.processing ? 'Saving…' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
