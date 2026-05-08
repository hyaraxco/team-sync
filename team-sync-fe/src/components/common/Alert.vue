<template>
    <div
        v-if="visible"
        class="rounded-xl p-4 mb-6 flex items-start gap-3 shadow-sm border transition-all duration-300"
        :class="[
            type === 'danger'
                ? 'bg-red-50/50 border-red-200 text-red-700'
                : 'bg-green-50/50 border-green-200 text-green-700',
        ]"
        role="alert"
        aria-live="polite"
    >
        <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            :stroke="type === 'danger' ? '#DC2626' : '#16A34A'"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="mt-0.5 shrink-0"
        >
            <template v-if="type === 'danger'">
                <circle cx="12" cy="12" r="10" />
                <line x1="15" y1="9" x2="9" y2="15" />
                <line x1="9" y1="9" x2="15" y2="15" />
            </template>
            <template v-else>
                <circle cx="12" cy="12" r="10" />
                <polyline points="9 12 11 14 15 10" />
            </template>
        </svg>
        <div class="flex-1 space-y-1">
            <h4 class="text-sm font-semibold tracking-tight m-0">
                {{ title }}
            </h4>
            <p class="text-sm opacity-90 leading-relaxed m-0">
                {{ message }}
            </p>
        </div>
        <button
            @click="visible = false"
            class="ml-2 p-1.5 rounded-lg transition-colors hover:bg-black/5 focus:outline-none focus:ring-2"
            :class="type === 'danger' ? 'focus:ring-red-500/20' : 'focus:ring-green-500/20'"
            aria-label="Close"
            type="button"
        >
            <svg
                width="16"
                height="16"
                viewBox="0 0 20 20"
                fill="none"
                :stroke="type === 'danger' ? '#DC2626' : '#16A34A'"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <line x1="5" y1="5" x2="15" y2="15" />
                <line x1="15" y1="5" x2="5" y2="15" />
            </svg>
        </button>
    </div>
</template>

<script setup>
import { ref, watch } from "vue";

const props = defineProps({
    type: { type: String, default: "success" }, // success | danger
    title: { type: String, required: true },
    message: { type: String, required: true },
    show: { type: Boolean, default: true },
});

const visible = ref(props.show);

watch(
    () => props.show,
    (val) => {
        visible.value = val;
    },
);
</script>
