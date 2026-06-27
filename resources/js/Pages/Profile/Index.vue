<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useIdleTimeout } from '../../composables/useIdleTimeout';
import TourButton from '../Partials/TourButton.vue';
import { useTour } from '../../composables/useTour';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const page = usePage();
useIdleTimeout(page.props.idleTimeoutMinutes);

const user = page.props.auth.user;

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

const { startTour } = useTour('profile', [
    {
        element: '#tour-profile-name',
        title: 'Display Name',
        description: 'Update the name that appears across the admin portal and in emails sent on your behalf.',
        side: 'right',
    },
    {
        element: '#tour-profile-picture',
        title: 'Profile Picture',
        description: 'Upload a photo to personalise your account. It appears in the sidebar and in your profile.',
        side: 'right',
    },
    {
        element: '#tour-profile-password',
        title: 'Change Password',
        description: 'Update your login password here. You must enter your current password to confirm the change.',
        side: 'right',
    },
]);
</script>

<template>
    <Head title="My Profile" />

    <div class="flex flex-col flex-1 bg-[#030712]">
        <header class="h-16 border-b border-white/[0.05] flex items-center px-8 shrink-0 bg-[#030712]">
            <h1 class="font-grotesk text-lg font-semibold text-white">My Profile</h1>
        </header>

        <main class="p-8 max-w-2xl space-y-5">

            <!-- Success flash -->
            <div v-if="$page.props.flash?.success"
                 class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ $page.props.flash.success }}
            </div>

            <!-- Display Name -->
            <section id="tour-profile-name" class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-6">
                <h2 class="font-grotesk text-base font-semibold text-white mb-1">Display Name</h2>
                <p class="text-slate-500 text-sm mb-5">Update the name shown across the admin portal.</p>
                <form @submit.prevent="updateName" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Name</label>
                        <input
                            v-model="nameForm.name"
                            type="text"
                            class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition"
                            placeholder="Your display name"
                        />
                        <p v-if="nameForm.errors.name" class="text-red-400 text-xs mt-1.5">{{ nameForm.errors.name }}</p>
                    </div>
                    <div class="flex justify-end mt-5">
                        <button
                            type="submit"
                            :disabled="nameForm.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50 cursor-pointer transition-all duration-150"
                            style="background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 0 20px rgba(99,102,241,0.25)"
                        >
                            {{ nameForm.processing ? 'Saving…' : 'Save Name' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- Profile Picture -->
            <section id="tour-profile-picture" class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-6">
                <h2 class="font-grotesk text-base font-semibold text-white mb-1">Profile Picture</h2>
                <p class="text-slate-500 text-sm mb-5">Upload a photo to personalise your account.</p>
                <form @submit.prevent="updatePicture" class="space-y-5">
                    <div class="flex items-center gap-5">
                        <div class="w-20 h-20 rounded-full overflow-hidden bg-white/[0.04] border border-white/[0.08] shrink-0 flex items-center justify-center">
                            <img v-if="picturePreview" :src="picturePreview" class="w-full h-full object-cover" alt="Preview" />
                            <span v-else class="text-3xl font-bold text-slate-500 font-grotesk">
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
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-400 bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.06] cursor-pointer transition-all"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Choose Image
                            </button>
                            <p class="text-xs text-slate-600 mt-2">JPG, PNG, GIF up to 2 MB</p>
                            <p v-if="pictureForm.errors.profile_picture" class="text-red-400 text-xs mt-1">{{ pictureForm.errors.profile_picture }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="pictureForm.processing || !pictureForm.profile_picture"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50 cursor-pointer transition-all duration-150"
                            style="background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 0 20px rgba(99,102,241,0.25)"
                        >
                            {{ pictureForm.processing ? 'Uploading…' : 'Upload Picture' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- Change Password -->
            <section id="tour-profile-password" class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-6">
                <h2 class="font-grotesk text-base font-semibold text-white mb-1">Change Password</h2>
                <p class="text-slate-500 text-sm mb-5">You must enter your current password to confirm the change.</p>
                <form @submit.prevent="updatePassword" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Current Password</label>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition"
                        />
                        <p v-if="passwordForm.errors.current_password" class="text-red-400 text-xs mt-1.5">{{ passwordForm.errors.current_password }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">New Password</label>
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            autocomplete="new-password"
                            class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition"
                        />
                        <p v-if="passwordForm.errors.password" class="text-red-400 text-xs mt-1.5">{{ passwordForm.errors.password }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Confirm New Password</label>
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition"
                        />
                    </div>
                    <div class="flex justify-end mt-5">
                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50 cursor-pointer transition-all duration-150"
                            style="background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 0 20px rgba(99,102,241,0.25)"
                        >
                            {{ passwordForm.processing ? 'Updating…' : 'Update Password' }}
                        </button>
                    </div>
                </form>
            </section>

        </main>
    </div>

    <TourButton @click="startTour" />
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
