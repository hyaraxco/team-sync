<script setup>
import { ref, onMounted, computed } from "vue";
import { storeToRefs } from "pinia";
import { usePerformanceReviewStore } from "@/stores/performanceReview";
import { useRouter } from "vue-router";
import {
  Calendar,
  Plus,
  Users,
  TrendingUp,
  Clock,
  CheckCircle2,
  XCircle,
} from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import StatusBadge from "@/components/common/StatusBadge.vue";

const router = useRouter();
const reviewStore = usePerformanceReviewStore();
const { cycles, cyclesLoading } = storeToRefs(reviewStore);

const selectedType = ref("all");
const selectedStatus = ref("all");

const filteredCycles = computed(() => {
  let filtered = cycles.value;

  if (selectedType.value !== "all") {
    filtered = filtered.filter((c) => c.cycle_type === selectedType.value);
  }

  if (selectedStatus.value !== "all") {
    filtered = filtered.filter((c) => c.status === selectedStatus.value);
  }

  return filtered;
});

const statusConfig = {
  draft: { label: "Draft", color: "secondary", icon: Clock },
  active: { label: "Active", color: "success", icon: TrendingUp },
  completed: { label: "Completed", color: "info", icon: CheckCircle2 },
  cancelled: { label: "Cancelled", color: "danger", icon: XCircle },
};

const cycleTypes = [
  { value: "quarterly", label: "Quarterly" },
  { value: "semi_annual", label: "Semi-Annual" },
  { value: "annual", label: "Annual" },
  { value: "probation", label: "Probation" },
];

const createCycle = () => {
  router.push({ name: "admin.performance.cycles.create" });
};

const viewCycle = (cycleId) => {
  router.push({
    name: "admin.performance.cycles.detail",
    params: { id: cycleId },
  });
};

onMounted(async () => {
  await reviewStore.fetchCycles();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-brand-dark">
          Performance Review Cycles
        </h1>
        <p class="text-brand-light mt-1">
          Manage company-wide performance review cycles
        </p>
      </div>
      <button
        class="flex items-center gap-2 px-4 py-2 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors"
        @click="createCycle"
      >
        <Plus class="w-5 h-5" />
        Create Cycle
      </button>
    </div>

    <!-- Filters -->
    <MainCard>
      <div class="flex flex-wrap gap-4">
        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm font-medium text-brand-dark mb-2"
            >Cycle Type</label
          >
          <select
            v-model="selectedType"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
          >
            <option value="all">All Types</option>
            <option
              v-for="type in cycleTypes"
              :key="type.value"
              :value="type.value"
            >
              {{ type.label }}
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
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>
    </MainCard>

    <!-- Loading State -->
    <div v-if="cyclesLoading" class="flex justify-center items-center py-12">
      <div
        class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"
      ></div>
    </div>

    <!-- Cycles Grid -->
    <div v-else-if="filteredCycles.length > 0" class="grid gap-6">
      <MainCard
        v-for="cycle in filteredCycles"
        :key="cycle.id"
        class="hover:shadow-lg transition-shadow duration-200 cursor-pointer"
        @click="viewCycle(cycle.id)"
      >
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-3">
              <Calendar class="w-6 h-6 text-brand-primary" />
              <h3 class="text-xl font-semibold text-brand-dark">
                {{ cycle.name }}
              </h3>
              <StatusBadge
                :status="statusConfig[cycle.status]?.label"
                :color="statusConfig[cycle.status]?.color"
              />
              <span
                class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm font-medium"
              >
                {{
                  cycleTypes.find((t) => t.value === cycle.cycle_type)?.label
                }}
              </span>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-4">
              <div>
                <p
                  class="text-xs text-brand-light uppercase tracking-wide mb-1"
                >
                  Review Period
                </p>
                <p class="text-sm font-medium text-brand-dark">
                  {{ new Date(cycle.review_period_start).toLocaleDateString() }}
                </p>
                <p class="text-sm font-medium text-brand-dark">
                  to
                  {{ new Date(cycle.review_period_end).toLocaleDateString() }}
                </p>
              </div>

              <div>
                <p
                  class="text-xs text-brand-light uppercase tracking-wide mb-1"
                >
                  Cycle Duration
                </p>
                <p class="text-sm font-medium text-brand-dark">
                  {{ new Date(cycle.start_date).toLocaleDateString() }}
                </p>
                <p class="text-sm font-medium text-brand-dark">
                  to {{ new Date(cycle.end_date).toLocaleDateString() }}
                </p>
              </div>

              <div>
                <p
                  class="text-xs text-brand-light uppercase tracking-wide mb-1"
                >
                  Self-Assessment Deadline
                </p>
                <p class="text-sm font-medium text-brand-dark">
                  {{
                    cycle.self_assessment_deadline
                      ? new Date(
                          cycle.self_assessment_deadline,
                        ).toLocaleDateString()
                      : "Not set"
                  }}
                </p>
              </div>

              <div>
                <p
                  class="text-xs text-brand-light uppercase tracking-wide mb-1"
                >
                  Manager Assessment Deadline
                </p>
                <p class="text-sm font-medium text-brand-dark">
                  {{
                    cycle.manager_assessment_deadline
                      ? new Date(
                          cycle.manager_assessment_deadline,
                        ).toLocaleDateString()
                      : "Not set"
                  }}
                </p>
              </div>
            </div>

            <!-- Progress Bar (if active) -->
            <div v-if="cycle.status === 'active'" class="mt-4">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-brand-light"
                  >Cycle Progress</span
                >
                <span class="text-xs text-brand-dark">
                  {{
                    Math.round(
                      ((new Date() - new Date(cycle.start_date)) /
                        (new Date(cycle.end_date) -
                          new Date(cycle.start_date))) *
                        100,
                    )
                  }}%
                </span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div
                  class="bg-brand-primary h-full rounded-full transition-all duration-300"
                  :style="{
                    width: `${Math.min(100, Math.round(((new Date() - new Date(cycle.start_date)) / (new Date(cycle.end_date) - new Date(cycle.start_date))) * 100))}%`,
                  }"
                ></div>
              </div>
            </div>
          </div>

          <div class="ml-4">
            <button
              class="px-4 py-2 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors"
              @click.stop="viewCycle(cycle.id)"
            >
              Manage
            </button>
          </div>
        </div>
      </MainCard>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      icon="Calendar"
      title="No Review Cycles"
      description="Create your first performance review cycle to start evaluating your team's performance."
    >
      <button
        class="mt-4 flex items-center gap-2 px-6 py-3 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors"
        @click="createCycle"
      >
        <Plus class="w-5 h-5" />
        Create First Cycle
      </button>
    </EmptyState>
  </div>
</template>
