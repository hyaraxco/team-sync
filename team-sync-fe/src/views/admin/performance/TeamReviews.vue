<script setup>
import { ref, onMounted, computed } from "vue";
import { storeToRefs } from "pinia";
import { usePerformanceReviewStore } from "@/stores/performanceReview";
import { useRouter } from "vue-router";
import {
  Users,
  Calendar,
  TrendingUp,
  Clock,
  CheckCircle2,
  AlertCircle,
  Filter,
} from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import StatusBadge from "@/components/common/StatusBadge.vue";

const router = useRouter();
const reviewStore = usePerformanceReviewStore();
const { teamReviews, reviewsLoading, pagination } = storeToRefs(reviewStore);

const selectedCycle = ref("all");
const selectedStatus = ref("all");
const searchQuery = ref("");

const filteredReviews = computed(() => {
  let reviews = teamReviews.value;

  if (selectedCycle.value !== "all") {
    reviews = reviews.filter(
      (r) => r.cycle_id === parseInt(selectedCycle.value),
    );
  }

  if (selectedStatus.value !== "all") {
    reviews = reviews.filter((r) => r.status === selectedStatus.value);
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    reviews = reviews.filter(
      (r) =>
        r.employee?.full_name?.toLowerCase().includes(query) ||
        r.employee?.email?.toLowerCase().includes(query),
    );
  }

  return reviews;
});

const cycles = computed(() => {
  const uniqueCycles = [...new Set(teamReviews.value.map((r) => r.cycle))];
  return uniqueCycles.filter(Boolean);
});

const statusConfig = {
  pending_self: {
    label: "Pending Self-Assessment",
    color: "warning",
    icon: Clock,
    action: "Waiting for employee",
  },
  pending_manager: {
    label: "Pending Manager Review",
    color: "danger",
    icon: AlertCircle,
    action: "Action Required",
  },
  pending_calibration: {
    label: "Pending Calibration",
    color: "info",
    icon: TrendingUp,
    action: "Waiting for HR",
  },
  completed: {
    label: "Completed",
    color: "success",
    icon: CheckCircle2,
    action: "Completed",
  },
  cancelled: {
    label: "Cancelled",
    color: "secondary",
    icon: AlertCircle,
    action: "Cancelled",
  },
};

const pendingManagerReviews = computed(() => {
  return teamReviews.value.filter((r) => r.status === "pending_manager").length;
});

const completedReviews = computed(() => {
  return teamReviews.value.filter((r) => r.status === "completed").length;
});

const averageRating = computed(() => {
  const completedWithRatings = teamReviews.value.filter(
    (r) => r.status === "completed" && r.final_rating,
  );
  if (completedWithRatings.length === 0) return 0;
  const sum = completedWithRatings.reduce(
    (acc, r) => acc + parseFloat(r.final_rating),
    0,
  );
  return (sum / completedWithRatings.length).toFixed(2);
});

const viewReview = (reviewId) => {
  router.push({
    name: "admin.performance.review.detail",
    params: { id: reviewId },
  });
};

