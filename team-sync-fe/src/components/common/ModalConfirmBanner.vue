<script setup>
import { Check, X } from "lucide-vue-next";

defineProps({
    variant: {
        type: String,
        default: "green",
        validator: (v) => ["green", "red"].includes(v),
    },
    message: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div
        class="flex items-center gap-3 rounded-xl border p-4"
        :class="
            variant === 'green'
                ? 'border-green-200 bg-green-50'
                : 'border-red-200 bg-red-50'
        "
    >
        <div
            class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
            :class="variant === 'green' ? 'bg-green-100' : 'bg-red-100'"
        >
            <Check
                v-if="variant === 'green'"
                class="w-5 h-5 text-green-600"
                aria-hidden="true"
            />
            <X
                v-else
                class="w-5 h-5 text-red-600"
                aria-hidden="true"
            />
        </div>
        <p class="text-sm text-brand-dark">{{ message }}</p>
    </div>
</template>
