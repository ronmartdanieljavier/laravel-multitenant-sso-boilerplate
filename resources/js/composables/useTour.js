import { onMounted, onUnmounted } from 'vue';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

/**
 * @param {string} tourName - Unique key for localStorage persistence
 * @param {Array<{element: string, title: string, description: string, side?: string, align?: string}>} steps
 * @returns {{ startTour: () => void }}
 */
export function useTour(tourName, steps) {
    let driverInstance = null;

    function startTour() {
        if (driverInstance) {
            driverInstance.destroy();
        }

        driverInstance = driver({
            showProgress: true,
            animate: true,
            overlayColor: 'rgba(2, 6, 23, 0.85)',
            popoverClass: 'app-tour-popover',
            nextBtnText: 'Next →',
            prevBtnText: '← Prev',
            doneBtnText: 'Done',
            onDestroyStarted: () => {
                if (typeof localStorage !== 'undefined') {
                    localStorage.setItem(`tour_seen_${tourName}`, '1');
                }
                driverInstance.destroy();

            },
            steps: steps.map((step) => ({
                element: step.element,
                popover: {
                    title: step.title,
                    description: step.description,
                    side: step.side ?? 'bottom',
                    align: step.align ?? 'start',
                },
            })),
        });

        driverInstance.drive();
    }

    onMounted(() => {
        if (typeof localStorage === 'undefined') { return; }
        const hasSeen = localStorage.getItem(`tour_seen_${tourName}`);
        if (!hasSeen) {
            setTimeout(startTour, 600);
        }
    });

    onUnmounted(() => {
        if (driverInstance) {
            driverInstance.destroy();
        }
    });

    return { startTour };
}
