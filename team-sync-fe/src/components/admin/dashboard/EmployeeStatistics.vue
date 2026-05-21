<script setup>
import { ref, computed, onMounted } from "vue";
import { StarIcon, BellIcon, CheckCircle2, Clock3, Wallet, Calendar, Users, MessageSquare } from "lucide-vue-next";
import QuickActions from "./QuickActions.vue";
import StatsCard from "@/components/common/StatsCard.vue";
import MainCard from "@/components/common/MainCard.vue";
import { useAuthStore } from "@/stores/auth";
import { useDashboardStore } from "@/stores/dashboard";
import { useTaskStore } from "@/stores/task";
import { useStaffMemberStore } from "@/stores/staffMember";
import { useNotificationStore } from "@/stores/notifications";
import { useToast } from "@/composables/useToast";
import { RouterLink, useRouter } from "vue-router";
import { getTimeAgo } from "@/utils/dateUtils";

const authStore = useAuthStore();
const router = useRouter();
const toast = useToast();
const notificationStore = useNotificationStore();
const dashboardStore = useDashboardStore();

const statistics = ref({
    attendance_rate: 0,
    present_days: 0,
    absent_days: 0,
    sick_days: 0,
    late_days: 0,
    tasks_done: 0,
    tasks_done_yesterday: 0,
    tasks_in_progress: 0,
    tasks_todo: 0,
    tasks_review: 0,
    assigned_active_projects: 0,
    led_active_projects: 0,
    total_hours_worked: null,
    leave_balance: null,
});

const taskStore = useTaskStore();
const staffMemberStore = useStaffMemberStore();
const currentEmployeeId = computed(
    () => authStore.user?.employee_profile?.id ?? authStore.user?.employeeProfile?.id ?? null,
);
const upcomingTaskStatuses = new Set(["todo", "in_progress", "review", "rejected"]);

const normalizeTaskStatus = (status) => {
    const normalized = String(status ?? "")
        .trim()
        .toLowerCase();

    if (normalized === "pending") {
        return "todo";
    }

    return normalized;
};

const toDueDateTimestamp = (dueDate) => {
    const parsed = Date.parse(dueDate);

    if (Number.isNaN(parsed)) {
        return Number.POSITIVE_INFINITY;
    }

    return parsed;
};

const upcomingTasks = computed(() => {
    if (!Array.isArray(taskStore.tasks)) {
        return [];
    }

    return taskStore.tasks
        .filter((task) => {
            const status = normalizeTaskStatus(task?.status);
            if (!upcomingTaskStatuses.has(status)) {
                return false;
            }

            const taskAssigneeId = task?.assignee_id ?? task?.assignee?.id ?? null;
            if (taskAssigneeId === null || currentEmployeeId.value === null) {
                return false;
            }

            return String(taskAssigneeId) === String(currentEmployeeId.value);
        })
        .map((task) => ({
            id: task.id,
            title: task.title || task.name || "Task",
            project: task.project?.name || task.project_name || "-",
            priority: task.priority || "medium",
            dueDate: task.due_date || task.deadline || "",
            status: normalizeTaskStatus(task.status || "todo"),
        }))
        .sort((left, right) => toDueDateTimestamp(left.dueDate) - toDueDateTimestamp(right.dueDate));
});

const recentActivities = computed(() =>
    Array.isArray(notificationStore.notifications) ? notificationStore.notifications.slice(0, 4) : [],
);
const recentActivitiesLoading = computed(() => notificationStore.loading);
const recentActivitiesError = computed(() => notificationStore.error);

const statsLoading = ref(false);
const userName = computed(() => authStore.user?.name || "Employee");
const currentDayOfMonth = computed(() => new Date().getDate());
const onTimePercentage = computed(() => {
    const present = statistics.value.present_days || 0;
    const late = statistics.value.late_days || 0;
    if (present <= 0) return 0;
    const ontime = Math.max(0, present - late);
    return Math.round((ontime / present) * 100 * 10) / 10;
});

