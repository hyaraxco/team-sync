<template>
    <div class="attendance-periods-container min-h-screen bg-neutral-900 text-neutral-100 p-8">
        <div class="max-w-7xl mx-auto space-y-8 relative">
            <div
                class="absolute top-0 right-0 -mr-32 -mt-32 w-96 h-96 bg-indigo-600/20 rounded-full blur-[120px] pointer-events-none"
            ></div>

            <header
                class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-end gap-6 border-b border-white/30 pb-8"
            >
                <div class="space-y-2">
                    <h1
                        class="text-5xl font-extralight tracking-tight font-display bg-clip-text text-transparent bg-gradient-to-r from-white to-neutral-500"
                    >
                        Attendance Periods
                    </h1>
                    <p class="text-neutral-400 font-light tracking-wide max-w-xl">
                        Monitor period statuses, review timesheets, and access the payroll readiness workspace before
                        cutoff.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        class="px-6 py-2.5 rounded-full bg-white text-black font-medium text-sm hover:scale-105 transition-transform duration-300 shadow-lg shadow-white/10"
                        @click="openCreateModal"
                    >
                        <Plus class="w-4 h-4 inline mr-2" />
                        Create Period
                    </button>
                    <button
                        class="px-6 py-2.5 rounded-full bg-white/10 text-white font-medium text-sm hover:bg-white/20 transition-colors duration-300"
                        @click="fetchData"
                    >
                        Sync Latest
                    </button>
                </div>
            </header>

            <div class="relative z-10 grid gap-8 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-4">
                    <h2 class="text-xl font-light mb-4">Period History</h2>

                    <div
                        v-if="periodStore.error"
                        class="p-6 rounded-2xl border border-rose-500/20 bg-rose-500/5 text-rose-400 flex items-center gap-3"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            ></path>
                        </svg>
                        <p>Failed to load attendance periods. Please try again later.</p>
                    </div>

                    <div v-else-if="periodStore.loading" class="flex justify-center p-12">
                        <svg
                            class="animate-spin w-8 h-8 text-indigo-500"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                    </div>

                    <div
                        v-else-if="!periods.length"
                        class="text-center p-12 border border-dashed border-white/30 rounded-2xl text-neutral-500"
                    >
                        <p class="font-light italic">No attendance periods found.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="period in periods"
                            :key="period.id"
                            @click="selectPeriod(period)"
                            class="group flex items-center justify-between p-5 rounded-2xl bg-white/[0.08] border transition-all duration-300 cursor-pointer"
                            :class="
                                selectedPeriod?.id === period.id
                                    ? 'border-indigo-500/50 bg-white/[0.12] shadow-[0_0_30px_rgba(99,102,241,0.15)]'
                                    : 'border-white/15 hover:bg-white/[0.12] hover:border-white/30'
                            "
                        >
                            <div class="flex items-center gap-5">
                                <div
                                    class="w-12 h-12 rounded-full flex items-center justify-center font-display font-medium text-lg"
                                    :class="{
                                        'bg-emerald-500/10 text-emerald-400': period.status === 'open',
                                        'bg-amber-500/10 text-amber-400': period.status === 'review',
                                        'bg-neutral-500/10 text-neutral-400': period.status === 'locked',
                                    }"
                                >
                                    {{ new Date(period.start_date).toLocaleString("default", { month: "short" }) }}
                                </div>
                                <div>
                                    <h3
                                        class="text-lg font-medium text-white group-hover:text-indigo-300 transition-colors"
                                    >
                                        {{ period.month }}
                                    </h3>
                                    <p class="text-sm text-neutral-500 font-light">
                                        {{ period.start_date }} — {{ period.end_date }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <span
                                    class="px-3 py-1 text-xs font-semibold uppercase tracking-wider rounded-full border"
                                    :class="{
                                        'bg-emerald-500/10 border-emerald-500/20 text-emerald-400':
                                            period.status === 'open',
                                        'bg-amber-500/10 border-amber-500/20 text-amber-400':
                                            period.status === 'review',
                                        'bg-neutral-500/10 border-neutral-500/20 text-neutral-400':
                                            period.status === 'locked',
                                    }"
                                >
                                    {{ period.status }}
                                </span>
                                <svg
                                    class="w-5 h-5 text-neutral-600 group-hover:text-white transition-colors"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 5l7 7-7 7"
                                    ></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div
                        class="sticky top-8 p-6 rounded-3xl bg-neutral-800/50 backdrop-blur-xl border border-white/30 shadow-2xl"
                    >
                        <h2 class="text-xl font-light mb-6 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                ></path>
                            </svg>
                            Readiness Workspace
                        </h2>

                        <div v-if="!selectedPeriod" class="text-center py-12 px-4 opacity-50">
                            <svg
                                class="w-12 h-12 mx-auto mb-4 text-neutral-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                ></path>
                            </svg>
                            <p class="text-sm font-light">Select an attendance period to view payroll readiness.</p>
                        </div>

                        <div v-else class="space-y-6">
                            <div class="p-4 rounded-xl bg-white/10 border border-white/15">
                                <h3 class="text-sm text-neutral-400 font-light mb-1">Selected Period</h3>
                                <p class="text-lg font-medium text-white">{{ selectedPeriod.month }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-4 rounded-xl bg-emerald-500/5 border border-emerald-500/10">
                                    <p class="text-2xl font-light text-emerald-400 mb-1">{{ readinessCounts.ready }}</p>
                                    <p class="text-xs uppercase tracking-wider text-emerald-500/70 font-semibold">
                                        Ready
                                    </p>
                                </div>
                                <div class="p-4 rounded-xl bg-amber-500/5 border border-amber-500/10">
                                    <p class="text-2xl font-light text-amber-400 mb-1">
                                        {{ readinessCounts.warnings }}
                                    </p>
                                    <p class="text-xs uppercase tracking-wider text-amber-500/70 font-semibold">
                                        Warnings
                                    </p>
                                </div>
                                <div
                                    class="p-4 rounded-xl bg-rose-500/5 border border-rose-500/10 col-span-2 flex justify-between items-center"
                                >
                                    <div>
                                        <p class="text-2xl font-light text-rose-400 mb-1">
                                            {{ readinessCounts.blocked }}
                                        </p>
                                        <p class="text-xs uppercase tracking-wider text-rose-500/70 font-semibold">
                                            Blocked
                                        </p>
                                    </div>
                                    <button
                                        class="px-4 py-1.5 rounded-lg bg-rose-500/20 text-rose-300 text-sm hover:bg-rose-500/30 transition-colors"
                                    >
                                        Review
                                    </button>
                                </div>
                            </div>

                            <div class="pt-6 border-t border-white/30">
                                <button
                                    class="w-full py-3 rounded-xl font-medium tracking-wide transition-all"
                                    :class="
                                        selectedPeriod.status === 'review'
                                            ? 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-500/25'
                                            : 'bg-white/10 text-neutral-500 cursor-not-allowed'
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
                    <label class="block text-sm font-medium text-neutral-300 mb-2">Start Date</label>
                    <input
                        v-model="createForm.start_date"
                        type="date"
                        required
                        class="w-full px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-colors"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-300 mb-2">End Date</label>
                    <input
                        v-model="createForm.end_date"
                        type="date"
                        required
                        class="w-full px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-colors"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-300 mb-2">Cutoff Date</label>
                    <input
                        v-model="createForm.cutoff_date"
                        type="date"
                        required
                        class="w-full px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-colors"
                    />
                </div>

                <div class="flex gap-3 pt-4">
                    <button
                        type="button"
                        :disabled="isSubmitting"
                        class="flex-1 px-4 py-2.5 rounded-lg border border-white/20 text-white font-medium text-sm hover:bg-white/10 transition-colors"
                        @click="closeCreateModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="flex-1 px-4 py-2.5 rounded-lg bg-indigo-600 text-white font-medium text-sm hover:bg-indigo-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ isSubmitting ? "Creating..." : "Create Period" }}
                    </button>
                </div>
            </form>
        </ModalWrapper>

        <!-- Edit Status Modal -->
        <ModalWrapper :show="isEditModalOpen" title="Update Period Status" maxWidth="md" @close="closeEditModal">
            <form class="space-y-4" @submit.prevent="submitEditForm">
                <div>
                    <label class="block text-sm font-medium text-neutral-300 mb-2">Status</label>
                    <select
                        v-model="editForm.status"
                        required
                        class="w-full px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-colors"
                    >
                        <option value="open">Open</option>
                        <option value="review">Review</option>
                        <option value="locked">Locked</option>
                    </select>
                    <p class="mt-2 text-xs text-neutral-400">
                        Status flow: Open → Review → Locked. Cannot skip steps or revert locked periods.
                    </p>
                </div>

                <div class="flex gap-3 pt-4">
                    <button
                        type="button"
                        :disabled="isSubmitting"
                        class="flex-1 px-4 py-2.5 rounded-lg border border-white/20 text-white font-medium text-sm hover:bg-white/10 transition-colors"
                        @click="closeEditModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="flex-1 px-4 py-2.5 rounded-lg bg-indigo-600 text-white font-medium text-sm hover:bg-indigo-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ isSubmitting ? "Updating..." : "Update Status" }}
                    </button>
                </div>
            </form>
        </ModalWrapper>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { Plus } from "lucide-vue-next";
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

// Edit modal
const isEditModalOpen = ref(false);
const editForm = ref({
    status: "",
});
const editingPeriod = ref(null);

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

// Edit modal handlers
const openEditModal = (period) => {
    editingPeriod.value = period;
    editForm.value = {
        status: period.status,
    };
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    editForm.value = {
        status: "",
    };
    editingPeriod.value = null;
};

const submitEditForm = async () => {
    isSubmitting.value = true;
    try {
        await periodStore.updatePeriod(editingPeriod.value.id, editForm.value);
        toast.success("Updated", "Attendance period status has been updated successfully.");
        closeEditModal();
        await fetchData();
    } catch (error) {
        toast.error(
            "Failed to update",
            periodStore.error || error?.response?.data?.message || "Failed to update attendance period.",
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

<style scoped>
.font-display {
    font-family: "Outfit", "Inter", sans-serif;
}
</style>
