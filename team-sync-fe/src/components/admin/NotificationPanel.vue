<script setup>
import { BellIcon, CheckCircle2, Clock3, MessageSquare, Users, Wallet } from "lucide-vue-next";
import { computed } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();

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
    markingAllRead: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["retry", "select", "mark-all-read", "close"]);

const visibleNotifications = computed(() => props.notifications.slice(0, 5));

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
    String(notification?.category ?? notification?.data?.category ?? notification?.type ?? "")
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
        return "bg-success-50";
    }

    if (category.includes("attendance") || category.includes("check")) {
        return "bg-purple-50";
    }

    if (category.includes("payroll") || category.includes("salary")) {
        return "bg-warning-50";
    }

    if (category.includes("comment") || category.includes("message")) {
        return "bg-primary-50";
    }

    if (category.includes("meeting") || category.includes("team")) {
        return "bg-warning-50";
    }

    return "bg-primary-50";
};

const getIconTextClass = (notification) => {
    const category = resolveCategory(notification);

    if (category.includes("task")) {
        return "text-success-600";
    }

    if (category.includes("attendance") || category.includes("check")) {
        return "text-purple-600";
    }

    if (category.includes("payroll") || category.includes("salary")) {
        return "text-warning-700";
    }

    if (category.includes("comment") || category.includes("message")) {
        return "text-primary-600";
    }

    if (category.includes("meeting") || category.includes("team")) {
        return "text-orange-500";
    }

    return "text-brand-primary";
};

const handleSeeAll = () => {
    emit("close");
    router.push({ name: 'admin.notifications' });
};
</script>

<template>
    <div
        :id="panelId"
        data-testid="header-notification-panel"
        role="dialog"
        aria-label="Latest notifications"
        class="notification-panel absolute right-0 top-full mt-3 z-[9999] overflow-hidden rounded-2xl border shadow-lg"
        :class="{ hidden: !open, 'notification-panel--open': open }"
        :style="{
            background: 'var(--color-surface)',
            borderColor: 'var(--color-border-default)',
            width: '20rem',
            maxWidth: 'calc(100vw - 2rem)'
        }"
    >
        <div 
            class="notification-panel__header border-b px-4 py-3"
            :style="{ borderColor: 'var(--color-border-default)' }"
        >
            <div class="relative z-10 flex items-center justify-between">
                <div>
                    <p 
                        class="text-[11px] font-semibold uppercase tracking-[0.16em] text-brand-primary"
                    >
                        Notifications
                    </p>
                    <p 
                        class="text-xs"
                        :style="{ color: 'var(--color-text-secondary)' }"
                    >
                        Latest updates
                    </p>
                </div>
                <button
                    v-if="visibleNotifications.length > 0"
                    type="button"
                    data-testid="mark-all-read-btn"
                    class="rounded-full border border-primary-200 bg-primary-50 px-2.5 py-1 text-[10px] font-semibold text-brand-primary transition-colors hover:bg-primary-100 disabled:opacity-50"
                    :disabled="markingAllRead"
                    @click="emit('mark-all-read')"
                >
                    {{ markingAllRead ? "Marking..." : "Mark all read" }}
                </button>
            </div>
        </div>

        <div v-if="loading" data-testid="notification-loading" class="px-4 py-4">
            <p 
                class="text-sm font-medium"
                :style="{ color: 'var(--color-text-primary)' }"
            >
                Loading notifications...
            </p>
            <div class="mt-3 space-y-2.5">
                <div class="h-2 w-20 animate-pulse rounded-full bg-primary-100"></div>
                <div class="h-2 w-full animate-pulse rounded-full bg-primary-100"></div>
                <div class="h-2 w-5/6 animate-pulse rounded-full bg-primary-100"></div>
            </div>
        </div>

        <div v-else-if="errorMessage" class="space-y-3 px-4 py-4">
            <p data-testid="notification-error" class="text-sm font-medium text-red-600">
                {{ errorMessage }}
            </p>
            <button
                type="button"
                data-testid="notification-retry"
                class="rounded-full border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-semibold text-brand-primary transition-colors hover:bg-primary-100"
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
            <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-primary-50 text-lg">
                <BellIcon class="h-5 w-5 text-brand-primary" />
            </div>
            <p 
                class="text-sm font-semibold"
                :style="{ color: 'var(--color-text-primary)' }"
            >
                No notifications
            </p>
            <p 
                class="mt-1 text-xs"
                :style="{ color: 'var(--color-text-secondary)' }"
            >
                You are all caught up
            </p>
        </div>

        <ul v-else class="notification-panel__list max-h-96 overflow-auto px-2 py-2">
            <li
                v-for="notification in visibleNotifications"
                :key="notification.id"
                :data-testid="`notification-item-${notification.id}`"
                class="notification-item border-b py-1.5 last:border-b-0"
                :style="{ borderColor: 'var(--color-border-muted)' }"
            >
                <button
                    type="button"
                    class="group flex w-full items-start gap-3 rounded-xl px-2.5 py-2 text-left transition-colors duration-200 hover:bg-primary-50/40"
                    :data-testid="`notification-select-${notification.id}`"
                    @click="emit('select', notification)"
                >
                    <div
                        class="relative flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl"
                        :class="getIconContainerClass(notification)"
                    >
                        <component
                            :is="getActivityIcon(notification)"
                            class="h-5 w-5"
                            :class="getIconTextClass(notification)"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p 
                            class="text-sm font-semibold leading-5"
                            :style="{ color: 'var(--color-text-primary)' }"
                        >
                            {{ notification.title }}
                        </p>
                        <p 
                            v-if="notification.body" 
                            class="mt-0.5 text-xs leading-5"
                            :style="{ color: 'var(--color-text-secondary)' }"
                        >
                            {{ notification.body }}
                        </p>
                        <p 
                            class="mt-1 text-xs"
                            :style="{ color: 'var(--color-text-muted)' }"
                            :title="formatCreatedAt(notification.created_at)"
                        >
                            {{ formatRelativeTime(notification.created_at) }}
                        </p>
                    </div>
                </button>
            </li>
        </ul>

        <!-- See all notifications link -->
        <div 
            class="border-t px-4 py-3 text-center"
            :style="{ borderColor: 'var(--color-border-default)' }"
        >
            <button
                data-testid="see-all-link"
                type="button"
                class="text-sm font-medium text-brand-primary hover:text-brand-primary-dark transition-colors"
                @click="handleSeeAll"
            >
                See all notifications →
            </button>
        </div>
    </div>
</template>

<style scoped>
.notification-panel--open {
    animation: panel-enter 220ms ease-out;
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
        width: min(20rem, calc(100vw - 1rem)) !important;
    }
}
</style>
