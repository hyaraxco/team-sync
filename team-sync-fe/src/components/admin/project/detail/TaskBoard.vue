<script setup>
import { ref, computed, onMounted } from "vue";
import { ListChecks, Plus, Search, ClipboardList } from "lucide-vue-next";
import { VueDraggableNext } from "vue-draggable-next";
import { useTaskStore } from "@/stores/task";
import { useAuthStore } from "@/stores/auth";
import { storeToRefs } from "pinia";
import { useRoute } from "vue-router";
import { useToast } from "@/composables/useToast";
import TaskCard from "./TaskCard.vue";
import TaskDetailModal from "./TaskDetailModal.vue";
import TaskCreateModal from "./TaskCreateModal.vue";

const props = defineProps({
    canCreateTask: {
        type: Boolean,
        default: false,
    },
});

const route = useRoute();
const toast = useToast();
const taskStore = useTaskStore();
const { tasks, loading } = storeToRefs(taskStore);
const { fetchProjectTasks, updateTaskStatus, createTask, deleteTask } = taskStore;
const authStore = useAuthStore();

const searchQuery = ref("");
const isModalOpen = ref(false);
const selectedTaskId = ref(null);
const isCreateModalOpen = ref(false);
const notice = ref({
    open: false,
    message: "",
    type: "error",
});
let noticeTimeoutId = null;

const showNotice = (message, type = "error") => {
    notice.value = {
        open: true,
        message,
        type,
    };

    if (noticeTimeoutId) {
        clearTimeout(noticeTimeoutId);
    }

    noticeTimeoutId = setTimeout(() => {
        notice.value.open = false;
    }, 2800);
};

const roleNames = computed(() => (authStore.user?.roles || []).map((role) => role.name || role));

const hasRole = (role) => roleNames.value.includes(role);

const currentEmployeeId = computed(() => authStore.user?.employee_profile?.id || authStore.user?.employeeProfile?.id);

const normalizeStatus = (status) => (status === "pending" ? "todo" : status);

const isOwnAssignedTask = (task) => !!task && currentEmployeeId.value === task.assignee_id;

const isProjectLeader = (task) => !!task && currentEmployeeId.value === task?.project?.leader?.id;

const canMoveTask = (task, targetStatus) => {
    if (!task) {
        return false;
    }

    const fromStatus = normalizeStatus(task.status);

    if (fromStatus === targetStatus) {
        return true;
    }

    if (hasRole("manager") || hasRole("hr") || isProjectLeader(task)) {
        const reviewerTransitions = {
            review: ["done", "rejected"],
            done: ["rejected"],
        };

        return (reviewerTransitions[fromStatus] || []).includes(targetStatus);
    }

    if (hasRole("staff") && isOwnAssignedTask(task)) {
        const employeeTransitions = {
            todo: ["in_progress"],
            in_progress: ["review"],
            rejected: ["in_progress"],
        };

        return (employeeTransitions[fromStatus] || []).includes(targetStatus);
    }

    return false;
};

const canDropToStatus = (event, targetStatus) => {
    const draggedTask = event?.draggedContext?.element;

    return canMoveTask(draggedTask, targetStatus);
};

const getMoveDeniedReason = (task, targetStatus) => {
    if (!task) {
        return "Task cannot be moved.";
    }

    const fromStatus = normalizeStatus(task.status);

    if (fromStatus === targetStatus) {
        return "Task is already in this status.";
    }

    if (hasRole("staff") && !isOwnAssignedTask(task)) {
        return "You can only move your own assigned tasks.";
    }

    if (hasRole("staff")) {
        return "Invalid status transition for employee workflow.";
    }

    if (hasRole("manager") || hasRole("hr") || isProjectLeader(task)) {
        return "Invalid reviewer transition. Allowed: review -> done/rejected and done -> rejected.";
    }

    return "You are not allowed to move this task.";
};

