<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    tenants: {
        type: Array,
        default: () => [],
    },
});

const open = ref(false);

const current = () => props.tenants.find(t => t.isCurrent);

function switchTo(slug) {
    open.value = false;
    router.post('/tenant/switch', { tenant_slug: slug });
}
</script>

<template>
    <div v-if="tenants.length > 1" class="relative">
        <button
            @click="open = !open"
            class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-sm text-slate-300 hover:text-white hover:bg-white/5 transition"
        >
            <div class="flex items-center gap-2 min-w-0">
                <svg class="w-4 h-4 shrink-0 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
                <span class="truncate text-xs">{{ current()?.name ?? 'Switch tenant' }}</span>
            </div>
            <svg
                class="w-3 h-3 shrink-0 transition-transform"
                :class="open ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div
            v-if="open"
            class="absolute top-full left-0 mt-1 w-full bg-slate-800 border border-white/10 rounded-lg shadow-lg overflow-hidden z-30"
        >
            <div class="py-1">
                <template v-for="tenant in tenants" :key="tenant.slug">
                    <div v-if="tenant.isCurrent" class="flex items-center gap-2 px-3 py-2 text-xs text-blue-400 font-medium">
                        <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        <span class="truncate">{{ tenant.name }}</span>
                    </div>
                    <div
                        v-else-if="tenant.isMaintenance"
                        class="flex items-center gap-2 px-3 py-2 text-xs text-slate-600 cursor-not-allowed"
                        :title="tenant.name + ' is under maintenance'"
                    >
                        <span class="w-3 h-3 shrink-0" />
                        <span class="truncate">{{ tenant.name }}</span>
                        <span class="ml-auto shrink-0 text-slate-600">🔧</span>
                    </div>
                    <button
                        v-else
                        type="button"
                        class="w-full text-left flex items-center gap-2 px-3 py-2 text-xs text-slate-400 hover:text-white hover:bg-white/5 transition"
                        @click="switchTo(tenant.slug)"
                    >
                        <span class="w-3 h-3 shrink-0" />
                        <span class="truncate">{{ tenant.name }}</span>
                    </button>
                </template>
            </div>
        </div>

        <div v-if="open" class="fixed inset-0 z-20" @click="open = false" />
    </div>
</template>
