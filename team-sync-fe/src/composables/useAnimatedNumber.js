import { ref, watch, onUnmounted } from "vue";

/**
 * Composable for animated count-up numbers.
 * Animates from 0 → target using requestAnimationFrame with ease-out curve.
 *
 * @param {import('vue').Ref<number|string>} source - Reactive value to animate to
 * @param {Object} [options]
 * @param {number} [options.duration=800] - Animation duration in ms
 * @param {number} [options.decimals=0] - Decimal places to show
 * @returns {{ displayValue: import('vue').Ref<string> }}
 */
export function useAnimatedNumber(source, options = {}) {
    const { duration = 800, decimals = 0 } = options;

    const displayValue = ref("0");
    let animationId = null;
    const motionQuery = typeof window.matchMedia === "function"
        ? window.matchMedia("(prefers-reduced-motion: reduce)") : null;
    const reducedMotion = ref(motionQuery?.matches ?? false);
    const updateMotion = (event) => { reducedMotion.value = event.matches; };
    motionQuery?.addEventListener("change", updateMotion);

    function easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }

    function animate(from, to) {
        // Cancel any running animation
        if (animationId) {
            cancelAnimationFrame(animationId);
            animationId = null;
        }

        if (isNaN(to) || to === 0 || reducedMotion.value || duration <= 0) {
            displayValue.value = isNaN(to) ? String(to)
                : decimals > 0 ? to.toFixed(decimals) : to.toLocaleString();
            return;
        }

        const startTime = performance.now();

        function step(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const easedProgress = easeOutCubic(progress);
            const current = from + (to - from) * easedProgress;

            displayValue.value = decimals > 0 ? current.toFixed(decimals) : Math.round(current).toLocaleString();

            if (progress < 1) {
                animationId = requestAnimationFrame(step);
            } else {
                animationId = null;
            }
        }

        animationId = requestAnimationFrame(step);
    }

    watch(
        [source, reducedMotion],
        ([newVal], [oldVal] = []) => {
            const to = typeof newVal === "string" ? parseFloat(newVal) : Number(newVal);
            const from = oldVal == null ? 0 : typeof oldVal === "string" ? parseFloat(oldVal) : Number(oldVal);

            if (isNaN(to)) {
                displayValue.value = String(newVal);
                return;
            }

            animate(isNaN(from) ? 0 : from, to);
        },
        { immediate: true },
    );

    onUnmounted(() => {
        motionQuery?.removeEventListener("change", updateMotion);
        if (animationId) {
            cancelAnimationFrame(animationId);
        }
    });

    return { displayValue };
}
