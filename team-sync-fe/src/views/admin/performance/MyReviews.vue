<script setup>
import { ref, onMounted, computed } from "vue";
import { storeToRefs } from "pinia";
import { usePerformanceReviewStore } from "@/stores/performanceReview";
import { useRouter } from "vue-router";
import { Calendar, TrendingUp, Clock, CheckCircle2, AlertCircle } from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";

const router = useRouter();
const reviewStore = usePerformanceReviewStore();
const { myReviews, reviewsLoading, pagination } = storeToRefs(reviewStore);

const selectedCycle = ref("all");
const selectedStatus = ref("all");

const filteredReviews = computed(() => {
    let reviews = myReviews.value;
    if (selectedCycle.value !== "all") {
        reviews = reviews.filter((r) => r.cycle_id === parseInt(selectedCycle.value));
    }
    if (selectedStatus.value !== "all") {
        reviews = reviews.filter((r) => r.status === selectedStatus.value);
    }
    return reviews;
});

const cycles = computed(() => {
    const uniqueCycles = [...new Set(myReviews.value.map((r) => r.cycle))];
    return uniqueCycles.filter(Boolean);
});

const statusConfig = {
    pending_self: {
        label: "Pending Self-Assessment",
        color: "warning",
        icon: Clock,
    },
    pending_manager: {
        label: "Pending Manager Review",
        color: "info",
        icon: Clock,
    },
    pending_calibration: {
        label: "Pending Calibration",
        color: "info",
        icon: TrendingUp,
    },
    completed: { label: "Completed", color: "success", icon: CheckCircle2 },
    cancelled: { label: "Cancelled", color: "danger", icon: AlertCircle },
};

const getRatingLabel = (rating) => {
    if (!rating) return "Not Rated";
    if (rating >= 4.5) return "Outstanding";
    if (rating >= 3.5) return "Exceeds Expectations";
    if (rating >= 2.5) return "Meets Expectations";
    if (rating >= 1.5) return "Needs Improvement";
    return "Unsatisfactory";
};

const getRatingColor = (rating) => {
    if (!rating) return "text-gray-400";
    if (rating >= 4.5) return "text-emerald-600";
    if (rating >= 3.5) return "text-blue-600";
    if (rating >= 2.5) return "text-yellow-600";
    if (rating >= 1.5) return "text-orange-600";
    return "text-red-600";
};

const viewReview = (reviewId) => {
    router.push({
        name: "admin.performance.review.detail",
        params: { id: reviewId },
    });
};

onMounted(async () => {
    await reviewStore.fetchMyReviews();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-brand-dark">Review Performa Saya</h1>
                <p class="text-brand-light mt-1">Track your performance evaluations and growth</p>
            </div>
        </div>

        <!-- Filters -->
        <MainCard>
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-brand-dark mb-2">Review Cycle</label>
                    <select
                        v-model="selectedCycle"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                    >
                        <option value="all">All Cycles</option>
                        <option v-for="cycle in cycles" :key="cycle.id" :value="cycle.id">
                            {{ cycle.name }}
                        </option>
                    </select>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-brand-dark mb-2">Status</label>
                    <select
                        v-model="selectedStatus"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                    >
                        <option value="all">All Statuses</option>
                        <option value="pending_self">Pending Self-Assessment</option>
                        <option value="pending_manager">Pending Manager Review</option>
                        <option value="pending_calibration">Pending Calibration</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </div>
        </MainCard>

        <!-- Loading State -->
        <div v-if="reviewsLoading" class="flex justify-center items-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"></div>
        </div>

        <!-- Reviews List -->
        <div v-else-if="filteredReviews.length > 0" class="grid gap-4">
            <MainCard
                v-for="review in filteredReviews"
                :key="review.id"
                class="hover:shadow-lg transition-shadow duration-200 cursor-pointer"
                @click="viewReview(review.id)"
            >
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <Calendar class="w-5 h-5 text-brand-primary" />
                            <h3 class="text-lg font-semibold text-brand-dark">
                                {{ review.cycle?.name || "Review" }}
                            </h3>
                            <span
                                class="px-3 py-1 text-xs font-medium rounded-full border"
                                :class="{
                                    'bg-yellow-100 text-yellow-700 border-yellow-200':
                                        statusConfig[review.status]?.color === 'warning',
                                    'bg-blue-100 text-blue-700 border-blue-200':
                                        statusConfig[review.status]?.color === 'info',
                                    'bg-emerald-100 text-emerald-700 border-emerald-200':
                                        statusConfig[review.status]?.color === 'success',
                                    'bg-red-100 text-red-700 border-red-200':
                                        statusConfig[review.status]?.color === 'danger',
                                }"
                            >
                                {{ statusConfig[review.status]?.label || review.status }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                            <div>
                                <p class="text-xs text-brand-light uppercase tracking-wide">Review Period</p>
                                <p class="text-sm font-medium text-brand-dark mt-1">
                                    {{ new Date(review.cycle?.review_period_start).toLocaleDateString() }}
                                    -
                                    {{ new Date(review.cycle?.review_period_end).toLocaleDateString() }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-brand-light uppercase tracking-wide">Reviewer</p>
                                <p class="text-sm font-medium text-brand-dark mt-1">
                                    {{ review.reviewer?.full_name || "Not Assigned" }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-brand-light uppercase tracking-wide">Overall Score</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-2xl font-bold" :class="getRatingColor(review.final_rating)">
                                        {{ review.final_rating ? Number(review.final_rating).toFixed(2) : "-" }}
                                    </span>
                                    <span class="text-xs text-brand-light">/ 5.00</span>
                                </div>
                                <p class="text-xs mt-1" :class="getRatingColor(review.final_rating)">
                                    {{ getRatingLabel(review.final_rating) }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-brand-light uppercase tracking-wide">Submitted</p>
                                <p class="text-sm font-medium text-brand-dark mt-1">
                                    {{
                                        review.self_assessment_submitted_at
                                            ? new Date(review.self_assessment_submitted_at).toLocaleDateString()
                                            : "Not Submitted"
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Action Required Banner -->
                        <div
                            v-if="review.status === 'pending_self'"
                            class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg flex items-center gap-2"
                        >
                            <AlertCircle class="w-5 h-5 text-yellow-600" />
                            <p class="text-sm font-medium text-yellow-800">
                                Action Required: Complete your self-assessment by
                                {{ new Date(review.cycle?.self_assessment_deadline).toLocaleDateString() }}
                            </p>
                        </div>
                    </div>

                    <div class="ml-4">
                        <button
                            class="px-4 py-2 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors"
                            @click.stop="viewReview(review.id)"
                        >
                            View Details
                        </button>
                    </div>
                </div>
            </MainCard>
        </div>

        <!-- Empty State -->
        <EmptyState
            v-else
            icon="FileText"
            title="No Performance Reviews"
            description="You don't have any performance reviews yet. Reviews will appear here when your manager creates them."
        />
    </div>
</template>
