<script setup>
import { TrendingUp, TrendingDown } from "lucide-vue-next";
import * as Icons from "lucide-vue-next";
import { computed } from "vue";
import { useAnimatedNumber } from "@/composables/useAnimatedNumber";

const props = defineProps({
    title: String,
    value: [String, Number],
    subtitle: String,
    trendLabel: String,
    isTrendUp: {
        type: Boolean,
        default: true,
    },
    iconName: String,
    loading: Boolean,
});

// Extract numeric part and suffix (e.g., "1,234" → 1234, "" / "92%" → 92, "%")
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
    if (numericValue.value === 0 && props.value) return props.value;
    return animatedNum.value + suffix.value;
});

const resolveIcon = computed(() => Icons[props.iconName] || Icons.HelpCircle);
</script>

<template>
    <!-- Wrapper mode: when default slot content is provided, render as a plain card -->
    <div
        v-if="$slots.default"
        class="bg-white border border-brand-border rounded-2xl p-4 sm:p-5"
    >
        <slot></slot>
    </div>

    <!-- Stat card mode: when no default slot, render the metric card -->
    <div v-else class="main-card rounded-2xl border border-[#0B1042] relative overflow-hidden p-4 sm:p-5">
        <div class="flex flex-col justify-center h-full relative z-10">
            <!-- Trending Badge -->
            <div v-if="trendLabel" class="flex items-center gap-2 mb-3">
                <div class="flex items-center gap-1 px-3 py-1 bg-white/20 rounded-full backdrop-blur-sm">
                    <TrendingUp v-if="isTrendUp" aria-hidden="true" class="w-3 h-3 text-white" />
                    <TrendingDown v-else aria-hidden="true" class="w-3 h-3 text-white" />
                    <span class="text-brand-white text-xs font-semibold">{{ trendLabel }}</span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-3">
                <div>
                    <p class="text-brand-white-90 text-sm font-medium">{{ title }}</p>
                    <p class="text-brand-white text-3xl sm:text-5xl font-extrabold leading-none my-4 tabular-nums">
                        {{ animatedDisplay }}
                    </p>
                    <p class="text-brand-white-80 text-base font-normal">
                        {{ subtitle }}
                    </p>
                </div>
                <div
                    class="w-12 h-12 sm:w-16 sm:h-16 bg-white/20 rounded-2xl flex items-center justify-center shrink-0"
                >
                    <component :is="resolveIcon" aria-hidden="true" class="w-6 h-6 sm:w-8 sm:h-8 text-white" />
                </div>
            </div>

            <!-- Additional Info Slot -->
            <div v-if="$slots.footer" class="flex items-center gap-3 mt-auto">
                <slot name="footer"></slot>
            </div>
        </div>
    </div>
</template>
