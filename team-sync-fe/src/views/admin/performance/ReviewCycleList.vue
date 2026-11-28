<script setup>
import { ref, onMounted, computed } from "vue";
import { storeToRefs } from "pinia";
import { usePerformanceReviewStore } from "@/stores/performanceReview";
import { useRouter } from "vue-router";
import { useToast } from "@/composables/useToast";
import { Calendar, Plus, TrendingUp, Clock, CheckCircle2, XCircle, X, Play, Trash2 } from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import StatusBadge from "@/components/common/StatusBadge.vue";
import ConfirmationModal from "@/components/common/ConfirmationModal.vue";

const router = useRouter();
const reviewStore = usePerformanceReviewStore();
const { cycles, cyclesLoading } = storeToRefs(reviewStore);
const toast = useToast();

const selectedType = ref("all");
const selectedStatus = ref("all");

const hasActiveFilters = computed(() => {
    return selectedType.value !== "all" || selectedStatus.value !== "all";
});

const clearFilters = () => {
    selectedType.value = "all";
    selectedStatus.value = "all";
};

// Confirmation dialog state
const showConfirmDialog = ref(false);
const confirmTitle = ref("");
const confirmMessage = ref("");
const confirmAction = ref(null);
const confirming = ref(false);
const runConfirmation = async () => {
    if (confirming.value) return;
    confirming.value = true;
    try {
        await confirmAction.value?.();
        showConfirmDialog.value = false;
    } finally {
        confirming.value = false;
    }
};

const filteredCycles = computed(() => {
    let filtered = cycles.value;

    if (selectedType.value !== "all") {
        filtered = filtered.filter((c) => c.cycle_type === selectedType.value);
    }

    if (selectedStatus.value !== "all") {
        filtered = filtered.filter((c) => c.status === selectedStatus.value);
    }

    return filtered;
});

const statusConfig = {
    draft: { label: "Draft", color: "secondary", icon: Clock },
    active: { label: "Active", color: "success", icon: TrendingUp },
    completed: { label: "Completed", color: "info", icon: CheckCircle2 },
    cancelled: { label: "Cancelled", color: "danger", icon: XCircle },
};

const cycleTypes = [
    { value: "quarterly", label: "Quarterly" },
    { value: "semi_annual", label: "Semi-Annual" },
    { value: "annual", label: "Annual" },
    { value: "probation", label: "Probation" },
];

const createCycle = () => {
    router.push({ name: "admin.performance.cycles.create" });
};

const viewCycle = (cycleId) => {
    router.push({
        name: "admin.performance.cycles.detail",
        params: { id: cycleId },
    });
};

const canGenerateReviews = (cycle) => {
    return ["draft", "active"].includes(cycle.status);
};

const canDeleteCycle = (cycle) => {
    return cycle.status === "draft";
};

const handleGenerateReviews = async (cycle) => {
    if (confirming.value) return;
    confirmTitle.value = "Generate Reviews";
    confirmMessage.value = `Generate reviews for "${cycle.name}"? This will create reviews for all active employees.`;
    confirmAction.value = async () => {
        try {
            const result = await reviewStore.generateReviews(cycle.id);
            toast.success("Reviews generated", `Created ${result?.generated_count ?? 0} reviews.`);
            await reviewStore.fetchCycles();
        } catch (error) {
            toast.error(
                "Failed to generate reviews",
                reviewStore.error || error?.response?.data?.message || error?.message || "Please try again.",
            );
        }
    };
    showConfirmDialog.value = true;
};

const handleDeleteCycle = async (cycle) => {
    if (confirming.value) return;
    confirmTitle.value = "Delete Cycle";
    confirmMessage.value = `Delete cycle "${cycle.name}"? This action cannot be undone.`;
    confirmAction.value = async () => {
        try {
            await reviewStore.deleteCycle(cycle.id);
            toast.success("Cycle deleted successfully");
            await reviewStore.fetchCycles();
        } catch (error) {
            toast.error(
                "Failed to delete cycle",
                reviewStore.error || error?.response?.data?.message || error?.message || "Please try again.",
            );
        }
    };
    showConfirmDialog.value = true;
};

