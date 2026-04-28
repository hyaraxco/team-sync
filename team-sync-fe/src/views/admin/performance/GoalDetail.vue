<script setup>
import { computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useRoute, useRouter } from "vue-router";
import {
    ArrowLeft,
    Calendar,
    Target,
    TrendingUp,
    CheckCircle2,
    Clock,
    AlertTriangle,
    XCircle,
} from "lucide-vue-next";
import { usePerformanceGoalStore } from "@/stores/performanceGoal";
import { useAuthStore } from "@/stores/auth";
import MainCard from "@/components/common/MainCard.vue";
import StatusBadge from "@/components/common/StatusBadge.vue";
import EmptyState from "@/components/common/EmptyState.vue";

const route = useRoute();
const router = useRouter();
const performanceGoalStore = usePerformanceGoalStore();
const authStore = useAuthStore();

const { currentGoal, goalsLoading, goalUpdates, updatesLoading } =
    storeToRefs(performanceGoalStore);
const { user } = storeToRefs(authStore);

const goalId = computed(() => route.params.id);

const goalTypes = [
    { value: "okr", label: "OKR", color: "bg-purple-100 text-purple-700" },
    { value: "kpi", label: "KPI", color: "bg-blue-100 text-blue-700" },
    {
        value: "development",
        label: "Development",
        color: "bg-green-100 text-green-700",
    },
    {
        value: "project",
        label: "Project",
        color: "bg-orange-100 text-orange-700",
    },
];

const statusConfig = {
    not_started: { label: "Not Started", color: "secondary", icon: Clock },
    in_progress: { label: "In Progress", color: "info", icon: TrendingUp },
    at_risk: { label: "At Risk", color: "warning", icon: AlertTriangle },
    completed: { label: "Completed", color: "success", icon: CheckCircle2 },
    cancelled: { label: "Cancelled", color: "danger", icon: XCircle },
};

const getTypeColor = (type) => {
    return (
        goalTypes.find((goalType) => goalType.value === type)?.color ||
        "bg-gray-100 text-gray-700"
    );
};

const getProgressColor = (percentage) => {
    const normalizedPercentage = Number(percentage || 0);

    if (normalizedPercentage >= 80) return "bg-green-500";
    if (normalizedPercentage >= 50) return "bg-blue-500";
    if (normalizedPercentage >= 25) return "bg-yellow-500";
    return "bg-red-500";
};

const resolvedAssigneeName = computed(() => {
    return (
        currentGoal.value?.assignee?.full_name ||
        currentGoal.value?.staff_member?.full_name ||
        currentGoal.value?.staff_member_name ||
        user.value?.name ||
        "-"
    );
});

const handleBack = () => {
    router.back();
};

