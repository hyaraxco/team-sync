<script setup>
import * as Icons from "lucide-vue-next";
import { computed, toRef } from "vue";
import { useAnimatedNumber } from "@/composables/useAnimatedNumber";

const props = defineProps({
    title: String,
    value: [String, Number],
    subtitle: String,
    subtitleColor: {
        type: String,
        default: "text-success", // 'text-success', 'text-danger', 'text-warning', 'text-brand-light'
    },
    iconName: String,
    colorScheme: {
        type: String,
        default: "blue", // "blue", "green", "purple", "orange", "red", "yellow", "teal", "cyan", "gray"
    },
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

const backgroundClass = computed(() => {
    const map = {
        blue: "bg-blue-50",
        green: "bg-green-50",
        purple: "bg-purple-50",
        orange: "bg-orange-50",
        red: "bg-red-50",
        yellow: "bg-yellow-50",
        teal: "bg-teal-50",
        cyan: "bg-cyan-50",
        gray: "bg-gray-50",
    };
    return map[props.colorScheme] || "bg-gray-50";
});

const iconTextClass = computed(() => {
    const map = {
        blue: "text-blue-600",
        green: "text-green-600",
        purple: "text-purple-600",
        orange: "text-orange-600",
        red: "text-red-600",
        yellow: "text-yellow-600",
        teal: "text-teal-600",
        cyan: "text-cyan-600",
        gray: "text-gray-600",
    };
    return map[props.colorScheme] || "text-gray-600";
});

const resolveIcon = computed(() => Icons[props.iconName] || Icons.HelpCircle);
</script>

<template>
    <div
        class="stats-card bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-4 sm:p-5 dark:bg-gray-800 dark:border-gray-700"
    >
        <div class="flex items-center justify-between">
            <div>
                <p class="text-brand-dark text-sm font-medium">{{ title }}</p>
                <p class="text-brand-dark text-2xl sm:text-3xl font-extrabold leading-tight my-2">
                    {{ animatedDisplay }}
                </p>
                <p v-if="subtitle" :class="subtitleColor" class="text-xs sm:text-sm font-medium">
                    {{ subtitle }}
                </p>
            </div>
            <div
                :class="backgroundClass"
                class="w-10 h-10 sm:w-12 sm:h-12 rounded-[12px] sm:rounded-[16px] flex items-center justify-center shrink-0 ml-2"
            >
                <component :is="resolveIcon" class="w-5 h-5 sm:w-6 sm:h-6" :class="iconTextClass" />
            </div>
        </div>
    </div>
</template>
