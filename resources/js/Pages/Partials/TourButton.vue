<script setup>
defineProps({
    label: {
        type: String,
        default: 'Take a tour',
    },
});

defineEmits(['click']);
</script>

<template>
    <Teleport to="body">
        <button
            class="tour-trigger-btn"
            :title="label"
            @click="$emit('click')"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ label }}</span>
        </button>
    </Teleport>
</template>

<style>
.tour-trigger-btn {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 0.875rem;
    background: rgba(30, 41, 59, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 9999px;
    color: #94a3b8;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    backdrop-filter: blur(8px);
    transition: color 0.15s ease, border-color 0.15s ease, background 0.15s ease;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
}

.tour-trigger-btn:hover {
    color: #e2e8f0;
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(51, 65, 85, 0.95);
}

/* ── driver.js dark-theme overrides ── */
.app-tour-popover {
    background: #1e293b !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 0.75rem !important;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6) !important;
    padding: 1.25rem !important;
    color: #e2e8f0 !important;
    max-width: 320px !important;
}

.app-tour-popover .driver-popover-title {
    font-size: 0.9rem !important;
    font-weight: 600 !important;
    color: #f1f5f9 !important;
    margin-bottom: 0.375rem !important;
}

.app-tour-popover .driver-popover-description {
    font-size: 0.8125rem !important;
    color: #94a3b8 !important;
    line-height: 1.5 !important;
}

.app-tour-popover .driver-popover-progress-text {
    color: #64748b !important;
    font-size: 0.75rem !important;
}

.app-tour-popover .driver-popover-footer {
    margin-top: 1rem !important;
    gap: 0.5rem !important;
}

.app-tour-popover .driver-popover-prev-btn,
.app-tour-popover .driver-popover-next-btn,
.app-tour-popover .driver-popover-done-btn {
    background: rgba(255, 255, 255, 0.06) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 0.375rem !important;
    color: #e2e8f0 !important;
    font-size: 0.75rem !important;
    font-weight: 500 !important;
    padding: 0.375rem 0.75rem !important;
    cursor: pointer !important;
    transition: background 0.15s ease !important;
}

.app-tour-popover .driver-popover-prev-btn:hover,
.app-tour-popover .driver-popover-next-btn:hover,
.app-tour-popover .driver-popover-done-btn:hover {
    background: rgba(255, 255, 255, 0.12) !important;
}

.app-tour-popover .driver-popover-next-btn,
.app-tour-popover .driver-popover-done-btn {
    background: rgba(124, 58, 237, 0.3) !important;
    border-color: rgba(124, 58, 237, 0.4) !important;
    color: #c4b5fd !important;
}

.app-tour-popover .driver-popover-next-btn:hover,
.app-tour-popover .driver-popover-done-btn:hover {
    background: rgba(124, 58, 237, 0.45) !important;
}

.app-tour-popover .driver-popover-close-btn {
    color: #64748b !important;
    font-size: 1rem !important;
    opacity: 0.7 !important;
}

.app-tour-popover .driver-popover-close-btn:hover {
    opacity: 1 !important;
    color: #e2e8f0 !important;
}

.app-tour-popover .driver-popover-arrow-side-left.driver-popover-arrow {
    border-left-color: #1e293b !important;
}
.app-tour-popover .driver-popover-arrow-side-right.driver-popover-arrow {
    border-right-color: #1e293b !important;
}
.app-tour-popover .driver-popover-arrow-side-top.driver-popover-arrow {
    border-top-color: #1e293b !important;
}
.app-tour-popover .driver-popover-arrow-side-bottom.driver-popover-arrow {
    border-bottom-color: #1e293b !important;
}
</style>
