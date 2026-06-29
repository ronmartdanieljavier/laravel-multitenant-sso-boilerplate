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

    <div class="min-h-screen bg-[#030712] flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Brand mark -->
            <div class="flex flex-col items-center mb-8">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4"
                     style="background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 0 30px rgba(99,102,241,0.4)">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <h1 class="font-grotesk text-2xl font-bold text-white">Set up your account</h1>
                <p class="text-sm text-slate-400 mt-1.5 text-center max-w-xs">
                    You've been invited to join. Confirm your name and create a password to get started.
                </p>
            </div>

            <!-- Card -->
            <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-8">
                <p class="text-xs font-mono text-slate-500 mb-6">
                    Signing in as <span class="text-slate-300">{{ email }}</span>
                </p>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Name</label>
                        <input v-model="form.name" type="text" autofocus
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition"
                               :class="{ 'ring-2 ring-red-500/40 border-transparent': form.errors.name }"
                               placeholder="Your full name" />
                        <p v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Password</label>
                        <input v-model="form.password" type="password"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition"
                               :class="{ 'ring-2 ring-red-500/40 border-transparent': form.errors.password }"
                               placeholder="At least 8 characters" />
                        <p v-if="form.errors.password" class="text-red-400 text-xs mt-1">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 font-mono uppercase tracking-wide">Confirm Password</label>
                        <input v-model="form.password_confirmation" type="password"
                               class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-transparent transition"
                               placeholder="Repeat your password" />
                    </div>

                    <button type="submit"
                            :disabled="form.processing"
                            class="w-full py-2.5 rounded-xl text-sm font-semibold text-white cursor-pointer transition-all duration-150 disabled:opacity-50 mt-2"
                            style="background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 0 20px rgba(99,102,241,0.3)">
                        {{ form.processing ? 'Activating…' : 'Activate Account' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@400;500&family=DM+Mono:wght@400;500&display=swap');
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