const getPriorityClass = (priority) => {
    switch (priority) {
        case "high":
            return "bg-red-100 text-red-600";
        case "medium":
            return "bg-yellow-100 text-yellow-600";
        case "low":
            return "bg-green-100 text-green-600";
        default:
            return "bg-gray-100 text-gray-600";
    }
};

const getStatusClass = (status) => {
    switch (status) {
        case "todo":
            return "bg-gray-100 text-gray-600";
        case "in_progress":
            return "bg-blue-100 text-blue-600";
        case "review":
            return "bg-purple-100 text-purple-600";
        case "rejected":
            return "bg-red-100 text-red-600";
        case "done":
            return "bg-green-100 text-green-600";
        default:
            return "bg-gray-100 text-gray-600";
    }
};

const getTaskStatusLabel = (status) => {
    switch (status) {
        case "todo":
            return "To Do";
        case "in_progress":
            return "In Progress";
        case "review":
            return "In Review";
        case "rejected":
            return "Needs Revision";
        case "done":
            return "Done";
        default:
            return status.replace("_", " ");
    }
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);

    if (date.toDateString() === today.toDateString()) return "Today";
    if (date.toDateString() === tomorrow.toDateString()) return "Tomorrow";

    return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
};

const resolveNotificationCategory = (notification) =>
    String(notification?.category ?? notification?.data?.category ?? notification?.type ?? "")
        .trim()
        .toLowerCase();

const getActivityIcon = (notification) => {
    const category = resolveNotificationCategory(notification);

    if (category.includes("task")) {
        return CheckCircle2;
    }

    if (category.includes("attendance") || category.includes("check")) {
        return Clock3;
    }

    if (category.includes("payroll") || category.includes("salary")) {
        return Wallet;
    }

    if (category.includes("comment") || category.includes("message")) {
        return MessageSquare;
    }

    if (category.includes("meeting") || category.includes("team")) {
        return Users;
    }

    return BellIcon;
};

const getActivityIconBgClass = (notification) => {
    const category = resolveNotificationCategory(notification);

    if (category.includes("task")) {
        return "bg-green-50";
    }

    if (category.includes("attendance") || category.includes("check")) {
        return "bg-purple-50";
    }

    if (category.includes("payroll") || category.includes("salary")) {
        return "bg-orange-50";
    }

    if (category.includes("comment") || category.includes("message")) {
        return "bg-blue-50";
    }

    if (category.includes("meeting") || category.includes("team")) {
        return "bg-orange-50";
    }

    return "bg-primary-50";
};

const getActivityIconClass = (notification) => {
    const category = resolveNotificationCategory(notification);

    if (category.includes("task")) {
        return "text-green-600";
    }

    if (category.includes("attendance") || category.includes("check")) {
        return "text-purple-600";
    }

    if (category.includes("payroll") || category.includes("salary")) {
        return "text-orange-600";
    }

    if (category.includes("comment") || category.includes("message")) {
        return "text-blue-600";
    }

    if (category.includes("meeting") || category.includes("team")) {
        return "text-orange-500";
    }

    return "text-brand-primary";
};

const getActivityTime = (notification) => {
    if (!notification?.created_at) {
        return "Recently";
    }

    return getTimeAgo(notification.created_at);
};

const handleActivitySelect = async (notification) => {
    const actionUrl = typeof notification?.action_url === "string" ? notification.action_url.trim() : "";

    if (!actionUrl) {
        return;
    }

    if (/^https?:\/\//i.test(actionUrl)) {
        window.location.assign(actionUrl);
        return;
    }

    await router.push(actionUrl);
};

const fetchRecentActivities = async () => {
    await notificationStore.fetchLatestNotifications(20);
};

