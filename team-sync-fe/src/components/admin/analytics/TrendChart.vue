<template>
  <div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
      <div v-if="subtitle" class="text-sm text-gray-500">{{ subtitle }}</div>
    </div>

    <div v-if="loading" class="flex items-center justify-center h-64">
      <div
        class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"
      ></div>
    </div>

    <div
      v-else-if="!chartData || chartData.length === 0"
      class="flex items-center justify-center h-64 text-gray-500"
    >
      No data available
    </div>

    <VueApexCharts
      v-else
      :type="chartType"
      height="300"
      :options="apexOptions"
      :series="apexSeries"
    />
  </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: null,
  },
  chartData: {
    type: Array,
    required: true,
  },
  chartType: {
    type: String,
    default: "line", // 'line', 'bar'
  },
  xKey: {
    type: String,
    default: "period",
  },
  yKey: {
    type: String,
    default: "value",
  },
  yLabel: {
    type: String,
    default: "Value",
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const apexOptions = computed(() => {
  const isLine = props.chartType === "line";
  return {
    chart: {
      type: props.chartType,
      height: 300,
      toolbar: { show: false },
      fontFamily: 'inherit'
    },
    colors: ["#3b82f6"],
    stroke: isLine ? { curve: "smooth", width: 2 } : undefined,
    plotOptions: !isLine ? { bar: { borderRadius: 4 } } : undefined,
    fill: isLine
      ? { type: "gradient", gradient: { opacityFrom: 0.4, opacityTo: 0.1 } }
      : undefined,
    xaxis: {
      categories: (props.chartData || []).map((item) => item[props.xKey]),
    },
    yaxis: {
      labels: {
        formatter: (val) => val
      }
    },
    dataLabels: { enabled: false },
    tooltip: {
      y: {
        formatter: (val) => `${val}`
      }
    }
  };
});

const apexSeries = computed(() => [
  {
    name: props.yLabel,
    data: (props.chartData || []).map((item) => item[props.yKey]),
  },
]);
</script>
