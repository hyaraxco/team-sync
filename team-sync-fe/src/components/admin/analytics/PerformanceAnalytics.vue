<template>
    <div class="space-y-6">
        <!-- Performance Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <MetricCard
                title="Goal Completion"
                :value="goalCompletionRate?.completion_rate ?? 0"
                format="percentage"
                :trend="goalCompletionRate?.trend ?? null"
                subtitle="Active goals completed"
                :icon="TargetIcon"
                :loading="enhancedMetricsLoading"
            />
            <MetricCard
                title="Avg Rating"
                :value="ratingDistribution?.average ?? companyPerformanceSummary?.average_rating ?? 0"
                format="number"
                subtitle="Company-wide average"
                :icon="StarIcon"
                :loading="enhancedMetricsLoading"
            />
            <MetricCard
                title="Feedback Given"
                :value="feedbackMetrics?.total_feedback ?? 0"
                format="number"
                :trend="feedbackMetrics?.trend ?? null"
                subtitle="This period"
                :icon="MessageSquareIcon"
                :loading="enhancedMetricsLoading"
            />
            <MetricCard
                title="Reviews Completed"
                :value="companyPerformanceSummary?.completed_reviews ?? teamPerformanceSummary?.completed_reviews ?? 0"
                format="number"
                subtitle="Completed reviews"
                :icon="UsersIcon"
                :loading="enhancedMetricsLoading"
            />
        </div>

        <!-- Rating Distribution Chart -->
        <MainCard title="Rating Distribution" class="p-6">
            <div v-if="enhancedMetricsLoading" class="h-64 flex items-center justify-center">
                <div class="animate-pulse text-gray-400">Loading...</div>
            </div>
            <div v-else-if="normalizedRatingDistribution.length" class="h-64">
                <VueApexCharts type="bar" height="100%" :options="ratingChartOptions" :series="ratingChartSeries" />
            </div>
            <div v-else class="h-64 flex items-center justify-center text-gray-400">No rating data available</div>
        </MainCard>

        <!-- Team Performance Table -->
        <MainCard title="Team Performance Summary" class="p-6">
            <div v-if="enhancedMetricsLoading" class="h-48 flex items-center justify-center">
                <div class="animate-pulse text-gray-400">Loading...</div>
            </div>
            <div v-else-if="teamReviewRows.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 font-semibold text-gray-600">Employee</th>
                            <th class="text-center py-3 px-4 font-semibold text-gray-600">Avg Rating</th>
                            <th class="text-center py-3 px-4 font-semibold text-gray-600">Status</th>
                            <th class="text-center py-3 px-4 font-semibold text-gray-600">Review ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="review in teamReviewRows"
                            :key="review.id"
                            class="border-b border-gray-100 hover:bg-gray-50"
                        >
                            <td class="py-3 px-4 font-medium text-gray-800">{{ review.employee_name }}</td>
                            <td class="py-3 px-4 text-center">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="ratingBadgeClass(review.overall_rating)"
                                >
                                    {{ formatRating(review.overall_rating) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center text-gray-600">{{ review.status ?? "-" }}</td>
                            <td class="py-3 px-4 text-center text-gray-600">#{{ review.id }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="h-48 flex items-center justify-center text-gray-400">
                No team performance data available
            </div>
        </MainCard>
    </div>
</template>

<script setup>
import { computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useAnalyticsStore } from "@/stores/analytics";
import MetricCard from "@/components/admin/analytics/MetricCard.vue";
import MainCard from "@/components/common/MainCard.vue";
import { TargetIcon, StarIcon, MessageSquareIcon, UsersIcon } from "lucide-vue-next";

const analyticsStore = useAnalyticsStore();
const {
    teamPerformanceSummary,
    companyPerformanceSummary,
    ratingDistribution,
    goalCompletionRate,
    feedbackMetrics,
    enhancedMetricsLoading,
} = storeToRefs(analyticsStore);

const normalizedRatingDistribution = computed(() => {
    const distribution = ratingDistribution.value?.distribution ?? {};

    if (Array.isArray(distribution)) {
        return distribution.map((item, index) => ({
            label: item.label ?? `${index + 1} star`,
            count: Number(item.count ?? item.value ?? 0),
        }));
    }

    return Object.entries(distribution).map(([rating, count]) => ({
        label: `${rating} star`,
        count: Number(count ?? 0),
    }));
});

const teamReviewRows = computed(() => teamPerformanceSummary.value?.reviews ?? []);

const ratingChartOptions = computed(() => ({
    chart: { type: "bar", toolbar: { show: false } },
    plotOptions: { bar: { borderRadius: 6, columnWidth: "50%" } },
    colors: ["#0C51D9"],
    xaxis: {
        categories: normalizedRatingDistribution.value.map((item) => item.label),
        labels: { style: { colors: "#94a3b8", fontSize: "12px" } },
    },
    yaxis: {
        labels: { style: { colors: "#94a3b8" } },
    },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (v) => `${v} reviews` } },
}));

const ratingChartSeries = computed(() => [
    {
        name: "Reviews",
        data: normalizedRatingDistribution.value.map((item) => item.count),
    },
]);

const formatRating = (value) => {
    const rating = Number(value ?? 0);

    if (!Number.isFinite(rating) || rating <= 0) {
        return "-";
    }

    return rating.toFixed(1);
};

const ratingBadgeClass = (value) => {
    const rating = Number(value ?? 0);

    if (rating >= 4) {
        return "bg-green-100 text-green-800";
    }

    if (rating >= 3) {
        return "bg-yellow-100 text-yellow-800";
    }

    return "bg-red-100 text-red-800";
};

onMounted(() => {
    analyticsStore.fetchPerformanceAnalytics();
});
</script>