const fetchMyStatistics = async () => {
    statsLoading.value = true;
    try {
        const data = await dashboardStore.fetchMyStatistics();

        const attendance = data.attendance || {};
        const tasks = data.tasks || {};
        const projects = data.projects || {};

        statistics.value = {
            attendance_rate: attendance.rate ?? 0,
            present_days: attendance.present_days ?? 0,
            absent_days: attendance.absent_days ?? 0,
            sick_days: attendance.sick_days ?? 0,
            late_days: attendance.late_days ?? 0,
            tasks_done: tasks.done ?? 0,
            tasks_done_yesterday: tasks.done_yesterday ?? 0,
            tasks_in_progress: tasks.in_progress ?? 0,
            tasks_todo: tasks.todo ?? 0,
            tasks_review: tasks.review ?? 0,
            assigned_active_projects: projects.assigned_active ?? 0,
            led_active_projects: projects.led_active ?? 0,
            total_hours_worked: attendance.total_hours_worked ?? null,
            leave_balance: data.leave_balance ?? null,
        };
    } catch (error) {
        toast.error("Failed to load employee statistics. Please try again.");
    } finally {
        statsLoading.value = false;
    }
};

onMounted(() => {
    fetchMyStatistics();
    fetchRecentActivities();
    staffMemberStore
        .fetchMyTeamProjects()
        .then((projects) => {
            const firstProjectId = Array.isArray(projects) && projects.length ? projects[0].id : null;
            if (firstProjectId) {
                return taskStore.fetchProjectTasks(firstProjectId);
            }
        })
        .catch(() => {});
});
</script>

