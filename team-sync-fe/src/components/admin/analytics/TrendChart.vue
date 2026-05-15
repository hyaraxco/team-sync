<template>
    <div class="bg-white rounded-2xl border border-brand-border hover:shadow-md transition-shadow duration-300 p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-bold text-brand-dark">{{ title }}</h3>
                <p v-if="subtitle" class="text-sm font-medium text-brand-light mt-1">{{ subtitle }}</p>
            </div>
        </div>

        <div v-if="loading" class="flex items-center justify-center h-[300px] bg-gray-50/50 rounded-xl">
            <div class="relative w-12 h-12">
                <div class="absolute inset-0 rounded-full border-2 border-gray-200"></div>
                <div
                    class="absolute inset-0 rounded-full border-2 border-primary-500 border-t-transparent animate-spin"
                ></div>
            </div>
        </div>

        <div
            v-else-if="!chartData || chartData.length === 0"
            class="flex flex-col items-center justify-center h-[300px] bg-gray-50/50 rounded-xl border border-dashed border-gray-200"
        >
            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"
                    />
                </svg>
            </div>
            <p class="text-sm font-medium text-brand-light">No data available</p>
        </div>

        <VueApexCharts v-else :type="chartType" height="300" :options="apexOptions" :series="apexSeries" />
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
            fontFamily: "inherit",
        },
        colors: ["#0C51D9"],
        stroke: isLine ? { curve: "smooth", width: 3 } : undefined,
        plotOptions: !isLine ? { bar: { borderRadius: 6, columnWidth: "45%" } } : undefined,
        fill: isLine
            ? {
                  type: "gradient",
                  gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [50, 100, 100] },
              }
            : undefined,
        xaxis: {
            categories: (props.chartData || []).map((item) => item[props.xKey]),
        },
        yaxis: {
            labels: {
                formatter: (val) => val,
            },
        },
        dataLabels: { enabled: false },
        tooltip: {
            y: {
                formatter: (val) => `${val}`,
            },
        },
    };
});

const apexSeries = computed(() => [
    {
        name: props.yLabel,
        data: (props.chartData || []).map((item) => item[props.yKey]),
    },
]);
</script>
