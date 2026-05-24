<script setup>
import EmptyState from "@/components/common/EmptyState.vue";

defineProps({
    loading: {
        type: Boolean,
        required: true,
    },
    empty: {
        type: Boolean,
        required: true,
    },
    colspan: {
        type: Number,
        required: true,
    },
    emptyIcon: {
        type: String,
        default: "Inbox",
    },
    emptyTitle: {
        type: String,
        default: "No data found",
    },
    emptySubtitle: {
        type: String,
        default: "",
    },
});
</script>

<template>
    <tr v-if="loading">
        <td :colspan="colspan" class="py-14">
            <div class="flex justify-center" role="status" aria-label="Loading data">
                <div class="w-8 h-8 border-4 border-brand-border border-t-brand-primary rounded-full animate-spin"></div>
            </div>
        </td>
    </tr>
    <tr v-else-if="empty">
        <td :colspan="colspan" class="py-12">
            <EmptyState :icon="emptyIcon" :title="emptyTitle" :subtitle="emptySubtitle" />
        </td>
    </tr>
</template>
