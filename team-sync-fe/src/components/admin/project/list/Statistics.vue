<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import VueApexCharts from "vue3-apexcharts";
import { TrendingUp, Briefcase, PlayCircle } from "lucide-vue-next";
import { useProjectStore } from "@/stores/project";
import { storeToRefs } from "pinia";
import AnimatedValue from "@/components/common/AnimatedValue.vue";

const projectStore = useProjectStore();
const { loadingStatistics } = storeToRefs(projectStore);

const total = computed(() => projectStore.statistics.total);
const active = computed(() => projectStore.statistics.active);

onMounted(() => {
    projectStore.fetchStatistics();
});

// Chart configuration
const chartOptions = ref({
    chart: {
        type: "area",
        height: 250,
        toolbar: {
            show: false,
        },
        sparkline: {
            enabled: false,
        },
        animations: {
            enabled: true,
            easing: "easeinout",
            speed: 800,
        },
    },
    stroke: {
        curve: "smooth",
        width: 3,
        colors: ["#8A63F9"],
    },
    fill: {
        type: "gradient",
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.4,
            opacityTo: 0.05,
            stops: [0, 100],
            colorStops: [
                {
                    offset: 0,
                    color: "#8A63F9",
                    opacity: 0.4,
                },
                {
                    offset: 100,
                    color: "#8A63F9",
                    opacity: 0.05,
                },
            ],
        },
    },
    colors: ["#8A63F9"],
    grid: {
        borderColor: "#f1f1f1",
        strokeDashArray: 0,
        padding: {
            top: 20,
            right: 20,
            bottom: 20,
            left: 20,
        },
    },
    xaxis: {
        categories: ["July", "August", "September", "October", "November", "December"],
        axisBorder: {
            show: false,
        },
        axisTicks: {
            show: false,
        },
        labels: {
            style: {
                colors: "#6B7280",
                fontSize: "12px",
            },
        },
    },
    yaxis: {
        min: 0,
        max: 8,
        tickAmount: 8,
        labels: {
            style: {
                colors: "#6B7280",
                fontSize: "12px",
            },
            formatter: (value: number) => `${value}`,
        },
    },
    tooltip: {
        enabled: true,
        style: {
            fontSize: "12px",
        },
        y: {
            formatter: (value: number) => `${value}`,
        },
        theme: "light",
        fillSeriesColor: false,
    },
    dataLabels: {
        enabled: false,
    },
    legend: {
        show: false,
    },
    markers: {
        size: 5,
        colors: ["#8A63F9"],
        strokeColors: "#fff",
        strokeWidth: 2,
        hover: {
            size: 7,
        },
    },
    responsive: [
        {
            breakpoint: 768,
            options: {
                chart: {
                    height: 200,
                },
                yaxis: {
                    tickAmount: 6,
                },
            },
        },
    ],
});

const chartSeries = ref([
    {
        name: "Project Progress",
        data: [4, 3, 6, 2, 5, 3],
    },
]);
</script>

<template>
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 lg:gap-6 mb-6">
        <!-- Projects Stats Column -->
        <div class="xl:col-span-1 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
            <!-- Total Projects Card -->
            <div
                class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 hover:shadow-md transition-all duration-300 p-5 flex flex-col min-h-[186px] group"
            >
                <!-- Header with title and icon -->
                <div class="flex items-center justify-between mb-4">
                    <p class="text-brand-dark text-base font-medium">Total Projects</p>
                    <div
                        class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center group-hover:bg-blue-100 transition-colors duration-300"
                    >
                        <Briefcase class="w-6 h-6 text-blue-600" />
                    </div>
                </div>
                <!-- Value and status at bottom -->
                <div class="mt-auto">
                    <p id="totalProjects" class="text-brand-dark text-3xl font-extrabold leading-tight mb-1">
                        <template v-if="loadingStatistics">-</template>
                        <AnimatedValue v-else :value="total" />
                    </p>
                    <p class="text-success text-base font-medium">All projects</p>
                </div>
            </div>

            <!-- Active Projects Card -->
            <div
                class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 hover:shadow-md transition-all duration-300 p-5 flex flex-col min-h-[186px] group"
            >
                <!-- Header with title and icon -->
                <div class="flex items-center justify-between mb-4">
                    <p class="text-brand-dark text-base font-medium">Active Projects</p>
                    <div
                        class="w-12 h-12 bg-green-50 rounded-2xl flex items-center justify-center group-hover:bg-green-100 transition-colors duration-300"
                    >
                        <PlayCircle class="w-6 h-6 text-green-600" />
                    </div>
                </div>
                <!-- Value and status at bottom -->
                <div class="mt-auto">
                    <p id="activeProjects" class="text-brand-dark text-3xl font-extrabold leading-tight mb-1">
                        <template v-if="loadingStatistics">-</template>
                        <AnimatedValue v-else :value="active" />
                    </p>
                    <p class="text-success text-base font-medium">In progress</p>
                </div>
            </div>
        </div>

        <!-- Charts Column -->
        <div
            class="xl:col-span-2 bg-white border border-brand-border rounded-2xl p-4 sm:p-6 hover:shadow-md transition-shadow duration-300"
        >
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <TrendingUp class="w-6 h-6 text-purple-600" />
                </div>
                <div class="flex-1">
                    <h3 class="text-brand-dark text-lg font-bold">Project Progress</h3>
                    <p class="text-brand-light text-sm">Monthly project completion trends</p>
                </div>
            </div>

            <!-- Chart Container -->
            <div class="relative w-full" style="height: 250px">
                <VueApexCharts type="area" height="250" :options="chartOptions" :series="chartSeries" class="w-full" />
            </div>
        </div>
    </div>
</template>
