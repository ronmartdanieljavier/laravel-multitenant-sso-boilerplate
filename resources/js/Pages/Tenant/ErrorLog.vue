<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import TenantLayout from '../../Layouts/TenantLayout.vue';

defineOptions({ layout: TenantLayout });

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const props = defineProps({
    log: Object,
});

const severityConfig = {
    error:    { label: 'Error',    cls: 'bg-red-500/10 border-red-500/20 text-red-300' },
    warning:  { label: 'Warning',  cls: 'bg-amber-500/10 border-amber-500/20 text-amber-300' },
    critical: { label: 'Critical', cls: 'bg-red-900/30 border-red-500/20 text-red-300' },
};

function badge(s) {
    return severityConfig[s] ?? { label: s, cls: 'bg-slate-700/50 border-white/10 text-slate-400' };
}

function formatDate(val) {
    if (!val) { return '—'; }
    return new Date(val).toLocaleString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
}

function shortClass(cls) {
    const parts = cls.split('\\');
    return parts[parts.length - 1];
}

function copyCode() {
    navigator.clipboard?.writeText(props.log.error_code);
}
</script>

<template>
    <Head :title="`${log.error_code} — Error Detail`" />

    <div class="flex flex-col flex-1 bg-[#030712]">
        <header class="h-16 border-b border-white/[0.05] flex items-center px-8 shrink-0 bg-[#030712]">
            <div class="flex items-center gap-2 text-sm text-slate-500">
                <Link href="/tenant/errors" class="hover:text-slate-300 transition">Error Logs</Link>
                <span class="text-slate-700">/</span>
                <span class="font-mono text-slate-400">{{ log.error_code }}</span>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <span :class="['inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full border', badge(log.severity).cls]">
                    {{ badge(log.severity).label }}
                </span>
                <span v-if="log.resolved"
                      class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-300">
                    Resolved
                </span>
                <span v-else
                      class="inline-flex items-center text-xs font-mono px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-300">
                    Open
                </span>
            </div>
        </header>

        <main class="flex-1 px-8 py-8 max-w-4xl space-y-5">

            <div v-if="flash.success" class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 rounded-xl px-4 py-3 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ flash.success }}
            </div>

            <!-- Error code heading -->
            <div class="flex items-center gap-3">
                <h1 class="font-grotesk font-mono text-2xl font-bold text-white">{{ log.error_code }}</h1>
                <button @click="copyCode" title="Copy error code"
                        class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-300 transition px-2.5 py-1 border border-white/[0.08] rounded-lg cursor-pointer bg-white/[0.03] hover:bg-white/[0.06]">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    Copy
                </button>
            </div>
            <p class="text-xs text-slate-500 font-mono -mt-3">{{ formatDate(log.created_at) }}</p>

            <div class="space-y-4">
                <!-- Exception summary -->
                <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-6">
                    <h2 class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-4">Exception</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-slate-500 mb-1 font-mono">Type</p>
                            <p class="font-mono text-sm text-white">{{ shortClass(log.exception_class) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-1 font-mono">Occurred</p>
                            <p class="text-sm text-slate-300">{{ formatDate(log.created_at) }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-slate-500 mb-2 font-mono">Message</p>
                            <pre class="bg-black/40 border border-white/[0.06] rounded-xl p-4 text-xs font-mono text-red-300 overflow-auto max-h-32 whitespace-pre-wrap break-all">{{ log.message }}</pre>
                        </div>
                    </div>
                </div>

                <!-- Request info -->
                <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-6">
                    <h2 class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-4">Request</h2>
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <p class="text-xs text-slate-500 mb-1 font-mono">Method</p>
                            <span class="inline-block px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20 font-mono text-xs text-emerald-300">
                                {{ log.request_method ?? '—' }}
                            </span>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-slate-500 mb-1 font-mono">URL</p>
                            <p class="font-mono text-xs text-slate-300 break-all">{{ log.request_url ?? '—' }}</p>
                        </div>
                    </div>

                    <div v-if="log.request_params && Object.keys(log.request_params).length" class="mt-4">
                        <p class="text-xs text-slate-500 mb-2 font-mono">Parameters</p>
                        <pre class="bg-black/40 border border-white/[0.06] rounded-xl p-4 text-xs font-mono text-slate-400 overflow-auto max-h-64">{{ JSON.stringify(log.request_params, null, 2) }}</pre>
                    </div>
                </div>

                <!-- Context -->
                <div v-if="log.context && Object.keys(log.context).length" class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-6">
                    <h2 class="text-xs font-mono text-slate-500 uppercase tracking-wider mb-4">Context</h2>
                    <dl class="grid grid-cols-2 gap-3">
                        <div v-for="(value, key) in log.context" :key="key">
                            <dt class="text-xs text-slate-500 mb-0.5 font-mono">{{ key }}</dt>
                            <dd class="font-mono text-xs text-slate-300 break-all">{{ value ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Support note -->
                <div class="bg-emerald-500/[0.04] border border-emerald-500/20 rounded-2xl p-5">
                    <p class="text-sm text-white font-grotesk font-medium mb-1">Need help?</p>
                    <p class="text-xs text-slate-400">
                        Quote error code <span class="font-mono text-emerald-400">{{ log.error_code }}</span> when contacting support so we can locate the full details quickly.
                    </p>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
