<script setup>
import * as Icons from "lucide-vue-next";
import { computed } from "vue";
import { useAnimatedNumber } from "@/composables/useAnimatedNumber";

const props = defineProps({
    title: String,
    value: [String, Number],
    subtitle: String,
    subtitleColor: {
        type: String,
        default: "text-brand-light",
    },
    iconName: String,
    loading: Boolean,
});

// Extract numeric part and suffix (e.g., "92%" → 92, "%")
const numericValue = computed(() => {
    if (props.loading) return 0;
    const val = String(props.value);
    const match = val.match(/^([\d,.]+)/);
    return match ? parseFloat(match[1].replace(/,/g, "")) : 0;
});

const suffix = computed(() => {
    const val = String(props.value);
    const match = val.match(/^[\d,.]+(.*)$/);
    return match ? match[1] : "";
});

const { displayValue: animatedNum } = useAnimatedNumber(numericValue);

const animatedDisplay = computed(() => {
    if (props.loading) return "...";
    if (numericValue.value === 0 && String(props.value) === "0") return "0";
    if (numericValue.value === 0 && props.value) return props.value; // non-numeric like '-'
    return animatedNum.value + suffix.value;
});


const resolveIcon = computed(() => Icons[props.iconName] || Icons.HelpCircle);
const subtitleStyle = computed(() => {
    const intent = { 'text-success': 'success', 'text-danger': 'danger', 'text-warning': 'warning' }[props.subtitleColor];
    return { color: intent ? `var(--color-feedback-${intent})` : 'var(--color-text-primary)' };
});
</script>

<template>
    <div
        data-testid="stats-card"
        class="border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 motion-reduce:transition-none p-5"
        style="background: var(--color-surface)"
    >
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium" style="color: var(--color-text-primary)">{{ title }}</p>
                <p v-if="loading" role="status" class="my-2 text-sm text-text-primary">Loading {{ title }}...</p>
                <p v-else class="text-2xl sm:text-3xl font-extrabold leading-tight my-2 tabular-nums" style="color: var(--color-text-primary)">
                    {{ animatedDisplay }}
                </p>
                <p v-if="subtitle && !loading" :style="subtitleStyle" class="text-xs sm:text-sm font-medium">
                    {{ subtitle }}
                </p>
            </div>
            <div
                class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl flex items-center justify-center shrink-0 ml-2 bg-[var(--color-surface-overlay)]"
            >
                <component :is="resolveIcon" aria-hidden="true" class="w-5 h-5 sm:w-6 sm:h-6 text-brand-dark" />
            </div>
        </div>
    </div>
</template>
