import { onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

const ACTIVITY_EVENTS = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'];

export function useIdleTimeout(minutes) {
    if (!minutes || minutes <= 0) {
        return;
    }

    let timer = null;

    function reset() {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.post('/logout');
        }, minutes * 60 * 1000);
    }

    onMounted(() => {
        reset();
        ACTIVITY_EVENTS.forEach((event) => window.addEventListener(event, reset, { passive: true }));
    });

    onUnmounted(() => {
        clearTimeout(timer);
        ACTIVITY_EVENTS.forEach((event) => window.removeEventListener(event, reset));
    });
}
