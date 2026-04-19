<template>
  <div
    class="bg-white rounded-[16px] border border-[#DCDEDD] hover:border-[#0C51D9] hover:shadow-lg transition-all duration-300 p-5 group"
  >
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-xs font-semibold uppercase tracking-wider text-[#737373] group-hover:text-[#0C51D9] transition-colors">
        {{ title }}
      </h3>
      <div v-if="icon" class="p-2.5 bg-[#F4F7FF] rounded-[10px] group-hover:bg-[#0C51D9] transition-colors">
        <component :is="icon" class="w-5 h-5 text-[#0C51D9] group-hover:text-white transition-colors" />
      </div>
    </div>

    <div class="space-y-2 relative">
      <div class="flex items-baseline justify-between">
        <span class="text-[28px] leading-tight font-extrabold text-[#202020] tracking-tight">
          {{ formattedValue }}
        </span>
        <span
          v-if="trend !== null"
          :class="[trendClass, 'flex items-center text-xs font-bold px-2 py-1 rounded-[6px] bg-opacity-10']"
          :style="trend >= 0 ? 'background-color: rgba(16, 185, 129, 0.1)' : 'background-color: rgba(239, 68, 68, 0.1)'"
        >
          <component :is="trendIcon" class="w-3 h-3 mr-1" />
          {{ Math.abs(trend) }}%
        </span>
      </div>

      <p v-if="subtitle" class="text-sm font-medium text-[#8F8F8F]">{{ subtitle }}</p>

      <div v-if="loading" class="absolute inset-0 bg-white/80 backdrop-blur-sm flex items-center justify-center rounded-lg">
        <div class="w-full max-w-[60%] h-2 bg-gray-200 rounded-full overflow-hidden">
          <div class="h-full bg-[#0C51D9] w-1/2 animate-[progress_1s_ease-in-out_infinite]"></div>
        </div>
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
