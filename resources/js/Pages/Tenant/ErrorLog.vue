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
    error:    { label: 'Error',    cls: 'bg-red-500/20 text-red-400' },
    warning:  { label: 'Warning',  cls: 'bg-amber-500/20 text-amber-300' },
    critical: { label: 'Critical', cls: 'bg-red-900/40 text-red-300 font-bold' },
};

function badge(s) {
    return severityConfig[s] ?? { label: s, cls: 'bg-slate-700 text-slate-400' };
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

    <main class="flex-1 px-8 py-10">
        <div v-if="flash.success" class="mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg px-4 py-3 text-sm">
            {{ flash.success }}
        </div>

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
            <Link href="/tenant/errors" class="hover:text-slate-300 transition">Error Logs</Link>
            <span>/</span>
            <span class="font-mono text-slate-400">{{ log.error_code }}</span>
        </div>

        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                <h1 class="font-mono text-xl font-bold text-white">{{ log.error_code }}</h1>
                <button @click="copyCode" title="Copy error code"
                        class="text-xs text-slate-500 hover:text-slate-300 transition px-2 py-0.5 border border-white/10 rounded">
                    Copy
                </button>
                <span :class="badge(log.severity).cls"
                      class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                    {{ badge(log.severity).label }}
                </span>
                <span v-if="log.resolved"
                      class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-emerald-500/20 text-emerald-400">
                    Resolved
                </span>
                <span v-else
                      class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-amber-500/20 text-amber-300">
                    Open
                </span>
            </div>
            <p class="text-sm text-slate-500">{{ formatDate(log.created_at) }}</p>
        </div>

        <div class="space-y-6">
            <!-- Exception summary -->
            <div class="bg-slate-900 border border-white/5 rounded-xl p-6">
                <h2 class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-4">Exception</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Type</p>
                        <p class="font-mono text-sm text-white">{{ shortClass(log.exception_class) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Occurred</p>
                        <p class="text-sm text-slate-300">{{ formatDate(log.created_at) }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-slate-500 mb-1">Message</p>
                        <p class="text-sm text-red-300 bg-red-500/10 rounded-lg px-3 py-2 font-mono break-all">{{ log.message }}</p>
                    </div>
                </div>
            </div>

            <!-- Request info -->
            <div class="bg-slate-900 border border-white/5 rounded-xl p-6">
                <h2 class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-4">Request</h2>
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Method</p>
                        <span class="inline-block px-2 py-0.5 rounded bg-slate-800 font-mono text-xs text-emerald-300">
                            {{ log.request_method ?? '—' }}
                        </span>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-slate-500 mb-1">URL</p>
                        <p class="font-mono text-xs text-slate-300 break-all">{{ log.request_url ?? '—' }}</p>
                    </div>
                </div>

                <div v-if="log.request_params && Object.keys(log.request_params).length" class="mt-4">
                    <p class="text-xs text-slate-500 mb-2">Parameters</p>
                    <pre class="bg-slate-800 rounded-lg p-3 text-xs text-slate-300 overflow-x-auto font-mono">{{ JSON.stringify(log.request_params, null, 2) }}</pre>
                </div>
            </div>

            <!-- Context -->
            <div v-if="log.context && Object.keys(log.context).length" class="bg-slate-900 border border-white/5 rounded-xl p-6">
                <h2 class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-4">Context</h2>
                <dl class="grid grid-cols-2 gap-3">
                    <div v-for="(value, key) in log.context" :key="key">
                        <dt class="text-xs text-slate-500 mb-0.5">{{ key }}</dt>
                        <dd class="font-mono text-xs text-slate-300 break-all">{{ value ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Support note -->
            <div class="bg-emerald-500/5 border border-emerald-500/20 rounded-xl p-5">
                <p class="text-sm text-emerald-300 font-medium mb-1">Need help?</p>
                <p class="text-xs text-slate-400">
                    Quote error code <span class="font-mono text-emerald-400">{{ log.error_code }}</span> when contacting support so we can locate the full details quickly.
                </p>
            </div>
        </div>
    </main>
</template>
