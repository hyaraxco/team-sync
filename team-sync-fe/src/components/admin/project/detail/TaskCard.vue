<script setup>
import { Clock, User } from "lucide-vue-next";
import { DEFAULT_AVATAR } from "@/helpers/format";
import StatusBadge from "@/components/common/StatusBadge.vue";
import { formatDate } from "@/utils/dateUtils";

defineProps({
  task: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(["click"]);

const handleClick = () => {
  emit("click");
};

const formatStatusLabel = (status) => {
  return String(status || "-")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (char) => char.toUpperCase());
};
</script>

<template>
  <div
    @click="handleClick"
    class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 p-4 cursor-pointer group border border-gray-100 hover:border-[#0C51D9]"
  >
    <!-- Task Title -->
    <h5
      class="text-sm font-semibold text-gray-900 mb-3 leading-snug group-hover:text-[#0C51D9] transition-colors"
    >
      {{ task.name }}
    </h5>

    <!-- Labels/Tags Row -->
    <div class="flex items-center gap-2 mb-4">
      <StatusBadge
        type="priority"
        :value="task.priority"
      />
      <StatusBadge
        type="task"
        :value="task.status"
      />
      <span
        v-if="task.status === 'review'"
        class="px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-100 text-amber-700"
      >
        Need Review
      </span>
      <span
        v-if="task.needs_revision"
        class="px-2.5 py-1 rounded-md text-xs font-semibold bg-red-100 text-red-700"
      >
        Needs Revision
      </span>
    </div>

    <!-- Footer with Assignee and Due Date -->
    <div
      class="flex items-center justify-between pt-3 border-t border-gray-100"
    >
      <!-- Assignee Avatar -->
      <div class="flex items-center gap-2">
        <img
          :src="task.assignee?.user?.profile_photo || DEFAULT_AVATAR"
          :alt="task.assignee?.user?.name || 'Unassigned'"
          class="w-7 h-7 rounded-full object-cover ring-2 ring-white"
          :title="task.assignee?.user?.name || 'Unassigned'"
        />
      </div>

      <!-- Due Date -->
      <div class="flex items-center gap-1.5 px-2 py-1 bg-gray-50 rounded-md">
        <Clock class="w-3.5 h-3.5 text-gray-500" />
        <span class="text-xs font-medium text-gray-600">{{
          formatDate(task.due_date)
        }}</span>
      </div>
    </div>
  </div>
</template>
