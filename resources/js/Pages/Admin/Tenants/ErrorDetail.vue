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
        <div v-if="flash.success" class="mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl px-4 py-3 text-sm">
            {{ flash.success }}
        </div>

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-slate-500 mb-6 font-mono">
            <Link href="/admin/tenants" class="hover:text-slate-300 transition">Tenants</Link>
            <span class="text-slate-700">/</span>
            <Link :href="`/admin/tenants/${tenant.id}/errors`" class="hover:text-slate-300 transition">{{ tenant.name }}</Link>
            <span class="text-slate-700">/</span>
            <span class="text-slate-400">{{ log.error_code }}</span>
        </div>

        <!-- Header -->
        <div class="flex items-start justify-between mb-8">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <h1 class="font-mono text-xl font-bold text-white">{{ log.error_code }}</h1>
                    <button @click="copyCode" title="Copy error code"
                            class="text-xs text-slate-500 hover:text-slate-300 transition px-2 py-0.5 border border-white/[0.08] rounded-md cursor-pointer">
                        Copy
                    </button>
                    <span :class="badge(log.severity).cls"
                          class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full border">
                        {{ badge(log.severity).label }}
                    </span>
                    <span v-if="log.resolved"
                          class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-300">
                        Resolved
                    </span>
                    <span v-else
                          class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-300">
                        Open
                    </span>
                </div>
                <p class="text-sm text-slate-400 font-mono">{{ formatDate(log.created_at) }}</p>
            </div>
            <div class="flex gap-2">
                <button v-if="!log.resolved" @click="resolve"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white cursor-pointer transition-all duration-150"
                        style="background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 0 20px rgba(99,102,241,0.25)">
                    Mark Resolved
                </button>
                <button v-else @click="unresolve"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-amber-400 bg-amber-500/[0.08] border border-amber-500/20 hover:bg-amber-500/15 cursor-pointer transition-all">
                    Reopen
                </button>
                <button @click="deleteLog"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-red-400 bg-red-500/[0.08] border border-red-500/20 hover:bg-red-500/15 cursor-pointer transition-all">
                    Delete
                </button>
            </div>
        </div>

        <div class="space-y-5">
            <!-- Exception summary -->
            <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-6 space-y-4">
                <h2 class="text-xs font-mono text-slate-400 uppercase tracking-wider">Exception</h2>
                <div class="flex items-start gap-4 py-3 border-b border-white/[0.04]">
                    <span class="text-xs font-mono text-slate-500 uppercase w-32 shrink-0 pt-0.5">Class</span>
                    <span class="text-sm text-slate-300 font-mono break-all">{{ log.exception_class }}</span>
                </div>
                <div class="flex items-start gap-4 py-3 border-b border-white/[0.04]">
                    <span class="text-xs font-mono text-slate-500 uppercase w-32 shrink-0 pt-0.5">Location</span>
                    <span class="text-xs text-slate-300 font-mono break-all">
                        {{ log.file ?? '—' }}<span v-if="log.line" class="text-blue-400">:{{ log.line }}</span>
                    </span>
                </div>
                <div class="flex items-start gap-4 py-3">
                    <span class="text-xs font-mono text-slate-500 uppercase w-32 shrink-0 pt-0.5">Message</span>
                    <span class="text-sm text-red-300 bg-red-500/10 rounded-xl px-3 py-2 font-mono break-all flex-1">{{ log.message }}</span>
                </div>
            </div>

            <!-- Request info -->
            <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-6">
                <h2 class="text-xs font-mono text-slate-400 uppercase tracking-wider mb-4">Request</h2>
                <div class="flex items-start gap-4 py-3 border-b border-white/[0.04]">
                    <span class="text-xs font-mono text-slate-500 uppercase w-32 shrink-0 pt-0.5">Method</span>
                    <span class="inline-block px-2 py-0.5 rounded-md bg-white/[0.06] font-mono text-xs text-blue-300">
                        {{ log.request_method ?? '—' }}
                    </span>
                </div>
                <div class="flex items-start gap-4 py-3 border-b border-white/[0.04]">
                    <span class="text-xs font-mono text-slate-500 uppercase w-32 shrink-0 pt-0.5">URL</span>
                    <span class="font-mono text-xs text-slate-300 break-all">{{ log.request_url ?? '—' }}</span>
                </div>
                <div v-if="log.user_id" class="flex items-start gap-4 py-3 border-b border-white/[0.04]">
                    <span class="text-xs font-mono text-slate-500 uppercase w-32 shrink-0 pt-0.5">User ID</span>
                    <span class="font-mono text-xs text-slate-300">{{ log.user_id }}</span>
                </div>
                <div v-if="log.request_params && Object.keys(log.request_params).length" class="mt-4">
                    <p class="text-xs font-mono text-slate-500 uppercase mb-2">Parameters</p>
                    <pre class="bg-black/40 border border-white/[0.06] rounded-xl p-4 text-xs font-mono text-slate-400 overflow-auto max-h-64">{{ JSON.stringify(log.request_params, null, 2) }}</pre>
                </div>
                <div v-if="log.request_headers && Object.keys(log.request_headers).length" class="mt-4">
                    <p class="text-xs font-mono text-slate-500 uppercase mb-2">Headers</p>
                    <pre class="bg-black/40 border border-white/[0.06] rounded-xl p-4 text-xs font-mono text-slate-400 overflow-auto max-h-64">{{ JSON.stringify(log.request_headers, null, 2) }}</pre>
                </div>
            </div>

            <!-- Context -->
            <div v-if="log.context && Object.keys(log.context).length" class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-6">
                <h2 class="text-xs font-mono text-slate-400 uppercase tracking-wider mb-4">Context</h2>
                <div v-for="(value, key) in log.context" :key="key"
                     class="flex items-start gap-4 py-3 border-b border-white/[0.04] last:border-0">
                    <span class="text-xs font-mono text-slate-500 uppercase w-32 shrink-0 pt-0.5">{{ key }}</span>
                    <span class="font-mono text-xs text-slate-300 break-all">{{ value ?? '—' }}</span>
                </div>
            </div>

            <!-- Stack trace -->
            <div v-if="log.trace && log.trace.length" class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-6">
                <h2 class="text-xs font-mono text-slate-400 uppercase tracking-wider mb-4">Stack Trace</h2>
                <pre class="bg-black/40 border border-white/[0.06] rounded-xl p-4 text-xs font-mono text-slate-400 overflow-auto max-h-64"><template v-for="(frame, i) in log.trace" :key="i"><span class="text-slate-600">{{ String(i).padStart(3) }}</span>  <span v-if="frame.function" class="text-blue-300">{{ frame.function }}</span><span v-if="frame.file" class="text-slate-500">
     {{ frame.file }}<span v-if="frame.line" class="text-slate-400">:{{ frame.line }}</span></span>
</template></pre>
            </div>
        </div>
    </main>
</template>

<style scoped>
.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-mono { font-family: 'DM Mono', monospace; }
</style>