<template>
    <!-- Employee Stats Layout -->
    <div class="mb-6">
        <!-- Welcome Message -->
        <div class="mb-4">
            <h2 class="text-brand-dark text-xl sm:text-2xl font-bold">Welcome back, {{ userName }}! 👋</h2>
            <p class="text-gray-600 text-xs sm:text-sm">Here's your performance overview</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <!-- My Attendance Card (spans 2 rows on the left) -->
            <MainCard
                class="lg:row-span-2"
                title="Attendance Rate"
                :value="(statsLoading ? '...' : statistics.attendance_rate) + '%'"
                :subtitle="`${statistics.present_days} of ${currentDayOfMonth} days`"
                iconName="CalendarCheckIcon"
                trendLabel="This Month"
                :isTrendUp="true"
                :loading="statsLoading"
            >
                <template #footer>
                    <div class="flex items-center gap-1">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span class="text-brand-white-70 text-xs font-normal">{{ onTimePercentage }}% On Time</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <StarIcon class="w-3 h-3 text-white opacity-70" />
                        <span class="text-brand-white-70 text-xs font-normal">Great Performance</span>
                    </div>
                </template>
            </MainCard>

            <!-- Row 1 Stats Cards -->
            <!-- Total Hours Worked -->
            <StatsCard
                title="Hours Worked"
                :value="(statistics.total_hours_worked ?? '-') + 'h'"
                subtitle="This month"
                subtitleColor="text-success"
                iconName="ClockIcon"
                colorScheme="blue"
                :loading="statsLoading"
            />

            <!-- Leave Balance -->
            <StatsCard
                title="Leave Balance"
                :value="statistics.leave_balance ?? '-'"
                subtitle="Days remaining"
                subtitleColor="text-gray-500"
                iconName="CalendarCheckIcon"
                colorScheme="green"
                :loading="statsLoading"
            />

            <!-- Quick Actions Card (spans 2 rows on the right) -->
            <QuickActions />

            <!-- Row 2 Stats Cards -->
            <!-- Tasks Done -->
            <StatsCard
                title="Tasks Done"
                :value="statistics.tasks_done || 0"
                :subtitle="`+${statistics.tasks_done_yesterday || 0} yesterday`"
                subtitleColor="text-success"
                iconName="CheckSquareIcon"
                colorScheme="purple"
                :loading="statsLoading"
            />

            <!-- Active Projects -->
            <StatsCard
                title="Active Projects"
                :value="statistics.assigned_active_projects || 0"
                :subtitle="`Assigned to you • Leading: ${statistics.led_active_projects || 0}`"
                subtitleColor="text-gray-500"
                iconName="FolderIcon"
                colorScheme="orange"
                :loading="statsLoading"
            />
        </div>
    </div>

    <!-- Additional Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-6">
        <!-- Upcoming Tasks -->
        <div class="bg-white border border-brand-border rounded-2xl p-4 sm:p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-brand-dark text-base sm:text-lg font-bold">Upcoming Tasks</h3>
                <a href="#" class="text-brand-primary text-xs sm:text-sm font-medium hover:underline">View All</a>
            </div>

            <div class="space-y-3">
                <div
                    v-for="task in upcomingTasks"
                    :key="task.id"
                    class="p-4 border border-brand-border rounded-xl hover:border-brand-primary transition-all duration-300"
                >
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1">
                            <h4 class="text-brand-dark text-sm font-semibold mb-1">
                                {{ task.title }}
                            </h4>
                            <p class="text-gray-500 text-xs">{{ task.project }}</p>
                        </div>
                        <span
                            :class="getPriorityClass(task.priority)"
                            class="px-2 py-1 rounded-md text-xs font-semibold capitalize"
                        >
                            {{ task.priority }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <Calendar class="w-3.5 h-3.5" />
                            <span>{{ formatDate(task.dueDate) }}</span>
                        </div>
                        <span
                            :class="getStatusClass(task.status)"
                            class="px-2 py-1 rounded-md text-xs font-medium capitalize"
                        >
                            {{ getTaskStatusLabel(task.status) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="bg-white border border-brand-border rounded-2xl p-4 sm:p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-brand-dark text-base sm:text-lg font-bold">Recent Activities</h3>
                <RouterLink
                    :to="{ name: 'admin.notifications' }"
                    data-testid="recent-activities-view-all"
                    class="text-brand-primary text-xs sm:text-sm font-medium hover:underline"
                >
                    View All
                </RouterLink>
            </div>

            <div v-if="recentActivitiesLoading" class="space-y-3">
                <div
                    v-for="index in 3"
                    :key="`activity-skeleton-${index}`"
                    class="flex items-start gap-3 animate-pulse"
                >
                    <div class="h-10 w-10 rounded-xl bg-primary-50"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-2.5 w-3/4 rounded-full bg-gray-200"></div>
                        <div class="h-2 w-1/3 rounded-full bg-gray-100"></div>
                    </div>
                </div>
            </div>

            <div v-else-if="recentActivitiesError" class="rounded-xl border border-red-100 bg-red-50 px-3 py-3">
                <p class="text-xs text-red-700">Failed to load activities.</p>
                <button
                    type="button"
                    class="mt-2 text-xs font-semibold text-red-700 hover:underline"
                    @click="fetchRecentActivities"
                >
                    Retry
                </button>
            </div>

            <div
                v-else-if="recentActivities.length === 0"
                class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-4 text-center"
            >
                <p class="text-sm font-medium text-slate-700">Belum ada aktivitas.</p>
            </div>

            <div v-else class="space-y-1">
                <button
                    v-for="activity in recentActivities"
                    :key="activity.id"
                    type="button"
                    class="flex w-full items-start gap-3 rounded-xl px-2 py-2 text-left transition-colors hover:bg-primary-50/40"
                    :disabled="!activity.action_url"
                    @click="handleActivitySelect(activity)"
                >
                    <div
                        class="relative h-10 w-10 flex-shrink-0 rounded-xl flex items-center justify-center"
                        :class="getActivityIconBgClass(activity)"
                    >
                        <component
                            :is="getActivityIcon(activity)"
                            class="w-5 h-5"
                            :class="getActivityIconClass(activity)"
                        />
                        <span
                            v-if="!activity.is_read"
                            class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-brand-primary ring-2 ring-white"
                        ></span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-brand-dark text-sm font-semibold leading-5">
                            {{ activity.title }}
                        </p>
                        <p v-if="activity.body" class="mt-0.5 text-xs text-gray-500 leading-5">
                            {{ activity.body }}
                        </p>
                        <p class="text-gray-500 text-xs mt-0.5">
                            {{ getActivityTime(activity) }}
                        </p>
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>
