<template>
  <div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-sm font-medium text-gray-500">{{ title }}</h3>
      <div v-if="icon" class="p-2 bg-blue-50 rounded-lg">
        <component :is="icon" class="w-5 h-5 text-blue-600" />
      </div>
    </div>

    <div class="space-y-2">
      <div class="flex items-baseline justify-between">
        <span class="text-3xl font-bold text-gray-900">{{
          formattedValue
        }}</span>
        <span
          v-if="trend !== null"
          :class="trendClass"
          class="flex items-center text-sm font-medium"
        >
          <component :is="trendIcon" class="w-4 h-4 mr-1" />
          {{ Math.abs(trend) }}%
        </span>
      </div>

      <p v-if="subtitle" class="text-sm text-gray-600">{{ subtitle }}</p>

      <div v-if="loading" class="mt-4">
        <div class="animate-pulse h-2 bg-gray-200 rounded"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { ArrowUpIcon, ArrowDownIcon } from "lucide-vue-next";

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  value: {
    type: [Number, String],
    required: true,
  },
  format: {
    type: String,
    default: "number", // 'number', 'currency', 'percentage', 'days'
  },
  trend: {
    type: Number,
    default: null,
  },
  subtitle: {
    type: String,
    default: null,
  },
  icon: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const formattedValue = computed(() => {
  if (props.loading) return "...";

  const val = parseFloat(props.value);

  switch (props.format) {
    case "currency":
      return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(val);
    case "percentage":
      return `${val.toFixed(1)}%`;
    case "days":
      return `${val.toFixed(0)} days`;
    default:
      return new Intl.NumberFormat("id-ID").format(val);
  }
});

const trendClass = computed(() => {
  if (props.trend === null) return "";
  return props.trend >= 0 ? "text-green-600" : "text-red-600";
});

const trendIcon = computed(() => {
  if (props.trend === null) return null;
  return props.trend >= 0 ? ArrowUpIcon : ArrowDownIcon;
});
</script>
