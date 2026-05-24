<script setup>
import { DEFAULT_AVATAR } from "@/helpers/format";

defineProps({
    photo: {
        type: String,
        default: null,
    },
    name: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: "",
    },
});
</script>

<template>
    <div class="flex items-center gap-3">
        <img
            loading="lazy"
            :src="photo || DEFAULT_AVATAR"
            alt=""
            class="w-10 h-10 rounded-full object-cover border border-brand-border shadow-sm"
        />
        <div class="min-w-0">
            <p class="text-sm font-semibold text-brand-dark truncate">{{ name }}</p>
            <p v-if="subtitle" class="text-xs text-brand-light truncate">{{ subtitle }}</p>
        </div>
    </div>
</template>
