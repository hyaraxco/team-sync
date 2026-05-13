<template>
    <div class="attendance-periods-container p-3 sm:p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <header class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-brand-dark">Attendance Periods</h1>
                    <p class="text-brand-light text-sm mt-1">
                        Monitor period statuses, review timesheets, and access the payroll readiness workspace before cutoff.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        class="px-4 py-2 rounded-[8px] border border-[#2151A0] blue-gradient blue-btn-shadow text-white font-medium text-sm hover:brightness-110 transition-all cursor-pointer"
                        @click="openCreateModal"
                    >
                        <Plus class="w-4 h-4 inline mr-1" />
                        Create Period
                    </button>
                    <button
                        class="px-4 py-2 rounded-[8px] border border-[#DCDEDD] text-brand-dark font-medium text-sm hover:bg-gray-50 transition-colors cursor-pointer"
                        @click="fetchData"
                    >
                        Sync Latest
                    </button>
                </div>
            </header>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-4">
                    <h2 class="text-lg font-bold text-brand-dark">Period History</h2>

                    <!-- Error State -->
                    <div
                        v-if="periodStore.error"
                        class="bg-white border border-red-200 rounded-[20px] p-6 flex items-center gap-3 text-red-600"
                    >
                        <AlertTriangle class="w-5 h-5 shrink-0" />
                        <p>Failed to load attendance periods. Please try again later.</p>
                    </div>

                    <!-- Loading State -->
                    <div v-else-if="periodStore.loading" class="space-y-3">
                        <div v-for="i in 4" :key="i" class="h-20 bg-gray-100 rounded-[16px] animate-pulse" />
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else-if="!periods.length"
                        class="bg-white border border-[#DCDEDD] rounded-[20px] p-12 text-center"
                    >
                        <Calendar class="w-12 h-12 mx-auto mb-3 text-gray-400" />
                        <p class="text-brand-dark font-semibold">No attendance periods found</p>
                        <p class="text-brand-light text-sm mt-1">Create a period to get started.</p>
                    </div>

                    <!-- Period List -->
                    <div v-else class="space-y-3">
                        <div
                            v-for="period in periods"
                            :key="period.id"
                            @click="selectPeriod(period)"
                            class="group flex items-center justify-between p-4 bg-white border rounded-[16px] transition-all duration-200 cursor-pointer"
                            :class="
                                selectedPeriod?.id === period.id
                                    ? 'border-[#0C51D9] shadow-md'
                                    : 'border-[#DCDEDD] hover:border-[#0C51D9] hover:border-2'
                            "
                        >
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-10 h-10 rounded-full flex items-center justify-center font-semibold text-sm"
                                    :class="{
                                        'bg-green-50 text-green-700': period.status === 'open',
                                        'bg-yellow-50 text-yellow-700': period.status === 'review',
                                        'bg-gray-100 text-gray-600': period.status === 'locked',
                                    }"
                                >
                                    {{ new Date(period.start_date).toLocaleString("default", { month: "short" }) }}
                                </div>
                                <div>
                                    <h3 class="font-semibold text-brand-dark">{{ period.month }}</h3>
                                    <p class="text-sm text-brand-light">{{ period.start_date }} — {{ period.end_date }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <span
                                    class="px-2 py-1 text-xs font-semibold uppercase rounded-full border"
                                    :class="{
                                        'bg-green-50 border-green-200 text-green-700': period.status === 'open',
                                        'bg-yellow-50 border-yellow-200 text-yellow-700': period.status === 'review',
                                        'bg-gray-100 border-gray-200 text-gray-600': period.status === 'locked',
                                    }"
                                >
                                    {{ period.status }}
                                </span>
                                <ChevronRight class="w-4 h-4 text-gray-400 group-hover:text-brand-dark transition-colors" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Readiness Sidebar -->
                <div class="lg:col-span-1">
                    <div class="sticky top-8 bg-white border border-[#DCDEDD] rounded-[20px] p-6">
                        <h2 class="text-lg font-bold text-brand-dark mb-4 flex items-center gap-2">
                            <CheckCircle class="w-5 h-5 text-green-500" />
                            Readiness Workspace
                        </h2>

                        <div v-if="!selectedPeriod" class="text-center py-12 px-4">
                            <Calendar class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                            <p class="text-brand-light text-sm">Select an attendance period to view payroll readiness.</p>
                        </div>

                        <div v-else class="space-y-4">
                            <div class="p-4 rounded-[12px] bg-gray-50 border border-[#DCDEDD]">
                                <p class="text-sm text-brand-light mb-1">Selected Period</p>
                                <p class="text-lg font-semibold text-brand-dark">{{ selectedPeriod.month }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="p-3 rounded-[12px] bg-green-50 border border-green-200">
                                    <p class="text-2xl font-bold text-green-700">{{ readinessCounts.ready }}</p>
                                    <p class="text-xs text-green-600 font-semibold">Ready</p>
                                </div>
                                <div class="p-3 rounded-[12px] bg-yellow-50 border border-yellow-200">
                                    <p class="text-2xl font-bold text-yellow-700">{{ readinessCounts.warnings }}</p>
                                    <p class="text-xs text-yellow-600 font-semibold">Warnings</p>
                                </div>
                                <div class="p-3 rounded-[12px] bg-red-50 border border-red-200 col-span-2 flex justify-between items-center">
                                    <div>
                                        <p class="text-2xl font-bold text-red-700">{{ readinessCounts.blocked }}</p>
                                        <p class="text-xs text-red-600 font-semibold">Blocked</p>
                                    </div>
                                    <button class="px-3 py-1.5 rounded-lg bg-red-100 text-red-700 text-sm font-medium hover:bg-red-200 transition-colors cursor-pointer">
                                        Review
                                    </button>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-[#DCDEDD]">
                                <button
                                    class="w-full py-3 rounded-[8px] font-semibold transition-all cursor-pointer"
                                    :class="
                                        selectedPeriod.status === 'review'
                                            ? 'border border-[#2151A0] blue-gradient blue-btn-shadow text-white hover:brightness-110'
                                            : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                    "
                                    :disabled="selectedPeriod.status !== 'review'"
                                >
                                    Generate Payroll
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Period Modal -->
        <ModalWrapper
            :show="isCreateModalOpen"
            title="Create Attendance Period"
            maxWidth="md"
            @close="closeCreateModal"
        >
            <form class="space-y-4" @submit.prevent="submitCreateForm">
                <div>
                    <label for="start-date" class="block text-sm font-medium text-brand-dark mb-2">Start Date</label>
                    <input
                        id="start-date"
                        v-model="createForm.start_date"
                        type="date"
                        required
                        class="w-full px-4 py-2 border border-[#DCDEDD] rounded-[8px] focus:border-[#0C51D9] focus:ring-1 focus:ring-[#0C51D9] outline-none transition-colors"
                    />
                </div>

                <div>
                    <label for="end-date" class="block text-sm font-medium text-brand-dark mb-2">End Date</label>
                    <input
                        id="end-date"
                        v-model="createForm.end_date"
                        type="date"
                        required
                        class="w-full px-4 py-2 border border-[#DCDEDD] rounded-[8px] focus:border-[#0C51D9] focus:ring-1 focus:ring-[#0C51D9] outline-none transition-colors"
                    />
                </div>

                <div>
                    <label for="cutoff-date" class="block text-sm font-medium text-brand-dark mb-2">Cutoff Date</label>
                    <input
                        id="cutoff-date"
                        v-model="createForm.cutoff_date"
                        type="date"
                        required
                        class="w-full px-4 py-2 border border-[#DCDEDD] rounded-[8px] focus:border-[#0C51D9] focus:ring-1 focus:ring-[#0C51D9] outline-none transition-colors"
                    />
                </div>

                <div class="flex gap-3 pt-4">
                    <button
                        type="button"
                        :disabled="isSubmitting"
                        class="flex-1 px-4 py-2.5 rounded-[8px] border border-[#DCDEDD] text-brand-dark font-medium text-sm hover:bg-gray-50 transition-colors cursor-pointer"
                        @click="closeCreateModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="flex-1 px-4 py-2.5 rounded-[8px] border border-[#2151A0] blue-gradient blue-btn-shadow text-white font-medium text-sm hover:brightness-110 transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                    >
                        {{ isSubmitting ? "Creating..." : "Create Period" }}
                    </button>
                </div>
            </form>
        </ModalWrapper>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { Plus, Calendar, CheckCircle, AlertTriangle, ChevronRight } from "lucide-vue-next";
import { useAttendancePeriodStore } from "@/stores/attendancePeriod";
import { useToast } from "@/composables/useToast";
import ModalWrapper from "@/components/common/ModalWrapper.vue";

const periodStore = useAttendancePeriodStore();
const { paginatedPeriods, meta, loading, error } = storeToRefs(periodStore);
const toast = useToast();
const selectedPeriod = ref(null);

const periods = computed(() => paginatedPeriods.value || periodStore.periods || []);

// Create modal
const isCreateModalOpen = ref(false);
const createForm = ref({
    start_date: "",
    end_date: "",
    cutoff_date: "",
});

const isSubmitting = ref(false);

const readinessCounts = computed(() => {
    const summary = periodStore.readinessSummary || {};

    return {
        ready: summary.ready_count ?? summary.ready ?? 0,
        warnings: summary.warning_count ?? summary.warnings ?? 0,
        blocked: summary.blocked_count ?? summary.blocked ?? 0,
    };
});

const selectPeriod = async (period) => {
    selectedPeriod.value = period;
    try {
        await periodStore.fetchReadiness(period);
    } catch (error) {
        toast.error(
            "Failed to load readiness",
            periodStore.error || error?.response?.data?.message || "Failed to load readiness.",
        );
    }
};

// Create modal handlers
const openCreateModal = () => {
    isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
    isCreateModalOpen.value = false;
    createForm.value = {
        start_date: "",
        end_date: "",
        cutoff_date: "",
    };
};

const submitCreateForm = async () => {
    isSubmitting.value = true;
    try {
        await periodStore.createPeriod(createForm.value);
        toast.success("Created", "Attendance period has been created successfully.");
        closeCreateModal();
        await fetchData();
    } catch (error) {
        toast.error(
            "Failed to create",
            periodStore.error || error?.response?.data?.message || "Failed to create attendance period.",
        );
    } finally {
        isSubmitting.value = false;
    }
};

const fetchData = async () => {
    await periodStore.fetchAllPaginated();
};

onMounted(async () => {
    try {
        await periodStore.fetchAllPaginated();
    } catch (error) {
        toast.error(
            "Failed to load attendance periods",
            periodStore.error || error?.response?.data?.message || "Failed to load periods.",
        );
    }
});
</script>
