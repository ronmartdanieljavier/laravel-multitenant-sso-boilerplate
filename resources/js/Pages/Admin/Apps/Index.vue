<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import TourButton from '../../Partials/TourButton.vue';
import { useTour } from '../../../composables/useTour';

const page = usePage();
const missingSettings = page.props.missingRequiredSettings ?? [];

function logout() {
    router.post('/logout');
}

const props = defineProps({
    apps: Array,
});

const editingId = ref(null);

const form = useForm({
    name: '',
    description: '',
});

function startEdit(app) {
    editingId.value = app.id;
    form.name = app.name;
    form.description = app.description ?? '';
}

function cancelEdit() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submitEdit(app) {
    form.put(`/admin/apps/${app.id}`, {
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

const { startTour } = useTour('admin-apps', [
    {
        element: '#tour-apps-table',
        title: 'Application Registry',
        description: 'These are the SSO-registered applications users can be granted access to. Each app has a unique slug used in token claims and API authentication.',
        side: 'bottom',
    },
]);
</script>

<template>
    <Head title="App Management" />

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
                      class="flex items-center gap-3 py-2 text-sm font-medium transition border-l-2 border-blue-500 rounded-r-lg pl-[10px] pr-3 text-white">
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

            <!-- Header -->
            <header class="h-16 bg-slate-900/50 border-b border-white/5 flex items-center px-8">
                <h2 class="text-lg font-semibold">App Management</h2>
            </header>

            <main class="p-8">
                <div id="tour-apps-table" class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-white/5">
                        <h3 class="font-semibold text-white">Apps</h3>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="text-slate-400 border-b border-white/5">
                            <tr>
                                <th class="text-left px-6 py-3 font-medium">Name</th>
                                <th class="text-left px-6 py-3 font-medium">Slug</th>
                                <th class="text-left px-6 py-3 font-medium">Description</th>
                                <th class="text-left px-6 py-3 font-medium">Status</th>
                                <th class="text-left px-6 py-3 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <template v-for="app in apps" :key="app.id">
                                <!-- View row -->
                                <tr v-if="editingId !== app.id" class="hover:bg-white/2 transition">
                                    <td class="px-6 py-4 font-medium text-white">{{ app.name }}</td>
                                    <td class="px-6 py-4 text-slate-500 font-mono text-xs">{{ app.slug }}</td>
                                    <td class="px-6 py-4 text-slate-400 max-w-xs truncate">{{ app.description ?? '—' }}</td>
                                    <td class="px-6 py-4">
                                        <span :class="app.is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-500/20 text-slate-400'"
                                              class="text-xs px-2 py-0.5 rounded-full">
                                            {{ app.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <button @click="startEdit(app)"
                                                class="text-blue-400 hover:text-blue-300 text-xs transition">
                                            Edit
                                        </button>
                                    </td>
                                </tr>

                                <!-- Edit row -->
                                <tr v-else class="bg-slate-800/50">
                                    <td class="px-6 py-4" colspan="5">
                                        <form @submit.prevent="submitEdit(app)" class="space-y-3">
                                            <div class="flex gap-4">
                                                <div class="flex-1">
                                                    <label class="block text-xs text-slate-400 mb-1">Name</label>
                                                    <input v-model="form.name"
                                                           type="text"
                                                           class="w-full bg-slate-900 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500"
                                                           :class="{ 'border-red-500': form.errors.name }"
                                                           placeholder="App name" />
                                                    <p v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</p>
                                                </div>
                                                <div class="flex-1">
                                                    <label class="block text-xs text-slate-400 mb-1">Description</label>
                                                    <input v-model="form.description"
                                                           type="text"
                                                           class="w-full bg-slate-900 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500"
                                                           :class="{ 'border-red-500': form.errors.description }"
                                                           placeholder="Optional description" />
                                                    <p v-if="form.errors.description" class="text-red-400 text-xs mt-1">{{ form.errors.description }}</p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <button type="submit"
                                                        :disabled="form.processing"
                                                        class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-xs font-medium px-4 py-1.5 rounded-lg transition">
                                                    Save
                                                </button>
                                                <button type="button"
                                                        @click="cancelEdit"
                                                        class="text-slate-400 hover:text-slate-200 text-xs transition">
                                                    Cancel
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="apps.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-slate-500">No apps found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <TourButton @click="startTour" />
</template>
