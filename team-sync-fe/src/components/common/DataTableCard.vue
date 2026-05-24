<script setup>
import Pagination from "@/components/common/Pagination.vue";

defineProps({
    meta: {
        type: Object,
        default: () => ({ total: 0 }),
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["page-change", "per-page-change"]);
</script>

<template>
    <div class="rounded-2xl border border-brand-border overflow-hidden" style="background-color: var(--color-surface);">
        <div class="overflow-x-auto">
            <slot></slot>
        </div>

        <div class="p-4 border-t border-brand-border" style="background-color: var(--color-surface-overlay);">
            <Pagination
                :meta="meta"
                :loading="loading"
                @page-change="emit('page-change', $event)"
                @per-page-change="emit('per-page-change', $event)"
            />
        </div>
    </div>
</template>