onMounted(async () => {
    await performanceGoalStore.fetchGoalById(goalId.value);

    if (typeof performanceGoalStore.fetchProgressUpdates === "function") {
        await performanceGoalStore.fetchProgressUpdates(goalId.value);
    }
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <button
                class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                @click="handleBack"
            >
                <ArrowLeft class="w-5 h-5" />
            </button>
            <div>
                <h1 class="text-3xl font-bold text-brand-dark">Goal Details</h1>
                <p class="text-brand-light mt-1">
                    Track goal progress, metrics, and update timeline
                </p>
            </div>
        </div>

        <div v-if="goalsLoading" class="flex justify-center items-center py-12">
            <div
                class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"
            ></div>
        </div>

        <template v-else-if="currentGoal">
            <MainCard>
                <div class="space-y-6">
                    <div
                        class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4"
                    >
                        <div>
                            <h2 class="text-2xl font-bold text-brand-dark">
                                {{ currentGoal.title }}
                            </h2>
                            <p class="text-brand-light mt-2">
                                {{ currentGoal.description || "No description provided" }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <span
                                class="px-3 py-1 rounded-full text-xs font-semibold"
                                :class="getTypeColor(currentGoal.goal_type)"
                            >
                                {{
                                    goalTypes.find(
                                        (goalType) =>
                                            goalType.value === currentGoal.goal_type
                                    )?.label || currentGoal.goal_type
                                }}
                            </span>
                            <StatusBadge
                                :value="currentGoal.status"
                                :label="statusConfig[currentGoal.status]?.label"
                            />
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-brand-light">Progress</span>
                            <span class="text-sm font-bold text-brand-dark"
                                >{{ currentGoal.completion_percentage || 0 }}%</span
                            >
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-300"
                                :class="getProgressColor(currentGoal.completion_percentage)"
                                :style="{ width: `${currentGoal.completion_percentage || 0}%` }"
                            ></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="p-4 border border-gray-100 rounded-xl">
                            <p class="text-xs text-brand-light uppercase tracking-wide">
                                Assignee
                            </p>
                            <p class="text-sm font-semibold text-brand-dark mt-1">
                                {{ resolvedAssigneeName }}
                            </p>
                        </div>

                        <div class="p-4 border border-gray-100 rounded-xl">
                            <p class="text-xs text-brand-light uppercase tracking-wide">
                                Category
                            </p>
                            <p class="text-sm font-semibold text-brand-dark mt-1">
                                {{ currentGoal.category || "-" }}
                            </p>
                        </div>

                        <div class="p-4 border border-gray-100 rounded-xl">
                            <p class="text-xs text-brand-light uppercase tracking-wide">
                                Start Date
                            </p>
                            <p class="text-sm font-semibold text-brand-dark mt-1">
                                {{
                                    currentGoal.start_date
                                        ? new Date(currentGoal.start_date).toLocaleDateString()
                                        : "-"
                                }}
                            </p>
                        </div>

                        <div class="p-4 border border-gray-100 rounded-xl">
                            <p class="text-xs text-brand-light uppercase tracking-wide">
                                Due Date
                            </p>
                            <p class="text-sm font-semibold text-brand-dark mt-1">
                                {{
                                    currentGoal.due_date
                                        ? new Date(currentGoal.due_date).toLocaleDateString()
                                        : "-"
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="p-4 border border-gray-100 rounded-xl">
                            <p class="text-xs text-brand-light uppercase tracking-wide">
                                Target Value
                            </p>
                            <p class="text-lg font-bold text-brand-dark mt-1">
                                {{ currentGoal.target_value || "-" }}
                            </p>
                        </div>

                        <div class="p-4 border border-gray-100 rounded-xl">
                            <p class="text-xs text-brand-light uppercase tracking-wide">
                                Current Value
                            </p>
                            <p class="text-lg font-bold text-brand-dark mt-1">
                                {{ currentGoal.current_value || "-" }}
                            </p>
                        </div>

                        <div class="p-4 border border-gray-100 rounded-xl">
                            <p class="text-xs text-brand-light uppercase tracking-wide">
                                Unit
                            </p>
                            <p class="text-lg font-bold text-brand-dark mt-1">
                                {{ currentGoal.unit || "-" }}
                            </p>
                        </div>
                    </div>
                </div>
            </MainCard>

            <MainCard>
                <div class="flex items-center gap-2 mb-4">
                    <TrendingUp class="w-5 h-5 text-brand-primary" />
                    <h3 class="text-lg font-semibold text-brand-dark">Progress Timeline</h3>
                </div>

                <div
                    v-if="updatesLoading"
                    class="flex justify-center items-center py-8"
                >
                    <div
                        class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-primary"
                    ></div>
                </div>

                <div v-else-if="goalUpdates?.length" class="space-y-3">
                    <div
                        v-for="update in goalUpdates"
                        :key="update.id"
                        class="p-4 border border-gray-100 rounded-lg"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-brand-dark">
                                    {{
                                        update.note ||
                                        update.description ||
                                        "Progress update"
                                    }}
                                </p>
                                <p class="text-xs text-brand-light mt-1">
                                    {{
                                        update.created_at
                                            ? new Date(update.created_at).toLocaleString()
                                            : "-"
                                    }}
                                </p>
                            </div>
                            <span
                                v-if="update.progress_percentage !== undefined"
                                class="text-sm font-bold text-brand-primary"
                            >
                                {{ update.progress_percentage }}%
                            </span>
                        </div>
                    </div>
                </div>

                <EmptyState
                    v-else
                    icon="CalendarClock"
                    title="No progress updates yet"
                    subtitle="Progress history will appear here once updates are added."
                    size="sm"
                />
            </MainCard>
        </template>

        <EmptyState
            v-else
            icon="Target"
            title="Goal not found"
            subtitle="The requested goal could not be loaded."
        />
    </div>
</template>