const handleColumnDrop = async (value, targetStatus) => {
    const currentTasksInColumn = tasks.value.filter((task) => normalizeStatus(task.status) === targetStatus);

    const movedTask = value.find((task) => !currentTasksInColumn.some((columnTask) => columnTask.id === task.id));

    if (!movedTask) {
        return;
    }

    if (!canMoveTask(movedTask, targetStatus)) {
        showNotice(getMoveDeniedReason(movedTask, targetStatus));
        await fetchProjectTasks(route.params.id);
        return;
    }

    if (normalizeStatus(movedTask.status) === targetStatus) {
        return;
    }

    try {
        await updateTaskStatus(movedTask.id, targetStatus);
    } catch (error) {
        toast.error("Failed to update task status. Please try again.");
        const serverMessage =
            error?.response?.data?.message ||
            error?.response?.data?.error ||
            "Failed to update task status. Please try again.";
        showNotice(serverMessage);
        await fetchProjectTasks(route.params.id);
    }
};

// Computed property to get always fresh task data from store
const selectedTask = computed(() => {
    if (!selectedTaskId.value) return null;
    return tasks.value.find((task) => task.id === selectedTaskId.value) || null;
});

const openTaskDetail = (task) => {
    selectedTaskId.value = task.id;
    isModalOpen.value = true;
};

const closeTaskDetail = () => {
    isModalOpen.value = false;
    selectedTaskId.value = null;
};

const openCreateModal = () => {
    isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
    isCreateModalOpen.value = false;
};

const handleCreateTask = async (taskData) => {
    try {
        await createTask(taskData);
        await fetchProjectTasks(route.params.id);
    } catch (error) {
        toast.error("Failed to create task. Please try again.");
    }
};

const handleDeleteTask = async (taskId) => {
    try {
        await deleteTask(taskId);
        await fetchProjectTasks(route.params.id);
    } catch (error) {
        toast.error("Failed to delete task. Please try again.");
    }
};

// Writable computed properties untuk drag and drop
const todoTask = computed({
    get: () => tasks.value.filter((task) => task.status === "todo" || task.status === "pending"),
    set: (value) => {
        handleColumnDrop(value, "todo");
    },
});

const inProgressTasks = computed({
    get: () => tasks.value.filter((task) => task.status === "in_progress"),
    set: (value) => {
        handleColumnDrop(value, "in_progress");
    },
});

const reviewTasks = computed({
    get: () => tasks.value.filter((task) => task.status === "review"),
    set: (value) => {
        handleColumnDrop(value, "review");
    },
});

const doneTasks = computed({
    get: () => tasks.value.filter((task) => task.status === "done"),
    set: (value) => {
        handleColumnDrop(value, "done");
    },
});

// Fetch tasks on mount
onMounted(async () => {
    const projectId = route.params.id;
    if (projectId) {
        await fetchProjectTasks(projectId);
    }
});
</script>

