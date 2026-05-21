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
            <h1 class="text-3xl font-bold text-brand-dark">Sasaran Tim</h1>
            <p class="text-brand-light mt-1">Monitor and manage your team's goals</p>
        </div>

        <MainCard>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-brand-dark mb-2">Status</label>
                <select
                    v-model="selectedStatus"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                >
                    <option value="all">All Statuses</option>
                    <option value="not_started">Not Started</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="at_risk">At Risk</option>
                </select>
            </div>
        </MainCard>

        <div v-if="goalsLoading" class="flex justify-center items-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"></div>
        </div>

        <div v-else-if="filteredGoals.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <MainCard
                v-for="goal in filteredGoals"
                :key="goal.id"
                class="hover:shadow-xl transition-all duration-200 cursor-pointer group relative overflow-hidden"
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
                            {{ goal.description || "No description provided" }}
                        </p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-medium text-brand-light">Progress</span>
                            <span class="text-sm font-bold text-brand-dark">{{ goal.completion_percentage }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-300"
                                :class="getProgressColor(goal.completion_percentage)"
                                :style="{ width: `${goal.completion_percentage}%` }"
                            ></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-3 border-t border-gray-100">
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

                    <div class="flex items-center justify-between pt-3 border-t border-gray-100">
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

        <EmptyState
            v-else
            icon="Target"
            title="No team goals found"
            subtitle="Try adjusting your filters or ask team members to create goals."
        />
    </div>
</template>
