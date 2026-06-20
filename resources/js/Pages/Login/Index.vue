<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
});

function submit() {
    form.post('/login');
}
</script>

<template>
    <Head title="Login" />

    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Logo / Brand -->
            <div class="text-center mb-10">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-2xl mb-4 shadow-lg shadow-blue-500/30">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>
                <h1 class="text-3xl font-semibold text-white">Welcome back</h1>
                <p class="text-slate-400 mt-1">Sign in to your SSO account</p>
            </div>

            <!-- Card -->
            <div class="bg-white/5 backdrop-blur border border-white/10 rounded-2xl p-8 shadow-2xl">
                <!-- Error Banner -->
                <div v-if="form.errors.email && !form.isDirty" class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 mb-5">
                    <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-red-400 text-sm">{{ form.errors.email }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Email address</label>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="you@example.com"
                            :class="form.errors.email ? 'border-red-500/50 focus:ring-red-500' : 'border-white/10 focus:ring-blue-500'"
                            class="w-full bg-white/5 border rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:border-transparent transition"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="••••••••"
                            :class="form.errors.password ? 'border-red-500/50 focus:ring-red-500' : 'border-white/10 focus:ring-blue-500'"
                            class="w-full bg-white/5 border rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:border-transparent transition"
                        />
                        <p v-if="form.errors.password" class="text-red-400 text-xs mt-1">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center gap-2 text-slate-400 cursor-pointer">
                            <input type="checkbox" class="rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500" />
                            Remember me
                        </label>
                        <a href="#" class="text-blue-400 hover:text-blue-300 transition">Forgot password?</a>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-medium py-2.5 px-4 rounded-lg transition shadow-lg shadow-blue-500/20 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-transparent"
                    >
                        <span v-if="form.processing">Signing in…</span>
                        <span v-else>Sign in</span>
                    </button>
                </form>
            </div>

            <p class="text-center text-slate-500 text-sm mt-6">
                Multi-tenant SSO Platform &mdash; All rights reserved.
            </p>
        </div>
    </div>
</template>
