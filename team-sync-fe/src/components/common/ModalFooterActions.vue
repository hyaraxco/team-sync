<script setup>
import { computed } from "vue";

const props = defineProps({
    processing: {
        type: Boolean,
        default: false,
    },
    confirmLabel: {
        type: String,
        default: "Confirm",
    },
    processingLabel: {
        type: String,
        default: "",
    },
    cancelLabel: {
        type: String,
        default: "Cancel",
    },
    confirmColor: {
        type: String,
        default: "green",
        validator: (v) => ["green", "red", "blue"].includes(v),
    },
    confirmDisabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["cancel", "confirm"]);

const confirmColorClass = computed(() => {
    const map = {
        green: "bg-green-600 hover:bg-green-700",
        red: "bg-red-600 hover:bg-red-700",
        blue: "btn-primary blue-gradient blue-btn-shadow hover:brightness-110",
    };
    return map[props.confirmColor] || map.green;
});

const displayProcessingLabel = computed(() => {
    if (props.processingLabel) return props.processingLabel;
    const label = props.confirmLabel;
    if (label.endsWith("e")) return label.slice(0, -1) + "ing...";
    return label + "ing...";
});
</script>

<template>
    <div class="flex gap-3">
        <button
            type="button"
            @click="emit('cancel')"
            :disabled="processing"
            class="flex-1 px-4 py-3 border border-brand-border rounded-xl text-brand-dark text-sm font-semibold hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
        >
            {{ cancelLabel }}
        </button>
        <button
            type="button"
            @click="emit('confirm')"
            :disabled="processing || confirmDisabled"
            class="flex-1 px-4 py-3 text-sm font-semibold text-white rounded-xl transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
            :class="confirmColorClass"
        >
            {{ processing ? displayProcessingLabel : confirmLabel }}
        </button>
    </div>
</template>
