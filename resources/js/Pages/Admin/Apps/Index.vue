<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
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

    <div class="flex flex-col flex-1">
        <!-- Header -->
        <header class="h-16 border-b border-white/[0.05] flex items-center px-8 shrink-0 bg-[#030712]">
            <h1 class="font-grotesk text-lg font-semibold text-white">App Management</h1>
        </header>

        <main class="p-8 overflow-y-auto">
            <div id="tour-apps-table" class="bg-white/[0.02] border border-white/[0.06] rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-white/[0.06]">
                    <h2 class="font-grotesk text-base font-semibold text-white">Apps</h2>
                    <p class="text-slate-500 text-sm mt-0.5">SSO-registered applications and their access configuration.</p>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/[0.06]">
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Name</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Slug</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Description</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-mono text-slate-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <template v-for="app in apps" :key="app.id">
                            <!-- View row -->
                            <tr v-if="editingId !== app.id" class="hover:bg-white/[0.02] transition-colors">
                                <td class="px-5 py-3.5 text-slate-200 font-medium">{{ app.name }}</td>
                                <td class="px-5 py-3.5 text-slate-500 font-mono text-xs">{{ app.slug }}</td>
                                <td class="px-5 py-3.5 text-slate-400 max-w-xs truncate">{{ app.description ?? '—' }}</td>
                                <td class="px-5 py-3.5">
                                    <span v-if="app.is_active"
                                          class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-300">
                                        Active
                                    </span>
                                    <span v-else
                                          class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-slate-500/10 border border-slate-500/20 text-slate-400">
                                        Inactive
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <button @click="startEdit(app)"
                                            class="text-xs text-blue-400 hover:text-blue-300 transition">
                                        Edit
                                    </button>
                                </td>
                            </tr>

                            <!-- Edit row (inline) -->
                            <tr v-else class="bg-white/[0.03]">
                                <td class="px-5 py-4" colspan="5">
                                    <form @submit.prevent="submitEdit(app)" class="space-y-3">
                                        <div class="flex gap-4">
                                            <div class="flex-1">
                                                <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Name</label>
                                                <input v-model="form.name"
                                                       type="text"
                                                       class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition"
                                                       :class="{ 'border-red-500/50': form.errors.name }"
                                                       placeholder="App name" />
                                                <p v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</p>
                                            </div>
                                            <div class="flex-1">
                                                <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Description</label>
                                                <input v-model="form.description"
                                                       type="text"
                                                       class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition"
                                                       :class="{ 'border-red-500/50': form.errors.description }"
                                                       placeholder="Optional description" />
                                                <p v-if="form.errors.description" class="text-red-400 text-xs mt-1">{{ form.errors.description }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <button type="submit"
                                                    :disabled="form.processing"
                                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50 cursor-pointer transition-all duration-150"
                                                    style="background: linear-gradient(135deg, #2563eb, #7c3aed)">
                                                {{ form.processing ? 'Saving…' : 'Save' }}
                                            </button>
                                            <button type="button"
                                                    @click="cancelEdit"
                                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="apps.length === 0">
                            <td colspan="5" class="px-5 py-12 text-center text-slate-600">No apps found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <TourButton @click="startTour" />
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
