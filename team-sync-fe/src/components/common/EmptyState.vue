<script setup>
import { 
    SearchX, 
    Users, 
    Briefcase, 
    CalendarClock, 
    FileText, 
    InboxIcon,
    Video,
    Bell,
    Layout,
    Target,
    BarChart3,
    Calendar
} from "lucide-vue-next";

const props = defineProps({
    /**
     * Icon name to display: 'SearchX', 'Users', 'Briefcase', 'CalendarClock', 'FileText', 'Inbox', 'Video', 'Bell', 'Layout', 'Target', 'BarChart3', 'Calendar'
     */
    icon: {
        type: String,
        default: "Inbox",
    },
    /**
     * Main text
     */
    title: {
        type: String,
        default: "No data found",
    },
    /**
     * Subtitle / hint text
     */
    subtitle: {
        type: String,
        default: "",
    },
    /**
     * Size variant: 'sm' | 'md' | 'lg'
     */
    size: {
        type: String,
        default: "md",
    },
});

const iconMap = {
    SearchX,
    Users,
    Briefcase,
    CalendarClock,
    FileText,
    Inbox: InboxIcon,
    Video,
    Bell,
    Layout,
    Target,
    BarChart3,
    Calendar
};

const iconComponent = iconMap[props.icon] || InboxIcon;

const sizeClasses = {
    sm: { wrapper: "py-6", icon: "w-10 h-10 mb-2", title: "text-sm", subtitle: "text-xs" },
    md: { wrapper: "py-8", icon: "w-12 h-12 mb-3", title: "text-base", subtitle: "text-sm" },
    lg: { wrapper: "py-12", icon: "w-16 h-16 mb-4", title: "text-lg font-semibold", subtitle: "text-sm" },
};

const sizes = sizeClasses[props.size] || sizeClasses.md;
</script>

<template>
    <div :class="['text-center', sizes.wrapper]">
        <component 
            :is="iconComponent" 
            :class="['mx-auto', sizes.icon]"
            :style="{ color: 'var(--color-text-muted)' }"
        />
        <p 
            :class="['font-medium', sizes.title]"
            :style="{ color: 'var(--color-text-primary)' }"
        >
            {{ title }}
        </p>
        <p 
            v-if="subtitle" 
            :class="['mt-1', sizes.subtitle]"
            :style="{ color: 'var(--color-text-secondary)' }"
        >
            {{ subtitle }}
        </p>
        <slot />
    </div>
</template>
