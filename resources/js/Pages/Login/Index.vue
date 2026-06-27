<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
});

function submit() {
    form.post('/login');
}
</script>

<template>
    <Head title="Sign In" />

    <div class="min-h-screen bg-slate-950 flex items-center justify-center p-4 relative overflow-hidden">
        <!-- Subtle background glow -->
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[400px] bg-blue-600/8 rounded-full blur-3xl" />
        </div>

        <div class="w-full max-w-sm relative">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-11 h-11 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl mb-5 shadow-xl shadow-blue-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>
                <h1 class="text-xl font-semibold text-white tracking-tight">Welcome back</h1>
                <p class="text-slate-500 mt-1 text-sm">Sign in to your account</p>
            </div>

            <!-- Card -->
            <div class="bg-slate-900 border border-white/[0.08] rounded-2xl p-7 shadow-2xl shadow-black/40">
                <!-- Error -->
                <div v-if="form.errors.email && !form.isDirty"
                     class="flex items-center gap-2.5 bg-red-500/10 border border-red-500/25 rounded-xl px-4 py-3 mb-5">
                    <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-red-400 text-sm">{{ form.errors.email }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Email address</label>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="you@company.com"
                            :class="form.errors.email ? 'border-red-500/40 focus:ring-red-500/50' : 'border-white/[0.08] focus:ring-blue-500/50'"
                            class="w-full bg-slate-800/60 border rounded-xl px-3.5 py-2.5 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:border-transparent transition text-sm"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Password</label>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="••••••••"
                            :class="form.errors.password ? 'border-red-500/40 focus:ring-red-500/50' : 'border-white/[0.08] focus:ring-blue-500/50'"
                            class="w-full bg-slate-800/60 border rounded-xl px-3.5 py-2.5 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:border-transparent transition text-sm"
                        />
                        <p v-if="form.errors.password" class="text-red-400 text-xs mt-1.5">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-slate-500 cursor-pointer text-xs">
                            <input type="checkbox" class="rounded border-white/10 bg-slate-800 text-blue-500 focus:ring-blue-500/50 focus:ring-offset-0 focus:ring-1" />
                            Remember me
                        </label>
                        <a href="#" class="text-xs text-slate-500 hover:text-blue-400 transition">Forgot password?</a>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-2.5 px-4 rounded-xl transition-all duration-150 shadow-lg shadow-blue-500/20 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2 focus:ring-offset-slate-900 cursor-pointer mt-2"
                    >
                        <span v-if="form.processing" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            Signing in…
                        </span>
                        <span v-else>Sign in</span>
                    </button>
                </form>
            </div>

            <p class="text-center text-slate-600 text-xs mt-6">
                <Link href="/" class="inline-flex items-center gap-1.5 text-slate-500 hover:text-slate-300 transition-colors duration-150 cursor-pointer">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to home
                </Link>
            </p>
        </div>
    </div>
</template>