onMounted(async () => {
  await reviewStore.fetchTeamReviews();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-brand-dark">
          Team Performance Reviews
        </h1>
        <p class="text-brand-light mt-1">
          Manage and review your team's performance evaluations
        </p>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <MainCard
        class="bg-gradient-to-br from-orange-50 to-orange-100 border-orange-200"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-orange-700">
              Pending Your Review
            </p>
            <p class="text-3xl font-bold text-orange-900 mt-2">
              {{ pendingManagerReviews }}
            </p>
          </div>
          <div
            class="w-12 h-12 bg-orange-200 rounded-full flex items-center justify-center"
          >
            <AlertCircle class="w-6 h-6 text-orange-700" />
          </div>
        </div>
      </MainCard>

      <MainCard
        class="bg-gradient-to-br from-green-50 to-green-100 border-green-200"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-green-700">Completed Reviews</p>
            <p class="text-3xl font-bold text-green-900 mt-2">
              {{ completedReviews }}
            </p>
          </div>
          <div
            class="w-12 h-12 bg-green-200 rounded-full flex items-center justify-between"
          >
            <CheckCircle2 class="w-6 h-6 text-green-700" />
          </div>
        </div>
      </MainCard>

      <MainCard
        class="bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-blue-700">Average Team Rating</p>
            <p class="text-3xl font-bold text-blue-900 mt-2">
              {{ averageRating }} / 5.00
            </p>
          </div>
          <div
            class="w-12 h-12 bg-blue-200 rounded-full flex items-center justify-center"
          >
            <TrendingUp class="w-6 h-6 text-blue-700" />
          </div>
        </div>
      </MainCard>
    </div>

    <!-- Filters -->
    <MainCard>
      <div class="space-y-4">
        <div class="flex flex-wrap gap-4">
          <div class="flex-1 min-w-[250px]">
            <label class="block text-sm font-medium text-brand-dark mb-2"
              >Search Employee</label
            >
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search by name or email..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
            />
          </div>
          <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-brand-dark mb-2"
              >Review Cycle</label
            >
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
            <label class="block text-sm font-medium text-brand-dark mb-2"
              >Status</label
            >
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
      </div>
    </MainCard>

    <!-- Loading State -->
    <div v-if="reviewsLoading" class="flex justify-center items-center py-12">
      <div
        class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"
      ></div>
    </div>

    <!-- Reviews Table -->
    <MainCard v-else-if="filteredReviews.length > 0">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-200">
              <th
                class="text-left py-3 px-4 text-sm font-semibold text-brand-dark"
              >
                Employee
              </th>
              <th
                class="text-left py-3 px-4 text-sm font-semibold text-brand-dark"
              >
                Review Cycle
              </th>
              <th
                class="text-left py-3 px-4 text-sm font-semibold text-brand-dark"
              >
                Status
              </th>
              <th
                class="text-left py-3 px-4 text-sm font-semibold text-brand-dark"
              >
                Self-Assessment
              </th>
              <th
                class="text-left py-3 px-4 text-sm font-semibold text-brand-dark"
              >
                Overall Score
              </th>
              <th
                class="text-right py-3 px-4 text-sm font-semibold text-brand-dark"
              >
                Actions
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="review in filteredReviews"
              :key="review.id"
              class="border-b border-gray-100 hover:bg-gray-50 transition-colors cursor-pointer"
              @click="viewReview(review.id)"
            >
              <td class="py-4 px-4">
                <div class="flex items-center gap-3">
                  <div
                    class="w-10 h-10 bg-brand-primary rounded-full flex items-center justify-center text-white font-semibold"
                  >
                    {{ (review.staff_member ?? review.employee)?.full_name?.charAt(0) || "E" }}
                  </div>
                  <div>
                    <p class="font-medium text-brand-dark">
                      {{ (review.staff_member ?? review.employee)?.full_name || "Unknown" }}
                    </p>
                    <p class="text-sm text-brand-light">
                      {{ (review.staff_member ?? review.employee)?.email || "-" }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="py-4 px-4">
                <div class="flex items-center gap-2">
                  <Calendar class="w-4 h-4 text-brand-light" />
                  <span class="text-sm text-brand-dark">{{
                    review.cycle?.name || "-"
                  }}</span>
                </div>
              </td>
              <td class="py-4 px-4">
                <StatusBadge
                  :status="statusConfig[review.status]?.label"
                  :color="statusConfig[review.status]?.color"
                />
                <p class="text-xs text-brand-light mt-1">
                  {{ statusConfig[review.status]?.action }}
                </p>
              </td>
              <td class="py-4 px-4">
                <span
                  v-if="review.self_assessment_submitted_at"
                  class="text-sm text-green-600 flex items-center gap-1"
                >
                  <CheckCircle2 class="w-4 h-4" />
                  {{
                    new Date(
                      review.self_assessment_submitted_at,
                    ).toLocaleDateString()
                  }}
                </span>
                <span v-else class="text-sm text-gray-400">Not submitted</span>
              </td>
              <td class="py-4 px-4">
                <div v-if="review.final_rating" class="flex items-center gap-2">
                  <span class="text-lg font-bold text-brand-dark">{{
                    review.final_rating
                  }}</span>
                  <span class="text-xs text-brand-light">/ 5.00</span>
                </div>
                <span v-else class="text-sm text-gray-400">-</span>
              </td>
              <td class="py-4 px-4 text-right">
                <button
                  class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                  :class="
                    review.status === 'pending_manager'
                      ? 'bg-brand-primary text-white hover:bg-brand-primary-dark'
                      : 'bg-gray-100 text-brand-dark hover:bg-gray-200'
                  "
                  @click.stop="viewReview(review.id)"
                >
                  {{
                    review.status === "pending_manager"
                      ? "Review Now"
                      : "View Details"
                  }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </MainCard>

    <!-- Empty State -->
    <EmptyState
      v-else
      icon="Users"
      title="No Team Reviews"
      description="You don't have any team members to review yet. Reviews will appear here when HR creates review cycles."
    />
  </div>
</template>
