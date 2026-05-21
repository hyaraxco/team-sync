<script setup>
import { computed, onMounted, ref } from "vue";
import {
    BellIcon,
    CheckCircle2,
    CheckCheck,
    Clock3,
    MessageSquare,
    Users,
    Wallet,
    RefreshCw,
    ChevronLeftIcon,
    ChevronRightIcon,
} from "lucide-vue-next";
import { useRouter } from "vue-router";
import { useNotificationStore } from "@/stores/notifications";
import { getTimeAgo } from "@/utils/dateUtils";

const notificationStore = useNotificationStore();
const router = useRouter();

const currentPage = ref(1);
const perPage = 15;

const notifications = computed(() =>
    Array.isArray(notificationStore.notifications) ? notificationStore.notifications : [],
);
const loading = computed(() => notificationStore.loading);
const error = computed(() => notificationStore.error);
const meta = computed(() => notificationStore.meta);
const markingAllRead = computed(() => notificationStore.markingAllRead);

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
        return "bg-success-50";
    }

    if (category.includes("attendance") || category.includes("check")) {
        return "bg-purple-50";
    }

    if (category.includes("payroll") || category.includes("salary")) {
        return "bg-orange-50";
    }

    if (category.includes("comment") || category.includes("message")) {
        return "bg-primary-50";
    }

    if (category.includes("meeting") || category.includes("team")) {
        return "bg-orange-50";
    }

    return "bg-primary-50";
};

const getActivityIconClass = (notification) => {
    const category = resolveNotificationCategory(notification);

    if (category.includes("task")) {
        return "text-success-600";
    }

    if (category.includes("attendance") || category.includes("check")) {
        return "text-purple-600";
    }

    if (category.includes("payroll") || category.includes("salary")) {
        return "text-orange-600";
    }

    if (category.includes("comment") || category.includes("message")) {
        return "text-primary-600";
    }

    if (category.includes("meeting") || category.includes("team")) {
        return "text-orange-500";
    }

    return "text-brand-primary";
};

const getNotificationTime = (notification) => {
    if (!notification?.created_at) {
        return "Recently";
    }

    return getTimeAgo(notification.created_at);
};

const openNotification = async (notification) => {
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

const fetchNotifications = async (page = 1) => {
    currentPage.value = page;
    await notificationStore.fetchNotificationsPaginated({ page, perPage });
};

const handleMarkAllRead = async () => {
    await notificationStore.markAllAsRead();
};

const goToPage = (page) => {
    if (page >= 1 && (!meta.value || page <= meta.value.last_page)) {
        fetchNotifications(page);
    }
};

onMounted(() => {
    fetchNotifications(1);
});
</script>

<template>
    <div class="rounded-2xl border border-brand-border bg-white p-4 sm:p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.1em] text-brand-primary">Activity Feed</p>
                <h3 class="text-brand-dark text-lg sm:text-xl font-bold">All Notifications</h3>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border border-primary-100 px-3 py-1.5 text-xs font-semibold text-brand-primary transition-colors hover:bg-primary-50 disabled:opacity-50"
                    :disabled="markingAllRead || notifications.length === 0"
                    @click="handleMarkAllRead"
                >
                    <CheckCheck class="h-3.5 w-3.5" />
                    {{ markingAllRead ? "Marking..." : "Mark all read" }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border border-primary-100 px-3 py-1.5 text-xs font-semibold text-brand-primary transition-colors hover:bg-primary-50"
                    @click="fetchNotifications(currentPage)"
                >
                    <RefreshCw class="h-3.5 w-3.5" />
                    Refresh
                </button>
            </div>
        </div>

        <div v-if="loading" class="space-y-3">
            <div
                v-for="index in 5"
                :key="`notifications-skeleton-${index}`"
                class="flex items-start gap-3 animate-pulse"
            >
                <div class="h-11 w-11 rounded-xl bg-primary-50"></div>
                <div class="flex-1 space-y-2 pt-1">
                    <div class="h-2.5 w-3/4 rounded-full bg-gray-200"></div>
                    <div class="h-2 w-1/2 rounded-full bg-gray-100"></div>
                </div>
            </div>
        </div>

        <div v-else-if="error" class="rounded-xl border border-red-100 bg-red-50 px-4 py-3">
            <p class="text-sm text-red-700">Gagal memuat notifikasi.</p>
            <button
                type="button"
                class="mt-2 text-sm font-semibold text-red-700 hover:underline"
                @click="fetchNotifications(currentPage)"
            >
                Try again
            </button>
        </div>

        <div
            v-else-if="notifications.length === 0"
            class="rounded-xl border border-brand-border bg-gray-50 px-4 py-8 text-center"
        >
            <p class="text-base font-semibold text-brand-dark">No notifications yet.</p>
            <p class="mt-1 text-sm text-gray-500">New updates will appear here.</p>
        </div>

        <div v-else class="space-y-1">
            <button
                v-for="notification in notifications"
                :key="notification.id"
                type="button"
                class="flex w-full items-start gap-3 rounded-xl px-2.5 py-2.5 text-left transition-colors hover:bg-primary-50/40"
                :disabled="!notification.action_url"
                @click="openNotification(notification)"
            >
                <div
                    class="relative h-11 w-11 flex-shrink-0 rounded-xl flex items-center justify-center"
                    :class="getActivityIconBgClass(notification)"
                >
                    <component
                        :is="getActivityIcon(notification)"
                        class="h-5 w-5"
                        :class="getActivityIconClass(notification)"
                    />
                    <span
                        v-if="!notification.is_read"
                        class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-brand-primary ring-2 ring-white"
                    ></span>
                </div>

                <div class="min-w-0 flex-1 border-b border-gray-100 pb-2.5">
                    <p class="text-brand-dark text-sm font-semibold leading-5">
                        {{ notification.title }}
                    </p>
                    <p v-if="notification.body" class="mt-0.5 text-xs leading-5 text-gray-500">
                        {{ notification.body }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ getNotificationTime(notification) }}
                    </p>
                </div>
            </button>

            <!-- Pagination -->
            <div
                v-if="meta && meta.last_page > 1"
                class="flex items-center justify-between border-t border-gray-100 pt-4 mt-3"
            >
                <p class="text-xs text-gray-500">
                    Page {{ meta.current_page }} of {{ meta.last_page }}
                    <span class="text-gray-400">({{ meta.total }} total)</span>
                </p>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-brand-border text-gray-500 hover:bg-primary-50/40 disabled:opacity-40 disabled:cursor-not-allowed"
                        :disabled="currentPage <= 1"
                        @click="goToPage(currentPage - 1)"
                    >
                        <ChevronLeftIcon class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-brand-border text-gray-500 hover:bg-primary-50/40 disabled:opacity-40 disabled:cursor-not-allowed"
                        :disabled="currentPage >= meta.last_page"
                        @click="goToPage(currentPage + 1)"
                    >
                        <ChevronRightIcon class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
