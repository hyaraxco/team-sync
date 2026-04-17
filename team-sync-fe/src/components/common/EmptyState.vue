<script setup>
import {
  SearchX,
  Users,
  Briefcase,
  CalendarClock,
  FileText,
  InboxIcon,
} from "lucide-vue-next";

const props = defineProps({
  /**
   * Icon name to display: 'SearchX', 'Users', 'Briefcase', 'CalendarClock', 'FileText', 'Inbox'
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
      :class="['text-gray-400 mx-auto', sizes.icon]"
    />
    <p :class="['text-gray-500 font-medium', sizes.title]">{{ title }}</p>
    <p v-if="subtitle" :class="['text-gray-400 mt-1', sizes.subtitle]">
      {{ subtitle }}
    </p>
    <slot />
  </div>
</template>
