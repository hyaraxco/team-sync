<template>
    <div class="policy-mismatches-container p-3 sm:p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <header class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
                <div>
                    <span class="sr-only" role="heading" aria-level="1">Policy Mismatches</span>
                    <p class="text-2xl font-bold text-brand-dark">Policy Mismatches</p>
                    <p class="text-brand-light text-sm mt-1">
                        Review and resolve discrepancies between employee scheduled work locations and actual attendance data.
                    </p>
                </div>
            </header>

            <!-- Error State -->
            <div
                v-if="error"
                class="bg-white border border-red-200 rounded-2xl p-6 flex items-center gap-3 text-red-600"
            >
                <AlertTriangle class="w-5 h-5 shrink-0" />
                <p>Unable to load policy mismatches. Please try again later.</p>
            </div>

            <!-- Loading State -->
            <div v-else-if="loading" class="rounded-2xl border border-brand-border bg-white p-4 space-y-4">
                <div v-for="i in 5" :key="i" class="h-16 bg-brand-border/40 rounded-2xl animate-pulse" />
            </div>

            <!-- Empty State -->
            <div v-else-if="!mismatches.length" class="bg-white border border-brand-border rounded-2xl p-6">
                <EmptyState
                    icon="Inbox"
                    title="No policy mismatches found"
                    subtitle="All attendance logs match scheduled work locations."
                    size="lg"
                />
            </div>

            <!-- Table -->
            <div v-else class="bg-white border border-brand-border rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-brand-border">
                                 <th class="p-4 text-left text-xs font-semibold text-brand-light uppercase tracking-wide">Employee</th>
                                 <th class="p-4 text-left text-xs font-semibold text-brand-light uppercase tracking-wide">Date</th>
                                 <th class="p-4 text-left text-xs font-semibold text-brand-light uppercase tracking-wide">Scheduled</th>
                                 <th class="p-4 text-left text-xs font-semibold text-brand-light uppercase tracking-wide">Actual</th>
                                 <th class="p-4 text-right text-xs font-semibold text-brand-light uppercase tracking-wide">Actions</th>
                             </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            <tr
                                v-for="item in mismatches"
                                :key="item.id"
                                class="hover:bg-brand-border/20 transition-colors"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-red-50 text-red-600 flex items-center justify-center text-xs font-bold">
                                            {{ getEmployeeName(item).charAt(0) }}
                                        </div>
                                        <span class="font-medium text-brand-dark">{{ getEmployeeName(item) }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-brand-light text-sm">{{ item.mismatch_date || item.date }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                                        {{ formatWorkMode(item.planned_work_mode || item.scheduled_location) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs rounded-full bg-red-50 text-red-700 border border-red-200">
                                        {{ formatWorkMode(item.actual_work_mode || item.actual_location) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <button
                                        @click="acknowledge(item.id)"
                                        class="text-xs px-3 py-1.5 rounded-lg border border-brand-border text-brand-dark hover:bg-brand-border/20 transition-colors cursor-pointer"
                                    >
                                        Acknowledge
                                    </button>
                                    <button
                                        @click="resolve(item.id)"
                                        class="text-xs px-3 py-1.5 rounded-lg bg-green-50 text-green-700 border border-green-200 hover:bg-green-100 transition-colors cursor-pointer"
                                    >
                                        Resolve
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useAttendanceStore } from "@/stores/attendance";
import { useToast } from "@/composables/useToast";
import { AlertTriangle } from "lucide-vue-next";
import EmptyState from "@/components/common/EmptyState.vue";

const attendanceStore = useAttendanceStore();
const toast = useToast();
const mismatches = ref([]);
const loading = ref(true);
const error = ref(false);

onMounted(async () => {
    try {
        const response = await attendanceStore.fetchPolicyMismatches();
        mismatches.value = response?.data?.data || response?.data || [];
    } catch (err) {
        toast.error(
            "Failed to load policy mismatches",
            attendanceStore.error || err?.response?.data?.message || "Failed to load mismatches.",
        );
        error.value = true;
    } finally {
        loading.value = false;
    }
});

const getEmployeeName = (item) =>
    item?.staff_member?.user?.name || item?.staff_member?.name || item?.employee_name || "Unknown";

const formatWorkMode = (value) => String(value || "-").replaceAll("_", " ");

const acknowledge = async (id) => {
    try {
        await attendanceStore.acknowledgePolicyMismatch(id, "Acknowledged by HR");
        mismatches.value = mismatches.value.filter((m) => m.id !== id);
    } catch (_err) {
        toast.error("Failed to acknowledge mismatch");
    }
};

const resolve = async (id) => {
    try {
        await attendanceStore.resolvePolicyMismatch(id, "Resolved to match scheduled");
        mismatches.value = mismatches.value.filter((m) => m.id !== id);
    } catch (_err) {
        toast.error("Failed to resolve mismatch");
    }
};
</script>
