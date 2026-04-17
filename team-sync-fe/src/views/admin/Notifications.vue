<script setup lang="ts">
import { computed, onMounted } from "vue";
import {
  BellIcon,
  CheckCircle2,
  Clock3,
  MessageSquare,
  Users,
  Wallet,
  RefreshCw,
} from "lucide-vue-next";
import { useRouter } from "vue-router";
import { useNotificationStore } from "@/stores/notifications";
import { getTimeAgo } from "@/utils/dateUtils";

const notificationStore = useNotificationStore();
const router = useRouter();

const notifications = computed(() =>
  Array.isArray(notificationStore.notifications) ? notificationStore.notifications : []
);
const loading = computed(() => notificationStore.loading);
const error = computed(() => notificationStore.error);

const resolveNotificationCategory = (notification: any) =>
  String(
    notification?.category ?? notification?.data?.category ?? notification?.type ?? ""
  )
    .trim()
    .toLowerCase();

const getActivityIcon = (notification: any) => {
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

const getActivityIconBgClass = (notification: any) => {
  const category = resolveNotificationCategory(notification);

  if (category.includes("task")) {
    return "bg-[#EAF8EE]";
  }

  if (category.includes("attendance") || category.includes("check")) {
    return "bg-[#F0ECFF]";
  }

  if (category.includes("payroll") || category.includes("salary")) {
    return "bg-[#FFF4E8]";
  }

  if (category.includes("comment") || category.includes("message")) {
    return "bg-[#EAF0FF]";
  }

  if (category.includes("meeting") || category.includes("team")) {
    return "bg-[#FFF1E8]";
  }

  return "bg-[#EEF4FF]";
};

const getActivityIconClass = (notification: any) => {
  const category = resolveNotificationCategory(notification);

  if (category.includes("task")) {
    return "text-[#16A34A]";
  }

  if (category.includes("attendance") || category.includes("check")) {
    return "text-[#7C3AED]";
  }

  if (category.includes("payroll") || category.includes("salary")) {
    return "text-[#EA580C]";
  }

  if (category.includes("comment") || category.includes("message")) {
    return "text-[#2563EB]";
  }

  if (category.includes("meeting") || category.includes("team")) {
    return "text-[#F97316]";
  }

  return "text-[#0C51D9]";
};

const getNotificationTime = (notification: any) => {
  if (!notification?.created_at) {
    return "Recently";
  }

  return getTimeAgo(notification.created_at);
};

const openNotification = async (notification: any) => {
  const actionUrl =
    typeof notification?.action_url === "string"
      ? notification.action_url.trim()
      : "";

  if (!actionUrl) {
    return;
  }

  if (/^https?:\/\//i.test(actionUrl)) {
    window.location.assign(actionUrl);
    return;
  }

  await router.push(actionUrl);
};

const fetchAllNotifications = async () => {
  await notificationStore.fetchLatestNotifications(100);
};

onMounted(() => {
  fetchAllNotifications();
});
</script>

<template>
  <div class="rounded-[20px] border border-[#DCDEDD] bg-white p-4 sm:p-6">
    <div class="mb-4 flex items-center justify-between gap-3">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.1em] text-[#0C51D9]">
          Activity Feed
        </p>
        <h3 class="text-brand-dark text-lg sm:text-xl font-bold">
          All Notifications
        </h3>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-full border border-[#D5E2FB] px-3 py-1.5 text-xs font-semibold text-[#0C51D9] transition-colors hover:bg-[#EFF5FF]"
        @click="fetchAllNotifications"
      >
        <RefreshCw class="h-3.5 w-3.5" />
        Refresh
      </button>
    </div>

    <div v-if="loading" class="space-y-3">
      <div
        v-for="index in 5"
        :key="`notifications-skeleton-${index}`"
        class="flex items-start gap-3 animate-pulse"
      >
        <div class="h-11 w-11 rounded-[12px] bg-[#EEF4FF]"></div>
        <div class="flex-1 space-y-2 pt-1">
          <div class="h-2.5 w-3/4 rounded-full bg-[#E8EEF8]"></div>
          <div class="h-2 w-1/2 rounded-full bg-[#EFF3FA]"></div>
        </div>
      </div>
    </div>

    <div
      v-else-if="error"
      class="rounded-[12px] border border-red-100 bg-red-50 px-4 py-3"
    >
      <p class="text-sm text-red-700">Unable to load notifications.</p>
      <button
        type="button"
        class="mt-2 text-sm font-semibold text-red-700 hover:underline"
        @click="fetchAllNotifications"
      >
        Try again
      </button>
    </div>

    <div
      v-else-if="notifications.length === 0"
      class="rounded-[12px] border border-[#E7ECF4] bg-[#F8FAFC] px-4 py-8 text-center"
    >
      <p class="text-base font-semibold text-[#334155]">No notifications yet.</p>
      <p class="mt-1 text-sm text-[#64748B]">New updates will appear here.</p>
    </div>

    <div v-else class="space-y-1">
      <button
        v-for="notification in notifications"
        :key="notification.id"
        type="button"
        class="flex w-full items-start gap-3 rounded-[12px] px-2.5 py-2.5 text-left transition-colors hover:bg-[#F7FAFF]"
        :disabled="!notification.action_url"
        @click="openNotification(notification)"
      >
        <div
          class="relative h-11 w-11 flex-shrink-0 rounded-[12px] flex items-center justify-center"
          :class="getActivityIconBgClass(notification)"
        >
          <component
            :is="getActivityIcon(notification)"
            class="h-5 w-5"
            :class="getActivityIconClass(notification)"
          />
          <span
            v-if="!notification.is_read"
            class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-[#0C51D9] ring-2 ring-white"
          ></span>
        </div>

        <div class="min-w-0 flex-1 border-b border-[#EEF2F8] pb-2.5">
          <p class="text-brand-dark text-sm font-semibold leading-5">
            {{ notification.title }}
          </p>
          <p
            v-if="notification.body"
            class="mt-0.5 text-xs leading-5 text-gray-500"
          >
            {{ notification.body }}
          </p>
          <p class="mt-1 text-xs text-gray-500">
            {{ getNotificationTime(notification) }}
          </p>
        </div>
      </button>
    </div>
  </div>
</template>
