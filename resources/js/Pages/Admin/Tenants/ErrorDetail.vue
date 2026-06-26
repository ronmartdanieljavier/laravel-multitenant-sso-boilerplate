<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminTenantLayout from '../../../Layouts/AdminTenantLayout.vue';

defineOptions({ layout: AdminTenantLayout });

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const props = defineProps({
    tenant: Object,
    log: Object,
});

const severityConfig = {
    error: { label: 'Error', cls: 'bg-red-500/20 text-red-400' },
    warning: { label: 'Warning', cls: 'bg-amber-500/20 text-amber-300' },
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

function resolve() {
    router.patch(`/admin/tenants/${props.tenant.id}/errors/${props.log.id}/resolve`);
}

function unresolve() {
    router.patch(`/admin/tenants/${props.tenant.id}/errors/${props.log.id}/unresolve`);
}

function deleteLog() {
    if (!confirm(`Delete error log ${props.log.error_code}?`)) { return; }
    router.delete(`/admin/tenants/${props.tenant.id}/errors/${props.log.id}`);
}

function copyCode() {
    navigator.clipboard?.writeText(props.log.error_code);
}
</script>

<template>
    <Head :title="`${log.error_code} — Error Detail`" />

    <main class="p-8 max-w-5xl">
            <div v-if="flash.success" class="mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg px-4 py-3 text-sm">
                {{ flash.success }}
            </div>

            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
                <Link href="/admin/tenants" class="hover:text-slate-300 transition">Tenants</Link>
                <span>/</span>
                <Link :href="`/admin/tenants/${tenant.id}/errors`" class="hover:text-slate-300 transition">{{ tenant.name }}</Link>
                <span>/</span>
                <span class="font-mono text-slate-400">{{ log.error_code }}</span>
            </div>

            <!-- Header -->
            <div class="flex items-start justify-between mb-8">
                <div>
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
                    <p class="text-sm text-slate-400">{{ formatDate(log.created_at) }}</p>
                </div>
                <div class="flex gap-2">
                    <button v-if="!log.resolved" @click="resolve"
                            class="px-4 py-2 text-sm rounded-lg bg-blue-600/20 text-blue-400 hover:bg-blue-500/30 transition">
                        Mark Resolved
                    </button>
                    <button v-else @click="unresolve"
                            class="px-4 py-2 text-sm rounded-lg bg-amber-600/20 text-amber-400 hover:bg-amber-600/30 transition">
                        Reopen
                    </button>
                    <button @click="deleteLog"
                            class="px-4 py-2 text-sm rounded-lg bg-red-500/20 text-red-400 hover:bg-red-500/30 transition">
                        Delete
                    </button>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Exception summary -->
                <div class="bg-slate-900 border border-white/5 rounded-xl p-6 space-y-3">
                    <h2 class="text-sm font-medium text-slate-400 uppercase tracking-wide mb-4">Exception</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-slate-500 mb-1">Class</p>
                            <p class="font-mono text-sm text-white break-all">{{ log.exception_class }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-1">Location</p>
                            <p class="font-mono text-xs text-slate-300 break-all">
                                {{ log.file ?? '—' }}<span v-if="log.line" class="text-blue-400">:{{ log.line }}</span>
                            </p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-slate-500 mb-1">Message</p>
                            <p class="text-sm text-red-300 bg-red-500/10 rounded-lg px-3 py-2 font-mono break-all">{{ log.message }}</p>
                        </div>
                    </div>
                </div>

                <!-- Request info -->
                <div class="bg-slate-900 border border-white/5 rounded-xl p-6">
                    <h2 class="text-sm font-medium text-slate-400 uppercase tracking-wide mb-4">Request</h2>
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <p class="text-xs text-slate-500 mb-1">Method</p>
                            <span class="inline-block px-2 py-0.5 rounded bg-slate-800 font-mono text-xs text-blue-300">
                                {{ log.request_method ?? '—' }}
                            </span>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-slate-500 mb-1">URL</p>
                            <p class="font-mono text-xs text-slate-300 break-all">{{ log.request_url ?? '—' }}</p>
                        </div>
                        <div v-if="log.user_id">
                            <p class="text-xs text-slate-500 mb-1">User ID</p>
                            <p class="font-mono text-xs text-slate-300">{{ log.user_id }}</p>
                        </div>
                    </div>

                    <div v-if="log.request_params && Object.keys(log.request_params).length" class="mt-4">
                        <p class="text-xs text-slate-500 mb-2">Parameters</p>
                        <pre class="bg-slate-800 rounded-lg p-3 text-xs text-slate-300 overflow-x-auto font-mono">{{ JSON.stringify(log.request_params, null, 2) }}</pre>
                    </div>

                    <div v-if="log.request_headers && Object.keys(log.request_headers).length" class="mt-4">
                        <p class="text-xs text-slate-500 mb-2">Headers</p>
                        <pre class="bg-slate-800 rounded-lg p-3 text-xs text-slate-300 overflow-x-auto font-mono">{{ JSON.stringify(log.request_headers, null, 2) }}</pre>
                    </div>
                </div>

                <!-- Context -->
                <div v-if="log.context && Object.keys(log.context).length" class="bg-slate-900 border border-white/5 rounded-xl p-6">
                    <h2 class="text-sm font-medium text-slate-400 uppercase tracking-wide mb-4">Context</h2>
                    <dl class="grid grid-cols-2 gap-3">
                        <div v-for="(value, key) in log.context" :key="key">
                            <dt class="text-xs text-slate-500 mb-0.5">{{ key }}</dt>
                            <dd class="font-mono text-xs text-slate-300 break-all">{{ value ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Stack trace -->
                <div v-if="log.trace && log.trace.length" class="bg-slate-900 border border-white/5 rounded-xl p-6">
                    <h2 class="text-sm font-medium text-slate-400 uppercase tracking-wide mb-4">Stack Trace</h2>
                    <div class="space-y-1">
                        <div v-for="(frame, i) in log.trace" :key="i"
                             class="flex gap-3 py-1.5 border-b border-white/5 last:border-0">
                            <span class="text-slate-600 text-xs font-mono w-5 shrink-0 text-right">{{ i }}</span>
                            <div class="min-w-0">
                                <p v-if="frame.function" class="font-mono text-xs text-blue-300">{{ frame.function }}</p>
                                <p v-if="frame.file" class="font-mono text-xs text-slate-500 break-all">
                                    {{ frame.file }}<span v-if="frame.line" class="text-slate-400">:{{ frame.line }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </main>
</template>
