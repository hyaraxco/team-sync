<script setup>
import { formatDateShort, formatTime } from "@/utils/dateUtils";
import EmptyState from "@/components/common/EmptyState.vue";

defineProps({
    corrections: {
        type: Array,
        required: true,
    },
});

const getStatusBadge = (status) => {
    switch (status) {
        case "approved":
            return "bg-green-100 text-green-700";
        case "rejected":
            return "bg-red-100 text-red-700";
        default:
            return "bg-amber-100 text-amber-700";
    }
};
</script>

<template>
    <div class="bg-white border flex-1 border-brand-border rounded-2xl p-6 mb-6">
        <h3 class="text-brand-dark text-lg font-bold mb-6">Pengajuan Koreksi Saya</h3>

        <div v-if="!corrections || corrections.length === 0">
            <EmptyState title="Belum ada koreksi" description="Anda belum membuat pengajuan koreksi absensi." />
        </div>
        <div v-else class="space-y-4">
            <div
                v-for="correction in corrections"
                :key="correction.id"
                class="border rounded-xl p-4 flex flex-col md:flex-row justify-between gap-4"
            >
                <div>
                    <p class="font-bold text-brand-dark">
                        {{ correction.attendance ? formatDateShort(correction.attendance.date) : "Unknown Date" }}
                    </p>
                    <p class="text-sm text-brand-light mt-1">{{ correction.reason }}</p>

                    <div class="flex gap-4 mt-3">
                        <div class="text-xs bg-gray-50 px-2 py-1 rounded">
                            <span class="font-semibold text-gray-500 block">Requested In</span>
                            <span class="text-brand-dark">
                                {{ correction.requested_check_in ? formatTime(correction.requested_check_in) : "-" }}
                            </span>
                        </div>
                        <div class="text-xs bg-gray-50 px-2 py-1 rounded">
                            <span class="font-semibold text-gray-500 block">Requested Out</span>
                            <span class="text-brand-dark">
                                {{ correction.requested_check_out ? formatTime(correction.requested_check_out) : "-" }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col items-end justify-between">
                    <span
                        :class="[
                            'px-3 py-1 rounded-full text-xs font-semibold capitalize',
                            getStatusBadge(correction.status),
                        ]"
                    >
                        {{ correction.status }}
                    </span>
                    <p v-if="correction.review_notes" class="text-xs text-brand-light mt-2 max-w-xs text-right italic">
                        Note: {{ correction.review_notes }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
