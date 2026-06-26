<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: String,
    name: String,
    email: String,
});

const form = useForm({
    name: props.name,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(`/invitation/${props.token}`, {
        onSuccess: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Accept Invitation" />

    <div class="min-h-screen bg-slate-950 flex items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <div class="flex items-center justify-center gap-3 mb-8">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
            </div>

            <div class="bg-slate-900 border border-white/5 rounded-2xl p-8">
                <h1 class="text-xl font-semibold text-white mb-1">Set up your account</h1>
                <p class="text-sm text-slate-400 mb-6">
                    You've been invited to join. Confirm your name and create a password to get started.
                </p>

                <p class="text-xs text-slate-500 mb-6">Signing in as <span class="text-slate-300">{{ email }}</span></p>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Name</label>
                        <input v-model="form.name" type="text" autofocus
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500"
                               :class="{ 'border-red-500': form.errors.name }"
                               placeholder="Your full name" />
                        <p v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Password</label>
                        <input v-model="form.password" type="password"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500"
                               :class="{ 'border-red-500': form.errors.password }"
                               placeholder="At least 8 characters" />
                        <p v-if="form.errors.password" class="text-red-400 text-xs mt-1">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Confirm Password</label>
                        <input v-model="form.password_confirmation" type="password"
                               class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500"
                               placeholder="Repeat your password" />
                    </div>

                    <button type="submit"
                            :disabled="form.processing"
                            class="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium py-2.5 rounded-lg transition mt-2">
                        {{ form.processing ? 'Activating...' : 'Activate Account' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
