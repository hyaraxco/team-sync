<script setup>
import { ref, onMounted, computed } from "vue";
import { storeToRefs } from "pinia";
import { usePerformanceGoalStore } from "@/stores/performanceGoal";
import { useRouter } from "vue-router";
import { useToast } from "@/composables/useToast";
import { can } from "@/helpers/permissionHelper";
import {
    Target,
    TrendingUp,
    Calendar,
    Plus,
    CheckCircle2,
    Clock,
    AlertTriangle,
    XCircle,
    X,
} from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import StatusBadge from "@/components/common/StatusBadge.vue";

const router = useRouter();
const goalStore = usePerformanceGoalStore();
const { myGoals, goalsLoading } = storeToRefs(goalStore);
const toast = useToast();

const selectedType = ref("all");
const selectedStatus = ref("all");
const showCreateModal = ref(false);
const createLoading = ref(false);

const defaultCreateForm = () => ({
    title: "",
    description: "",
    goal_type: "okr",
    category: "",
    start_date: "",
    due_date: "",
    target_value: "",
    unit: "",
});

const createForm = ref(defaultCreateForm());

const canCreateGoal = computed(() => can("goal-create-own"));

const filteredGoals = computed(() => {
    let goals = myGoals.value;
    if (selectedType.value !== "all") {
        goals = goals.filter((g) => g.goal_type === selectedType.value);
    }
    if (selectedStatus.value !== "all") {
        goals = goals.filter((g) => g.status === selectedStatus.value);
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
    return (
        goalTypes.find((t) => t.value === type)?.color ||
        "bg-gray-100 text-gray-700"
    );
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

const viewGoal = (goalId) => {
    router.push({
        name: "admin.performance.goal.detail",
        params: { id: goalId },
    });
};

const createGoal = () => {
    createForm.value = defaultCreateForm();
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    createForm.value = defaultCreateForm();
};

const submitCreateGoal = async () => {
    if (!createForm.value.title || !createForm.value.goal_type) {
        toast.warning("Title and goal type are required");
        return;
    }

    createLoading.value = true;

    try {
        const payload = {
            ...createForm.value,
            target_value:
                createForm.value.target_value === ""
                    ? null
                    : Number(createForm.value.target_value),
        };

        await goalStore.createGoal(payload);
        await goalStore.fetchMyGoals();
        toast.success("Goal created successfully");
        closeCreateModal();
    } catch (error) {
        toast.error(
            "Failed to create goal",
            error?.response?.data?.message ||
                error?.message ||
                "Please try again"
        );
    } finally {
        createLoading.value = false;
    }
};

onMounted(async () => {
    await goalStore.fetchMyGoals();
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-brand-dark">My Goals</h1>
                <p class="text-brand-light mt-1">
                    Track your objectives and key results
                </p>
            </div>
            <button
                v-if="canCreateGoal"
                class="flex items-center gap-2 px-4 py-2 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors"
                @click="createGoal"
            >
                <Plus class="w-5 h-5" />
                Create Goal
            </button>
        </div>

        <MainCard>
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-brand-dark mb-2"
                        >Goal Type</label
                    >
                    <select
                        v-model="selectedType"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                    >
                        <option value="all">All Types</option>
                        <option
                            v-for="type in goalTypes"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ type.label }}
                        </option>
                    </select>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-brand-dark mb-2"
                        >Status</label
                    >
                    <select
                        v-model="selectedStatus"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                    >
                        <option value="all">All Statuses</option>
                        <option value="not_started">Not Started</option>
                        <option value="in_progress">In Progress</option>
                        <option value="at_risk">At Risk</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </div>
        </MainCard>

        <div v-if="goalsLoading" class="flex justify-center items-center py-12">
            <div
                class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"
            ></div>
        </div>

        <div
            v-else-if="filteredGoals.length > 0"
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
        >
            <MainCard
                v-for="goal in filteredGoals"
                :key="goal.id"
                class="hover:shadow-xl transition-all duration-200 cursor-pointer group relative overflow-hidden"
                @click="viewGoal(goal.id)"
            >
                <div class="absolute top-4 right-4">
                    <span
                        class="px-3 py-1 rounded-full text-xs font-semibold"
                        :class="getTypeColor(goal.goal_type)"
                    >
                        {{ goalTypes.find((t) => t.value === goal.goal_type)?.label }}
                    </span>
                </div>

                <div class="space-y-4">
                    <div>
                        <div class="flex items-start gap-3 mb-2">
                            <Target class="w-6 h-6 text-brand-primary flex-shrink-0 mt-1" />
                            <h3
                                class="text-lg font-semibold text-brand-dark group-hover:text-brand-primary transition-colors"
                            >
                                {{ goal.title }}
                            </h3>
                        </div>
                        <p class="text-sm text-brand-light line-clamp-2">
                            {{ goal.description || "No description provided" }}
                        </p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-medium text-brand-light">Progress</span>
                            <span class="text-sm font-bold text-brand-dark"
                                >{{ goal.completion_percentage }}%</span
                            >
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
                            <p class="text-xs text-brand-light uppercase tracking-wide">
                                Target
                            </p>
                            <p class="text-sm font-medium text-brand-dark mt-1">
                                {{ goal.target_value || "-" }} {{ goal.unit || "" }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-brand-light uppercase tracking-wide">
                                Current
                            </p>
                            <p class="text-sm font-medium text-brand-dark mt-1">
                                {{ goal.current_value || "-" }} {{ goal.unit || "" }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between pt-3 border-t border-gray-100"
                    >
                        <div class="flex items-center gap-2">
                            <Calendar class="w-4 h-4 text-brand-light" />
                            <span class="text-xs text-brand-light">
                                Due {{ new Date(goal.due_date).toLocaleDateString() }}
                            </span>
                            <span
                                v-if="
                                    getDaysRemaining(goal.due_date) < 7 &&
                                    goal.status !== 'completed'
                                "
                                class="text-xs font-semibold text-red-600"
                            >
                                ({{ getDaysRemaining(goal.due_date) }} days left)
                            </span>
                        </div>
                        <StatusBadge
                            :value="goal.status"
                            :label="statusConfig[goal.status]?.label"
                        />
                    </div>
                </div>
            </MainCard>
        </div>

        <EmptyState
            v-else
            icon="Target"
            title="No Goals Yet"
            subtitle="Start setting goals to track your progress and achievements. Click 'Create Goal' to get started."
        >
            <button
                v-if="canCreateGoal"
                class="mt-4 flex items-center gap-2 px-6 py-3 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors"
                @click="createGoal"
            >
                <Plus class="w-5 h-5" />
                Create Your First Goal
            </button>
        </EmptyState>

        <div
            v-if="showCreateModal"
            class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
        >
            <MainCard class="w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-brand-dark">Create Goal</h2>
                    <button
                        class="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                        @click="closeCreateModal"
                    >
                        <X class="w-5 h-5 text-brand-light" />
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="submitCreateGoal">
                    <div>
                        <label class="block text-sm font-medium text-brand-dark mb-2"
                            >Title</label
                        >
                        <input
                            v-model="createForm.title"
                            type="text"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-brand-dark mb-2"
                            >Description</label
                        >
                        <textarea
                            v-model="createForm.description"
                            rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-brand-dark mb-2"
                                >Goal Type</label
                            >
                            <select
                                v-model="createForm.goal_type"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                            >
                                <option value="okr">OKR</option>
                                <option value="kpi">KPI</option>
                                <option value="development">Development</option>
                                <option value="project">Project</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-brand-dark mb-2"
                                >Category</label
                            >
                            <input
                                v-model="createForm.category"
                                type="text"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-brand-dark mb-2"
                                >Start Date</label
                            >
                            <input
                                v-model="createForm.start_date"
                                type="date"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-brand-dark mb-2"
                                >Due Date</label
                            >
                            <input
                                v-model="createForm.due_date"
                                type="date"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-brand-dark mb-2"
                                >Target Value</label
                            >
                            <input
                                v-model="createForm.target_value"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-brand-dark mb-2"
                                >Unit</label
                            >
                            <input
                                v-model="createForm.unit"
                                type="text"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                                placeholder="hours, %, tasks"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            @click="closeCreateModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="createLoading"
                            class="px-4 py-2 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors disabled:opacity-50"
                        >
                            {{ createLoading ? "Creating..." : "Create Goal" }}
                        </button>
                    </div>
                </form>
            </MainCard>
        </div>
    </div>
</template>