<template>
    <div class="bg-white border border-brand-border rounded-2xl p-6">
        <div v-if="notice.open" class="mb-4">
            <div
                :class="[
                    'rounded-lg px-3 py-2 text-sm font-medium border',
                    notice.type === 'error'
                        ? 'bg-red-50 text-red-700 border-red-100'
                        : 'bg-blue-50 text-blue-700 border-blue-100',
                ]"
            >
                {{ notice.message }}
            </div>
        </div>
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center">
                    <ListChecks class="w-6 h-6 text-purple-600" />
                </div>
                <div>
                    <h3 class="text-brand-dark text-xl font-bold">Project Tasks</h3>
                    <p class="text-brand-light text-sm font-normal">Manage and track all project tasks</p>
                </div>
            </div>
            <button
                v-if="canCreateTask"
                @click="openCreateModal"
                class="btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2"
            >
                <Plus class="w-4 h-4 text-white" />
                <span class="text-brand-white text-sm font-semibold">Create New Task</span>
            </button>
        </div>

        <!-- Tasks Filter -->
        <div class="mb-6">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <Search class="h-5 w-5 text-gray-400" />
                </div>
                <input
                    type="text"
                    class="w-full pl-12 pr-4 py-3 border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 font-semibold"
                    placeholder="Search tasks..."
                    v-model="searchQuery"
                />
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-if="tasks.length === 0 && !loading"
            class="flex flex-col items-center justify-center py-12 text-gray-400"
        >
            <ClipboardList class="h-12 w-12 mb-4 opacity-50" />
            <p class="text-lg font-medium mb-2">No tasks yet</p>
            <p class="text-sm mb-4">Create your first task to get started.</p>
            <button
                v-if="canCreateTask"
                @click="openCreateModal"
                class="inline-flex items-center rounded-lg bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary/90 transition-colors"
            >
                Create Task
            </button>
        </div>

        <!-- Task Management Columns -->
        <div
            v-else
            class="flex gap-4 overflow-x-auto pb-4 scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100"
        >
            <!-- To Do Column -->
            <div class="bg-gray-50 rounded-2xl p-4 flex-shrink-0 w-72">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 bg-gray-400 rounded-full"></div>
                        <h4 class="text-brand-dark text-base font-semibold">To Do</h4>
                    </div>
                    <span class="bg-gray-200 text-gray-700 px-2 py-1 rounded-full text-xs font-medium">
                        {{ todoTask.length }}
                    </span>
                </div>
                <VueDraggableNext
                    v-model="todoTask"
                    group="tasks"
                    class="space-y-3 min-h-[500px] max-h-[600px] overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent pr-2"
                    :animation="200"
                    :move="(event) => canDropToStatus(event, 'todo')"
                >
                    <TaskCard v-for="task in todoTask" :key="task.id" :task="task" @click="openTaskDetail(task)" />
                </VueDraggableNext>
            </div>

            <!-- In Progress Column -->
            <div class="bg-blue-50 rounded-2xl p-4 flex-shrink-0 w-72">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                        <h4 class="text-brand-dark text-base font-semibold">In Progress</h4>
                    </div>
                    <span class="bg-blue-200 text-blue-700 px-2 py-1 rounded-full text-xs font-medium">
                        {{ inProgressTasks.length }}
                    </span>
                </div>
                <VueDraggableNext
                    v-model="inProgressTasks"
                    group="tasks"
                    class="space-y-3 min-h-[500px] max-h-[600px] overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent pr-2"
                    :animation="200"
                    :move="(event) => canDropToStatus(event, 'in_progress')"
                >
                    <TaskCard
                        v-for="task in inProgressTasks"
                        :key="task.id"
                        :task="task"
                        @click="openTaskDetail(task)"
                    />
                </VueDraggableNext>
            </div>

            <!-- Review Column -->
            <div class="bg-yellow-50 rounded-2xl p-4 flex-shrink-0 w-72">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                        <h4 class="text-brand-dark text-base font-semibold">Review</h4>
                    </div>
                    <span class="bg-yellow-200 text-yellow-700 px-2 py-1 rounded-full text-xs font-medium">
                        {{ reviewTasks.length }}
                    </span>
                </div>
                <VueDraggableNext
                    v-model="reviewTasks"
                    group="tasks"
                    class="space-y-3 min-h-[500px] max-h-[600px] overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent pr-2"
                    :animation="200"
                    :move="(event) => canDropToStatus(event, 'review')"
                >
                    <TaskCard v-for="task in reviewTasks" :key="task.id" :task="task" @click="openTaskDetail(task)" />
                </VueDraggableNext>
            </div>

            <!-- Done Column -->
            <div class="bg-green-50 rounded-2xl p-4 flex-shrink-0 w-72">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                        <h4 class="text-brand-dark text-base font-semibold">Done</h4>
                    </div>
                    <span class="bg-green-200 text-green-700 px-2 py-1 rounded-full text-xs font-medium">
                        {{ doneTasks.length }}
                    </span>
                </div>
                <VueDraggableNext
                    v-model="doneTasks"
                    group="tasks"
                    class="space-y-3 min-h-[500px] max-h-[600px] overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent pr-2"
                    :animation="200"
                    :move="(event) => canDropToStatus(event, 'done')"
                >
                    <TaskCard v-for="task in doneTasks" :key="task.id" :task="task" @click="openTaskDetail(task)" />
                </VueDraggableNext>
            </div>
        </div>

        <!-- Task Detail Modal -->
        <TaskDetailModal
            :task="selectedTask"
            :is-open="isModalOpen"
            :project-id="parseInt(route.params.id)"
            @close="closeTaskDetail"
            @deleted="handleDeleteTask"
        />

        <!-- Task Create Modal -->
        <TaskCreateModal
            :is-open="isCreateModalOpen"
            :project-id="route.params.id"
            @close="closeCreateModal"
            @created="handleCreateTask"
        />
    </div>
</template>
