<script setup>
import { ref, onMounted, computed } from "vue";
import { storeToRefs } from "pinia";
import { usePerformanceGoalStore } from "@/stores/performanceGoal";
import { useRouter } from "vue-router";
import { Target, TrendingUp, Calendar, CheckCircle2, Clock, AlertTriangle, XCircle } from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import StatusBadge from "@/components/common/StatusBadge.vue";

const router = useRouter();
const performanceGoalStore = usePerformanceGoalStore();
const { teamGoals, goalsLoading } = storeToRefs(performanceGoalStore);

const selectedStatus = ref("all");

const filteredGoals = computed(() => {
    let goals = teamGoals.value;

    if (selectedStatus.value !== "all") {
        goals = goals.filter((goal) => goal.status === selectedStatus.value);
    }

    return goals;
});

const goalTypes = [
    { value: "okr", label: "OKR", color: "bg-[var(--color-surface-raised)] text-brand-dark" },
    { value: "kpi", label: "KPI", color: "bg-[var(--color-surface-raised)] text-brand-dark" },
    {
        value: "development",
        label: "Development",
        color: "bg-[var(--color-surface-raised)] text-brand-dark",
    },
    {
        value: "project",
        label: "Project",
        color: "bg-[var(--color-surface-raised)] text-brand-dark",
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
    return goalTypes.find((goalType) => goalType.value === type)?.color || "bg-gray-100 text-gray-700";
};

const getProgressColor = (percentage) => {
    if (percentage >= 80) return "bg-green-500";
    if (percentage >= 50) return "bg-blue-500";
    if (percentage >= 25) return "bg-yellow-500";
    return "bg-red-500";
};

const getDaysRemaining = (dueDate) => {
    const today = new Date();
    const due = new Date(dueDate);
    const diffTime = due - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays;
};

const getAssigneeName = (goal) => {
    return goal?.assignee?.full_name || goal?.staff_member?.full_name || goal?.staff_member_name || "Unassigned";
};

const viewGoal = (goalId) => {
    router.push({
        name: "admin.performance.goal.detail",
        params: { id: goalId },
    });
};

onMounted(async () => {
    await performanceGoalStore.fetchTeamGoals();
});
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-brand-dark">Team Goals</h1>
            <p class="text-brand-light mt-1">Monitor and manage your team's goals</p>
        </div>

        <div class="bg-[var(--color-surface)] border border-brand-border rounded-2xl p-4 sm:p-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-brand-border">
                <div class="w-full sm:w-64">
                    <label class="block text-xs font-semibold text-brand-light uppercase tracking-wide mb-1.5">Status</label>
                    <select
                        v-model="selectedStatus"
                        aria-label="Filter by status"
                        class="w-full px-4 py-2.5 border border-brand-border bg-[var(--color-surface)] text-brand-dark rounded-xl text-sm focus:ring-2 focus:ring-brand-primary"
                    >
                        <option value="all">All Statuses</option>
                        <option value="not_started">Not Started</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="at_risk">At Risk</option>
                    </select>
                </div>
            </div>

            <div v-if="goalsLoading" class="flex justify-center items-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-primary"></div>
            </div>

        <div v-else-if="filteredGoals.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <MainCard
                v-for="goal in filteredGoals"
                :key="goal.id"
                class="hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200 cursor-pointer group relative overflow-hidden"
                @click="viewGoal(goal.id)"
            >
                <div class="absolute top-4 right-4">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="getTypeColor(goal.goal_type)">
                        {{ goalTypes.find((goalType) => goalType.value === goal.goal_type)?.label }}
                    </span>
                </div>

                <div class="space-y-4">
                    <div>
                        <div class="flex items-start gap-3 mb-2">
                            <Target class="w-6 h-6 text-brand-primary flex-shrink-0 mt-1" />
                            <div>
                                <h3
                                    class="text-lg font-semibold text-brand-dark group-hover:text-brand-primary transition-colors"
                                >
                                    {{ goal.title }}
                                </h3>
                                <p class="text-xs text-brand-light mt-1">
                                    {{ getAssigneeName(goal) }}
                                </p>
                            </div>
                        </div>
                        <p class="text-sm text-brand-light line-clamp-2">
                            {{ goal.description || "Deskripsi belum tersedia" }}
                        </p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-medium text-brand-light">Progress</span>
                            <span class="text-sm font-bold text-brand-dark">{{ goal.completion_percentage }}%</span>
                        </div>
                        <div class="w-full bg-[var(--color-surface-overlay)] border border-brand-border rounded-full h-2 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-300"
                                :class="getProgressColor(goal.completion_percentage)"
                                :style="{ width: `${goal.completion_percentage}%` }"
                            ></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-3 border-t border-brand-border">
                        <div>
                            <p class="text-xs text-brand-light uppercase tracking-wide">Due Date</p>
                            <p class="text-sm font-medium text-brand-dark mt-1">
                                {{ goal.due_date ? new Date(goal.due_date).toLocaleDateString() : "-" }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-brand-light uppercase tracking-wide">Category</p>
                            <p class="text-sm font-medium text-brand-dark mt-1">
                                {{ goal.category || "-" }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-brand-border">
                        <div class="flex items-center gap-2">
                            <Calendar class="w-4 h-4 text-brand-light" />
                            <span class="text-xs text-brand-light">
                                Due {{ new Date(goal.due_date).toLocaleDateString() }}
                            </span>
                            <span
                                v-if="getDaysRemaining(goal.due_date) < 7 && goal.status !== 'completed'"
                                class="text-xs font-semibold text-red-600"
                            >
                                ({{ getDaysRemaining(goal.due_date) }} days left)
                            </span>
                        </div>
                        <StatusBadge :value="goal.status" :label="statusConfig[goal.status]?.label" />
                    </div>
                </div>
            </MainCard>
        </div>

        <div v-else class="py-12">
            <EmptyState
                icon="Target"
                title="Sasaran tim belum tersedia"
                subtitle="Sesuaikan filter atau minta anggota tim membuat sasaran."
                size="lg"
            />
        </div>
    </div>
    </div>
</template>