onMounted(async () => {
    await reviewStore.fetchCycles();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-brand-dark">Performance Review Cycles</h1>
                <p class="text-brand-light mt-1">Manage company-wide performance review cycles</p>
            </div>
            <button
                class="flex items-center gap-2 px-4 py-2 bg-primary-700 text-white rounded-lg hover:bg-primary-800 transition-colors font-semibold text-sm"
                @click="createCycle"
            >
                <Plus class="w-5 h-5" />
                Create Cycle
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-[var(--color-surface)] border border-brand-border rounded-2xl p-4 sm:p-6 space-y-6">
            <div class="flex flex-wrap items-end gap-4 pb-4 border-b border-brand-border">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-semibold text-brand-light uppercase tracking-wide mb-1.5">Cycle Type</label>
                    <select
                        v-model="selectedType"
                        aria-label="Filter by cycle type"
                        class="w-full px-4 py-2.5 border border-brand-border bg-[var(--color-surface)] text-brand-dark rounded-xl text-sm focus:ring-2 focus:ring-brand-primary"
                    >
                        <option value="all">All Types</option>
                        <option v-for="type in cycleTypes" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-semibold text-brand-light uppercase tracking-wide mb-1.5">Status</label>
                    <select
                        v-model="selectedStatus"
                        aria-label="Filter by status"
                        class="w-full px-4 py-2.5 border border-brand-border bg-[var(--color-surface)] text-brand-dark rounded-xl text-sm focus:ring-2 focus:ring-brand-primary"
                    >
                        <option value="all">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <button
                    v-if="hasActiveFilters"
                    @click="clearFilters"
                    class="px-4 py-2.5 rounded-xl border border-brand-border text-sm font-semibold text-brand-dark hover:bg-[var(--color-surface-overlay)] transition-colors flex items-center gap-2"
                >
                    <X class="w-4 h-4 text-brand-light" />
                    Clear filters
                </button>
            </div>

        <!-- Loading State -->
            <div v-if="cyclesLoading" class="flex justify-center items-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-primary"></div>
            </div>

        <!-- Cycles Grid -->
        <div v-else-if="filteredCycles.length > 0" class="grid gap-6">
            <MainCard
                v-for="cycle in filteredCycles"
                :key="cycle.id"
                class="hover:ring-2 hover:ring-brand-primary/20 transition-shadow duration-200 cursor-pointer"
                @click="viewCycle(cycle.id)"
            >
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <Calendar class="w-6 h-6 text-brand-primary" />
                            <h3 class="text-xl font-semibold text-brand-dark">
                                {{ cycle.name }}
                            </h3>
                            <StatusBadge :value="cycle.status" :label="statusConfig[cycle.status]?.label" />
                            <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm font-medium">
                                {{ cycleTypes.find((t) => t.value === cycle.cycle_type)?.label }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-4">
                            <div>
                                <p class="text-xs text-brand-light uppercase tracking-wide mb-1">Review Period</p>
                                <p class="text-sm font-medium text-brand-dark">
                                    {{ new Date(cycle.review_period_start).toLocaleDateString() }}
                                </p>
                                <p class="text-sm font-medium text-brand-dark">
                                    to
                                    {{ new Date(cycle.review_period_end).toLocaleDateString() }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-brand-light uppercase tracking-wide mb-1">Cycle Duration</p>
                                <p class="text-sm font-medium text-brand-dark">
                                    {{ new Date(cycle.start_date).toLocaleDateString() }}
                                </p>
                                <p class="text-sm font-medium text-brand-dark">
                                    to {{ new Date(cycle.end_date).toLocaleDateString() }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-brand-light uppercase tracking-wide mb-1">
                                    Self-Assessment Deadline
                                </p>
                                <p class="text-sm font-medium text-brand-dark">
                                    {{
                                        cycle.self_assessment_deadline
                                            ? new Date(cycle.self_assessment_deadline).toLocaleDateString()
                                            : "Not set"
                                    }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-brand-light uppercase tracking-wide mb-1">
                                    Manager Assessment Deadline
                                </p>
                                <p class="text-sm font-medium text-brand-dark">
                                    {{
                                        cycle.manager_assessment_deadline
                                            ? new Date(cycle.manager_assessment_deadline).toLocaleDateString()
                                            : "Not set"
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Progress Bar (if active) -->
                        <div v-if="cycle.status === 'active'" class="mt-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-medium text-brand-light">Cycle Progress</span>
                                <span class="text-xs text-brand-dark">
                                    {{
                                        Math.round(
                                            ((new Date() - new Date(cycle.start_date)) /
                                                (new Date(cycle.end_date) - new Date(cycle.start_date))) *
                                                100,
                                        )
                                    }}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    class="bg-brand-primary h-full rounded-full transition-all duration-300"
                                    :style="{
                                        width: `${Math.min(100, Math.round(((new Date() - new Date(cycle.start_date)) / (new Date(cycle.end_date) - new Date(cycle.start_date))) * 100))}%`,
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div class="ml-4">
                        <div class="flex flex-col gap-2">
                            <button
                                class="px-4 py-2 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors"
                                @click.stop="viewCycle(cycle.id)"
                            >
                                Manage
                            </button>
                            <button
                                v-if="canGenerateReviews(cycle)"
                                class="px-4 py-2 bg-[var(--color-surface-overlay)] border border-brand-border text-brand-dark rounded-lg hover:bg-[var(--color-surface)] transition-colors flex items-center justify-center gap-2 text-xs font-semibold"
                                @click.stop="handleGenerateReviews(cycle)"
                            >
                                <Play class="w-4 h-4" />
                                Generate Reviews
                            </button>
                            <button
                                v-if="canDeleteCycle(cycle)"
                                class="px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors flex items-center justify-center gap-2"
                                @click.stop="handleDeleteCycle(cycle)"
                            >
                                <Trash2 class="w-4 h-4" />
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </MainCard>
        </div>

        <!-- Empty State -->
            <div v-else class="py-12">
                <EmptyState
                    icon="Calendar"
                    title="Belum ada siklus review"
                    subtitle="Buat siklus review pertama untuk mulai mengevaluasi performa tim."
                    size="lg"
                >
                    <button
                        class="mt-4 inline-flex items-center gap-2 px-6 py-3 bg-primary-700 text-white rounded-lg hover:bg-primary-800 transition-colors font-semibold text-sm"
                        @click="createCycle"
                    >
                        <Plus class="w-5 h-5" />
                        Buat Siklus Pertama
                    </button>
                </EmptyState>
            </div>
        </div>
    </div>

    <!-- Confirmation Dialog -->
    <ConfirmationModal
        :show="showConfirmDialog"
        :loading="confirming"
        :title="confirmTitle"
        :message="confirmMessage"
        confirm-text="Confirm"
        cancel-text="Cancel"
        type="danger"
        @confirm="runConfirmation"
        @cancel="showConfirmDialog = false"
    />
</template>
