<script setup>
import {
  BellIcon,
  CheckCircle2,
  Clock3,
  MessageSquare,
  Users,
  Wallet,
} from "lucide-vue-next";
import { computed } from "vue";

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  panelId: {
    type: String,
    default: "header-notification-panel",
  },
  notifications: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: [String, Object, Array],
    default: null,
  },
});

const emit = defineEmits(["retry", "select"]);

const visibleNotifications = computed(() => props.notifications.slice(0, 5));

const unreadCount = computed(() =>
  visibleNotifications.value.filter((notification) => !notification?.is_read).length
);

const errorMessage = computed(() => {
  if (!props.error) {
    return "";
  }

  if (typeof props.error === "string") {
    return props.error;
  }

  if (Array.isArray(props.error)) {
    return props.error.join(", ");
  }

  if (typeof props.error === "object") {
    const values = Object.values(props.error).flat();
    return values.join(", ");
  }

  return "Failed to load notifications.";
});

const relativeTimeFormatter = new Intl.RelativeTimeFormat("en", {
  numeric: "auto",
});

const formatCreatedAt = (value) => {
  if (!value) {
    return "";
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return String(value);
  }

  return new Intl.DateTimeFormat("en-US", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(parsed);
};

const formatRelativeTime = (value) => {
  if (!value) {
    return "";
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return "";
  }

  const diffMs = parsed.getTime() - Date.now();
  const absDiffMs = Math.abs(diffMs);
  const minute = 60 * 1000;
  const hour = 60 * minute;
  const day = 24 * hour;
  const week = 7 * day;

  if (absDiffMs < hour) {
    return relativeTimeFormatter.format(Math.round(diffMs / minute), "minute");
  }

  if (absDiffMs < day) {
    return relativeTimeFormatter.format(Math.round(diffMs / hour), "hour");
  }

  if (absDiffMs < week) {
    return relativeTimeFormatter.format(Math.round(diffMs / day), "day");
  }

  return relativeTimeFormatter.format(Math.round(diffMs / week), "week");
};

const resolveCategory = (notification) =>
  String(
    notification?.category ?? notification?.data?.category ?? notification?.type ?? ""
  )
    .trim()
    .toLowerCase();

const getActivityIcon = (notification) => {
  const category = resolveCategory(notification);

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

const getIconContainerClass = (notification) => {
  const category = resolveCategory(notification);

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

const getIconTextClass = (notification) => {
  const category = resolveCategory(notification);

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
</script>

<template>
  <div
    :id="panelId"
    data-testid="header-notification-panel"
    role="dialog"
    aria-label="Latest notifications"
    class="notification-panel absolute right-0 top-full mt-3 z-[9999] overflow-hidden rounded-2xl"
    :class="{ hidden: !open, 'notification-panel--open': open }"
  >
    <div class="notification-panel__header border-b border-[#E4EBF9] px-4 py-3">
      <div class="relative z-10 flex items-start justify-between gap-3">
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#0C51D9]">
            Realtime Feed
          </p>
          <p class="text-sm font-extrabold text-[#0C1C3C]">Notifications</p>
          <p class="text-xs text-[#5D6882]">Latest 5 updates</p>
        </div>
        <span
          v-if="unreadCount > 0"
          class="rounded-full border border-[#C8DAFF] bg-white/85 px-2.5 py-1 text-[11px] font-semibold text-[#0C51D9]"
        >
          {{ unreadCount }} new
        </span>
      </div>
    </div>

    <div
      v-if="loading"
      data-testid="notification-loading"
      class="px-4 py-4"
    >
      <p class="text-sm font-medium text-[#334155]">Loading notifications...</p>
      <div class="mt-3 space-y-2.5">
        <div class="h-2 w-20 animate-pulse rounded-full bg-[#D6E3FD]"></div>
        <div class="h-2 w-full animate-pulse rounded-full bg-[#E3ECFF]"></div>
        <div class="h-2 w-5/6 animate-pulse rounded-full bg-[#E3ECFF]"></div>
      </div>
    </div>

    <div v-else-if="errorMessage" class="space-y-3 px-4 py-4">
      <p data-testid="notification-error" class="text-sm font-medium text-red-600">
        {{ errorMessage }}
      </p>
      <button
        type="button"
        data-testid="notification-retry"
        class="rounded-full border border-[#C9DAFF] bg-[#EEF4FF] px-3 py-1.5 text-xs font-semibold text-[#0C51D9] transition-colors hover:bg-[#E1ECFF]"
        @click="emit('retry')"
      >
        Retry
      </button>
    </div>

    <div
      v-else-if="visibleNotifications.length === 0"
      data-testid="notification-empty"
      class="px-4 py-8 text-center"
    >
      <div
        class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-[#EEF4FF] text-lg"
      >
        <BellIcon class="h-5 w-5 text-[#0C51D9]" />
      </div>
      <p class="text-sm font-semibold text-[#0C1C3C]">You are all caught up</p>
      <p class="mt-1 text-xs text-[#64748B]">No notifications yet.</p>
    </div>

    <ul v-else class="notification-panel__list max-h-96 overflow-auto px-2 py-2">
      <li
        v-for="notification in visibleNotifications"
        :key="notification.id"
        :data-testid="`notification-item-${notification.id}`"
        class="border-b border-[#EEF2F8] py-1.5 last:border-b-0"
      >
        <button
          type="button"
          class="notification-item group flex w-full items-start gap-3 rounded-xl px-2.5 py-2 text-left transition-colors duration-200 hover:bg-[#F7FAFF]"
          :data-testid="`notification-select-${notification.id}`"
          @click="emit('select', notification)"
        >
          <div
            class="relative flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-[12px]"
            :class="getIconContainerClass(notification)"
          >
            <component
              :is="getActivityIcon(notification)"
              class="h-5 w-5"
              :class="getIconTextClass(notification)"
            />
            <span
              v-if="!notification.is_read"
              :data-testid="`notification-unread-${notification.id}`"
              class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-[#0C51D9] ring-2 ring-white"
            ></span>
          </div>

          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold leading-5 text-[#0C1C3C]">
              {{ notification.title }}
            </p>
            <p v-if="notification.body" class="mt-0.5 text-xs leading-5 text-[#4B5563]">
              {{ notification.body }}
            </p>
            <p
              class="mt-1 text-xs text-[#667085]"
              :title="formatCreatedAt(notification.created_at)"
            >
              {{ formatRelativeTime(notification.created_at) }}
            </p>
          </div>
        </button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.notification-panel {
  width: 20rem;
  max-width: calc(100vw - 2rem);
  border: 1px solid #c9dbff;
  background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
  box-shadow: 0 24px 60px -30px rgba(12, 81, 217, 0.85);
}

.notification-panel::after {
  content: "";
  position: absolute;
  inset: 0;
  pointer-events: none;
  background:
    radial-gradient(circle at 92% -8%, rgba(12, 81, 217, 0.28), transparent 46%),
    radial-gradient(circle at 8% -14%, rgba(111, 150, 227, 0.2), transparent 40%);
}

.notification-panel__header {
  position: relative;
  background: linear-gradient(132deg, #f7faff 0%, #edf4ff 100%);
}

.notification-panel__list {
  position: relative;
  z-index: 1;
}

.notification-panel__list::-webkit-scrollbar {
  width: 7px;
}

.notification-panel__list::-webkit-scrollbar-thumb {
  border-radius: 999px;
  background: #d5e3ff;
}

.notification-panel__list::-webkit-scrollbar-track {
  background: transparent;
}

.notification-panel--open {
  animation: panel-enter 220ms ease-out;
}

.notification-item {
  position: relative;
  z-index: 1;
}

@keyframes panel-enter {
  from {
    opacity: 0;
    transform: translateY(-8px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@media (max-width: 640px) {
  .notification-panel {
    right: -0.5rem;
    width: min(20rem, calc(100vw - 1rem));
  }
}
</style>
