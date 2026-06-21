<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useIdleTimeout } from '../../composables/useIdleTimeout';

const page = usePage();
useIdleTimeout(page.props.idleTimeoutMinutes);

const user = page.props.auth.user;

function logout() {
    router.post('/logout');
}

// Display name form
const nameForm = useForm({ name: user?.name ?? '' });

function updateName() {
    nameForm.put('/profile/name', { preserveScroll: true });
}

// Profile picture
const pictureForm = useForm({ profile_picture: null });
const picturePreview = ref(user?.profile_picture_url ?? null);
const pictureInput = ref(null);

function onPictureSelected(event) {
    const file = event.target.files[0];
    if (!file) { return; }
    pictureForm.profile_picture = file;
    picturePreview.value = URL.createObjectURL(file);
}

function updatePicture() {
    pictureForm.post('/profile/picture', { preserveScroll: true });
}

// Password form
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function updatePassword() {
    passwordForm.put('/profile/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <Head title="My Profile" />

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
                <Link href="/admin" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">
                    Dashboard
                </Link>
            </nav>
            <div class="p-4 border-t border-white/5">
                <div class="flex items-center gap-3">
                    <div v-if="user?.profile_picture_url" class="w-8 h-8 rounded-full overflow-hidden shrink-0">
                        <img :src="user.profile_picture_url" class="w-full h-full object-cover" alt="Profile" />
                    </div>
                    <div v-else class="w-8 h-8 rounded-full bg-violet-500 flex items-center justify-center text-xs font-bold text-white shrink-0">
                        {{ user?.name?.[0]?.toUpperCase() ?? 'U' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ user?.name }}</p>
                        <p class="text-xs text-slate-400 truncate">{{ user?.email }}</p>
                    </div>
                    <button @click="logout" title="Sign out" class="text-slate-400 hover:text-white transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <div class="ml-60">
            <header class="h-16 bg-slate-900/50 border-b border-white/5 flex items-center px-8">
                <h2 class="text-lg font-semibold">My Profile</h2>
            </header>

            <main class="p-8 max-w-2xl space-y-6">

                <!-- Success flash -->
                <div v-if="$page.props.flash?.success"
                     class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm px-4 py-3 rounded-xl">
                    {{ $page.props.flash.success }}
                </div>

                <!-- Display Name -->
                <section class="bg-slate-900 border border-white/5 rounded-xl p-6">
                    <h3 class="font-semibold text-white mb-4">Display Name</h3>
                    <form @submit.prevent="updateName" class="space-y-4">
                        <div>
                            <label class="block text-sm text-slate-400 mb-1.5">Name</label>
                            <input
                                v-model="nameForm.name"
                                type="text"
                                class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500 transition"
                                placeholder="Your display name"
                            />
                            <p v-if="nameForm.errors.name" class="text-red-400 text-xs mt-1">{{ nameForm.errors.name }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button
                                type="submit"
                                :disabled="nameForm.processing"
                                class="bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg transition"
                            >
                                {{ nameForm.processing ? 'Saving…' : 'Save Name' }}
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Profile Picture -->
                <section class="bg-slate-900 border border-white/5 rounded-xl p-6">
                    <h3 class="font-semibold text-white mb-4">Profile Picture</h3>
                    <form @submit.prevent="updatePicture" class="space-y-4">
                        <div class="flex items-center gap-5">
                            <div class="w-20 h-20 rounded-full overflow-hidden bg-slate-800 border border-white/10 shrink-0 flex items-center justify-center">
                                <img v-if="picturePreview" :src="picturePreview" class="w-full h-full object-cover" alt="Preview" />
                                <span v-else class="text-3xl font-bold text-slate-500">
                                    {{ user?.name?.[0]?.toUpperCase() ?? 'U' }}
                                </span>
                            </div>
                            <div class="flex-1">
                                <input
                                    ref="pictureInput"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="onPictureSelected"
                                />
                                <button
                                    type="button"
                                    @click="pictureInput.click()"
                                    class="bg-slate-800 hover:bg-slate-700 border border-white/10 text-slate-300 text-sm px-4 py-2 rounded-lg transition"
                                >
                                    Choose Image
                                </button>
                                <p class="text-xs text-slate-500 mt-2">JPG, PNG, GIF up to 2 MB</p>
                                <p v-if="pictureForm.errors.profile_picture" class="text-red-400 text-xs mt-1">{{ pictureForm.errors.profile_picture }}</p>
                            </div>
                        </div>
                        <button
                            type="submit"
                            :disabled="pictureForm.processing || !pictureForm.profile_picture"
                            class="bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg transition"
                        >
                            {{ pictureForm.processing ? 'Uploading…' : 'Upload Picture' }}
                        </button>
                    </form>
                </section>

                <!-- Change Password -->
                <section class="bg-slate-900 border border-white/5 rounded-xl p-6">
                    <h3 class="font-semibold text-white mb-4">Change Password</h3>
                    <form @submit.prevent="updatePassword" class="space-y-4">
                        <div>
                            <label class="block text-sm text-slate-400 mb-1.5">Current Password</label>
                            <input
                                v-model="passwordForm.current_password"
                                type="password"
                                autocomplete="current-password"
                                class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500 transition"
                            />
                            <p v-if="passwordForm.errors.current_password" class="text-red-400 text-xs mt-1">{{ passwordForm.errors.current_password }}</p>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-400 mb-1.5">New Password</label>
                            <input
                                v-model="passwordForm.password"
                                type="password"
                                autocomplete="new-password"
                                class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500 transition"
                            />
                            <p v-if="passwordForm.errors.password" class="text-red-400 text-xs mt-1">{{ passwordForm.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-400 mb-1.5">Confirm New Password</label>
                            <input
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500 transition"
                            />
                        </div>
                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg transition"
                        >
                            {{ passwordForm.processing ? 'Updating…' : 'Update Password' }}
                        </button>
                    </form>
                </section>

            </main>
        </div>
    </div>
</template>
