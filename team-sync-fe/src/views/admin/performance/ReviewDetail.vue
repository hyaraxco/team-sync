<script setup>
import { ref, computed, onMounted, watch, shallowRef } from "vue";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { usePerformanceReviewStore } from "@/stores/performanceReview";
import { useAuthStore } from "@/stores/auth";
import MainCard from "@/components/common/MainCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import Alert from "@/components/common/Alert.vue";
import ConfirmationModal from "@/components/common/ConfirmationModal.vue";
import {
  ArrowLeft as ArrowLeftIcon,
  FileText as FileTextIcon,
  User as UserIcon,
  UserCheck as UserCheckIcon,
  Scale as ScaleIcon,
  Calendar as CalendarIcon,
  Clock as ClockIcon,
  Star as StarIcon,
  AlertCircle as AlertCircleIcon,
  CheckCircle as CheckCircleIcon,
  Send as SendIcon,
} from "lucide-vue-next";

const route = useRoute();
const router = useRouter();
const reviewStore = usePerformanceReviewStore();
const authStore = useAuthStore();

const {
  currentReview,
  sections,
  reviewsLoading,
  sectionsLoading,
  error,
  success,
  calibrationContext,
  calibrationContextLoading,
  readinessResult,
  readinessLoading,
} = storeToRefs(reviewStore);

const reviewId = computed(() => route.params.id);
const activeTab = shallowRef("overview");
const submitting = shallowRef(false);
const showConfirmModal = shallowRef(false);
const confirmAction = shallowRef(null);

// Assessment form data
const selfAssessmentForm = ref({});
const managerAssessmentForm = ref({});
const calibrationForm = ref({});

// Tabs config
const tabs = [
  { id: "overview", label: "Overview", icon: FileTextIcon },
  { id: "self-assessment", label: "Self Assessment", icon: UserIcon },
  {
    id: "manager-assessment",
    label: "Manager Assessment",
    icon: UserCheckIcon,
  },
  { id: "calibration", label: "Calibration", icon: ScaleIcon },
];

// Role detection (matches existing pattern in TaskDetailModal.vue)
const roleNames = computed(() =>
  (authStore.user?.roles || []).map((role) => role.name || role),
);
const hasRole = (role) => roleNames.value.includes(role);
const currentEmployeeId = computed(
  () =>
    authStore.user?.employee_profile?.id || authStore.user?.employeeProfile?.id,
);

// Review data helpers
const review = computed(() => currentReview.value);
const reviewStatus = computed(() => review.value?.status || "");

const statusConfig = {
  pending_self: {
    label: "Pending Self-Assessment",
    color: "bg-yellow-100 text-yellow-700 border-yellow-200",
  },
  pending_manager: {
    label: "Pending Manager Review",
    color: "bg-blue-100 text-blue-700 border-blue-200",
  },
  pending_calibration: {
    label: "Pending Calibration",
    color: "bg-purple-100 text-purple-700 border-purple-200",
  },
  completed: {
    label: "Completed",
    color: "bg-emerald-100 text-emerald-700 border-emerald-200",
  },
  cancelled: {
    label: "Cancelled",
    color: "bg-red-100 text-red-700 border-red-200",
  },
};

const ratingLabels = [
  {
    min: 4.5,
    label: "Outstanding",
    color: "text-emerald-600",
    bg: "bg-emerald-100 text-emerald-700 border-emerald-200",
  },
  {
    min: 3.5,
    label: "Exceeds Expectations",
    color: "text-blue-600",
    bg: "bg-blue-100 text-blue-700 border-blue-200",
  },
  {
    min: 2.5,
    label: "Meets Expectations",
    color: "text-yellow-600",
    bg: "bg-yellow-100 text-yellow-700 border-yellow-200",
  },
  {
    min: 1.5,
    label: "Needs Improvement",
    color: "text-orange-600",
    bg: "bg-orange-100 text-orange-700 border-orange-200",
  },
  {
    min: 0,
    label: "Unsatisfactory",
    color: "text-red-600",
    bg: "bg-red-100 text-red-700 border-red-200",
  },
];

const getRatingLabel = (rating) => {
  if (!rating) return null;
  return ratingLabels.find((r) => rating >= r.min);
};

const formatDate = (date) => {
  if (!date) return "-";
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const isDeadlinePassed = (deadline) => {
  if (!deadline) return false;
  return new Date(deadline) < new Date();
};

// Permission checks
const canSubmitSelfAssessment = computed(() => {
  return (
    reviewStatus.value === "pending_self" &&
    currentEmployeeId.value === review.value?.staff_member_id
  );
});

const canSubmitManagerAssessment = computed(() => {
  return (
    reviewStatus.value === "pending_manager" &&
    (hasRole("manager") || hasRole("hr")) &&
    currentEmployeeId.value === review.value?.reviewer_id
  );
});

const canCalibrate = computed(() => {
  return (
    reviewStatus.value === "pending_calibration" &&
    hasRole("hr") &&
    currentEmployeeId.value !== review.value?.employee_id
  );
});

// Get sections list (from review responses or from sections endpoint)
const displaySections = computed(() => {
  if (review.value?.responses?.length > 0) {
    return review.value.responses.map((r) => r.section).filter(Boolean);
  }
  return sections.value || [];
});

const getResponseForSection = (sectionId) => {
  return review.value?.responses?.find((r) => r.section_id === sectionId);
};

// Initialize form data from existing responses
const initFormData = () => {
  if (!review.value) return;

  const sectionList =
    sections.value?.length > 0
      ? sections.value
      : review.value.responses?.map((r) => r.section).filter(Boolean) || [];

  sectionList.forEach((section) => {
    const existingResponse = review.value.responses?.find(
      (r) => r.section_id === section.id,
    );

    selfAssessmentForm.value[section.id] = {
      rating: existingResponse?.self_rating || null,
      comments: existingResponse?.self_comments || "",
    };

    managerAssessmentForm.value[section.id] = {
      rating: existingResponse?.manager_rating || null,
      comments: existingResponse?.manager_comments || "",
    };

    calibrationForm.value[section.id] = {
      rating: existingResponse?.final_rating || null,
    };
  });
};

// Form validation
const isSelfAssessmentValid = computed(() => {
  return displaySections.value.every((section) => {
    const form = selfAssessmentForm.value[section.id];
    return form && form.rating && form.rating >= 1 && form.rating <= 5;
  });
});

const isManagerAssessmentValid = computed(() => {
  return displaySections.value.every((section) => {
    const form = managerAssessmentForm.value[section.id];
    return form && form.rating && form.rating >= 1 && form.rating <= 5;
  });
});

const isCalibrationValid = computed(() => {
  return canCalibrate.value;
});

const calculatedFinalRating = computed(() => {
  if (!displaySections.value.length) return null;
  let weightedSum = 0;
  let totalWeight = 0;
  for (const section of displaySections.value) {
    const response = getResponseForSection(section.id);
    const calibrationOverride = calibrationForm.value[section.id]?.rating;
    const effectiveRating =
      calibrationOverride || response?.manager_rating || response?.self_rating;
    if (effectiveRating && section.weight) {
      weightedSum += effectiveRating * parseFloat(section.weight);
      totalWeight += parseFloat(section.weight);
    }
  }
  if (totalWeight <= 0) return null;
  return Math.max(1, Math.min(5, weightedSum / totalWeight)).toFixed(2);
});

const calculatedManagerRating = computed(() => {
  if (!displaySections.value.length) return null;
  let weightedSum = 0;
  let totalWeight = 0;
  for (const section of displaySections.value) {
    const rating = managerAssessmentForm.value[section.id]?.rating;
    if (rating && section.weight) {
      weightedSum += rating * parseFloat(section.weight);
      totalWeight += parseFloat(section.weight);
    }
  }
  if (totalWeight <= 0) return null;
  return Math.max(1, Math.min(5, weightedSum / totalWeight)).toFixed(2);
});

// Submit handlers
const openConfirmModal = (action) => {
  confirmAction.value = action;
  showConfirmModal.value = true;
};

const handleConfirm = async () => {
  showConfirmModal.value = false;
  if (confirmAction.value === "self") await submitSelfAssessment();
  else if (confirmAction.value === "manager") await submitManagerAssessment();
  else if (confirmAction.value === "calibrate") await submitCalibration();
};

// Readiness-aware calibration opener
const openCalibrateConfirm = async () => {
  // Fetch readiness before showing confirm modal
  try {
    await reviewStore.fetchValidateReadiness(reviewId.value);
  } catch {
    // If readiness check fails, proceed anyway
  }
  openConfirmModal("calibrate");
};

const submitSelfAssessment = async () => {
  submitting.value = true;
  try {
    const responses = displaySections.value.map((section) => ({
      section_id: section.id,
      rating: selfAssessmentForm.value[section.id]?.rating,
      comments: selfAssessmentForm.value[section.id]?.comments || null,
    }));
    await reviewStore.submitSelfAssessment(reviewId.value, responses);
    await reviewStore.fetchReviewById(reviewId.value);
  } finally {
    submitting.value = false;
  }
};

const submitManagerAssessment = async () => {
  submitting.value = true;
  try {
    const responses = displaySections.value.map((section) => ({
      section_id: section.id,
      rating: managerAssessmentForm.value[section.id]?.rating,
      comments: managerAssessmentForm.value[section.id]?.comments || null,
    }));
    await reviewStore.submitManagerAssessment(reviewId.value, responses, null);
    await reviewStore.fetchReviewById(reviewId.value);
  } finally {
    submitting.value = false;
  }
};

const submitCalibration = async () => {
  submitting.value = true;
  try {
    const responses = displaySections.value
      .map((section) => ({
        section_id: section.id,
        rating: calibrationForm.value[section.id]?.rating || null,
      }))
      .filter((r) => r.rating);
    await reviewStore.calibrateReview(reviewId.value, responses);
    await reviewStore.fetchReviewById(reviewId.value);
  } finally {
    submitting.value = false;
  }
};

// Confirmation modal text
const confirmModalConfig = computed(() => {
  const readiness = readinessResult.value;
  const warningMessages = readiness?.warnings?.map((w) => `⚠️ ${w.message}`).join('\n') || '';
  const blockerMessages = readiness?.blockers?.map((b) => `🚫 ${b.message}`).join('\n') || '';
  const summaryText = readiness?.summary
    ? `\n\nData Summary:\n• Sections rated: ${readiness.summary.sections_rated}\n• Goals: ${readiness.summary.goals_count}\n• Positive feedback: ${readiness.summary.positive_feedback_count}`
    : '';

  const configs = {
    self: {
      title: "Submit Self Assessment",
      message:
        "Are you sure you want to submit your self-assessment? This action cannot be undone.",
      type: "warning",
    },
    manager: {
      title: "Submit Manager Assessment",
      message:
        "Are you sure you want to submit the manager assessment? This will advance the review to calibration.",
      type: "warning",
    },
    calibrate: {
      title: readiness?.has_warnings ? "⚠️ Finalize Calibration — Warnings Detected" : "Finalize Calibration",
      message: blockerMessages
        ? `Cannot finalize:\n${blockerMessages}`
        : warningMessages
          ? `${warningMessages}${summaryText}\n\nProceed with finalize despite warnings?`
          : `Are you sure you want to finalize this review? The final rating will be locked.${summaryText}`,
      type: readiness?.blockers?.length ? "danger" : readiness?.has_warnings ? "warning" : "warning",
    },
  };
  return configs[confirmAction.value] || configs.self;
});

// Fetch data on mount
onMounted(async () => {
  reviewStore.resetState();
  await Promise.all([
    reviewStore.fetchReviewById(reviewId.value),
    reviewStore.fetchActiveSections(),
  ]);
  initFormData();

  if (
    currentReview.value?.status === "pending_calibration" &&
    canCalibrate.value
  ) {
    reviewStore.fetchCalibrationContext(reviewId.value);
  }

  // Auto-fetch readiness data for Performance Data Summary card (HR/calibrator only)
  if (['pending_calibration', 'completed'].includes(currentReview.value?.status) && hasRole('hr')) {
    reviewStore.fetchValidateReadiness(reviewId.value).catch(() => {});
  }
});

watch(currentReview, (newVal) => {
  initFormData();
  if (newVal?.status === "pending_calibration" && canCalibrate.value) {
    reviewStore.fetchCalibrationContext(reviewId.value);
  }
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <button
        @click="router.back()"
        class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
      >
        <ArrowLeftIcon class="w-5 h-5 text-brand-dark" />
      </button>
      <div>
        <h1 class="text-3xl font-bold text-brand-dark">Review Detail</h1>
        <p class="text-brand-light mt-1">
          {{ review?.cycle?.name || "Loading..." }}
        </p>
      </div>
    </div>

    <!-- Error Alert -->
    <Alert
      v-if="error"
      type="danger"
      title="Error"
      :message="error"
      :show="!!error"
    />

    <!-- Success Alert -->
    <Alert
      v-if="success"
      type="success"
      title="Success"
      :message="success"
      :show="!!success"
    />

    <!-- Loading -->
    <div v-if="reviewsLoading" class="flex justify-center items-center py-12">
      <div
        class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"
      ></div>
    </div>

    <template v-else-if="review">
      <!-- Tab Navigation -->
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-3">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            @click="activeTab = tab.id"
            class="rounded-[8px] px-4 py-3 border transition-all duration-300 flex items-center justify-center gap-2"
            :class="
              activeTab === tab.id
                ? 'blue-gradient blue-btn-shadow border-[#2151A0] text-white'
                : 'border-[#DCDEDD] text-brand-dark hover:border-[#0C51D9] hover:border-2 bg-white'
            "
          >
            <component
              :is="tab.icon"
              class="w-4 h-4"
              :class="activeTab === tab.id ? 'text-white' : 'text-gray-600'"
            />
            <span class="text-sm font-semibold">{{ tab.label }}</span>
          </button>
        </div>
      </div>

      <!-- Tab: Overview -->
      <div v-show="activeTab === 'overview'" class="space-y-6">
        <!-- Status & Action Banners -->
        <div
          v-if="canSubmitSelfAssessment"
          class="p-4 bg-yellow-50 border border-yellow-200 rounded-xl flex items-center gap-3"
        >
          <AlertCircleIcon class="w-5 h-5 text-yellow-600 flex-shrink-0" />
          <div class="flex-1">
            <p class="text-sm font-semibold text-yellow-800">
              Action Required: Self Assessment
            </p>
            <p class="text-sm text-yellow-700">
              Please complete your self-assessment
              <span v-if="review.cycle?.self_assessment_deadline">
                before
                {{ formatDate(review.cycle.self_assessment_deadline) }}
              </span>
            </p>
          </div>
          <button
            @click="activeTab = 'self-assessment'"
            class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors text-sm font-semibold"
          >
            Start Assessment
          </button>
        </div>

        <div
          v-if="canSubmitManagerAssessment"
          class="p-4 bg-blue-50 border border-blue-200 rounded-xl flex items-center gap-3"
        >
          <AlertCircleIcon class="w-5 h-5 text-blue-600 flex-shrink-0" />
          <div class="flex-1">
            <p class="text-sm font-semibold text-blue-800">
              Action Required: Manager Assessment
            </p>
            <p class="text-sm text-blue-700">
              Please complete the manager assessment for
              {{ (review.staff_member ?? review.employee)?.full_name }}
              <span v-if="review.cycle?.manager_assessment_deadline">
                before
                {{ formatDate(review.cycle.manager_assessment_deadline) }}
              </span>
            </p>
          </div>
          <button
            @click="activeTab = 'manager-assessment'"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-semibold"
          >
            Start Review
          </button>
        </div>

        <div
          v-if="canCalibrate"
          class="p-4 bg-purple-50 border border-purple-200 rounded-xl flex items-center gap-3"
        >
          <AlertCircleIcon class="w-5 h-5 text-purple-600 flex-shrink-0" />
          <div class="flex-1">
            <p class="text-sm font-semibold text-purple-800">
              Action Required: Calibration
            </p>
            <p class="text-sm text-purple-700">
              Please calibrate and finalize this review
              <span v-if="review.cycle?.calibration_deadline">
                before {{ formatDate(review.cycle.calibration_deadline) }}
              </span>
            </p>
          </div>
          <button
            @click="activeTab = 'calibration'"
            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-semibold"
          >
            Start Calibration
          </button>
        </div>

        <!-- Review Info Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Employee Info -->
          <MainCard>
            <div class="flex items-center gap-4">
              <div
                class="w-12 h-12 bg-brand-primary rounded-full flex items-center justify-center text-white font-semibold text-lg"
              >
                {{
                  (review.staff_member ?? review.employee)?.full_name?.charAt(
                    0,
                  ) || "?"
                }}
              </div>
              <div>
                <p class="text-sm text-brand-light">Employee</p>
                <p class="text-lg font-semibold text-brand-dark">
                  {{
                    (review.staff_member ?? review.employee)?.full_name || "-"
                  }}
                </p>
                <p class="text-sm text-brand-light">
                  {{ (review.staff_member ?? review.employee)?.email || "" }}
                </p>
              </div>
            </div>
          </MainCard>

          <!-- Reviewer Info -->
          <MainCard>
            <div class="flex items-center gap-4">
              <div
                class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold text-lg"
              >
                {{ review.reviewer?.full_name?.charAt(0) || "?" }}
              </div>
              <div>
                <p class="text-sm text-brand-light">Reviewer</p>
                <div class="flex items-center gap-2">
                  <p class="text-lg font-semibold text-brand-dark">
                    {{ review.reviewer?.full_name || "-" }}
                  </p>
                  <span
                    v-if="review.reviewer?.user?.roles?.length"
                    class="px-2 py-0.5 rounded text-[10px] bg-blue-100 text-blue-700 border border-blue-200"
                  >
                    {{ review.reviewer.user.roles[0].name }}
                  </span>
                </div>
                <p class="text-sm text-brand-light mt-0.5">
                  {{ review.reviewer?.email || "" }}
                </p>
              </div>
            </div>
          </MainCard>

          <!-- Cycle Info -->
          <MainCard>
            <div class="space-y-3">
              <div class="flex items-center gap-2">
                <CalendarIcon class="w-5 h-5 text-brand-light" />
                <p class="text-sm text-brand-light">Review Cycle</p>
              </div>
              <p class="text-lg font-semibold text-brand-dark">
                {{ review.cycle?.name || "-" }}
              </p>
              <div class="flex items-center gap-2">
                <span
                  class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700 capitalize"
                >
                  {{ review.cycle?.cycle_type?.replace("_", " ") || "-" }}
                </span>
              </div>
              <p class="text-sm text-brand-light">
                Period:
                {{ formatDate(review.cycle?.review_period_start) }} -
                {{ formatDate(review.cycle?.review_period_end) }}
              </p>
            </div>
          </MainCard>

          <!-- Status & Rating -->
          <MainCard>
            <div class="space-y-3">
              <div class="flex items-center gap-2">
                <StarIcon class="w-5 h-5 text-brand-light" />
                <p class="text-sm text-brand-light">Status & Rating</p>
              </div>
              <div class="flex items-center gap-3">
                <span
                  class="px-3 py-1 text-sm font-medium rounded-full border"
                  :class="
                    statusConfig[reviewStatus]?.color ||
                    'bg-gray-100 text-gray-700'
                  "
                >
                  {{ statusConfig[reviewStatus]?.label || reviewStatus }}
                </span>
              </div>
              <div v-if="review.final_rating" class="mt-2">
                <p
                  class="text-3xl font-bold"
                  :class="getRatingLabel(review.final_rating)?.color"
                >
                  {{ Number(review.final_rating).toFixed(2) }}
                </p>
                <span
                  v-if="getRatingLabel(review.final_rating)"
                  class="px-2 py-1 text-xs font-medium rounded-full border mt-1 inline-block"
                  :class="getRatingLabel(review.final_rating)?.bg"
                >
                  {{
                    review.final_rating_label ||
                    getRatingLabel(review.final_rating)?.label
                  }}
                </span>
              </div>
              <p v-else class="text-sm text-brand-light italic">
                No final rating yet
              </p>
            </div>
          </MainCard>
        </div>

        <!-- Performance Outcome (visible after calibration) -->
        <MainCard
          v-if="review.status === 'completed' && review.outcome_applied_at"
          class="border-l-4 border-l-emerald-500"
        >
          <h4 class="text-sm font-semibold text-brand-dark mb-4 flex items-center gap-2">
            <StarIcon class="w-4 h-4 text-emerald-600" />
            Performance Outcome
          </h4>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-3 bg-emerald-50 rounded-lg border border-emerald-100 text-center">
              <p class="text-xs text-emerald-600 mb-1">Bonus</p>
              <p class="text-lg font-bold text-emerald-700">{{ review.bonus_months }} mo</p>
            </div>
            <div class="p-3 bg-blue-50 rounded-lg border border-blue-100 text-center">
              <p class="text-xs text-blue-600 mb-1">Salary Increase</p>
              <p class="text-lg font-bold text-blue-700">{{ review.salary_increase_pct }}%</p>
            </div>
            <div class="p-3 rounded-lg border text-center" :class="review.promotion_eligible ? 'bg-purple-50 border-purple-100' : 'bg-gray-50 border-gray-100'">
              <p class="text-xs mb-1" :class="review.promotion_eligible ? 'text-purple-600' : 'text-gray-500'">Promotion</p>
              <p class="text-lg font-bold" :class="review.promotion_eligible ? 'text-purple-700' : 'text-gray-400'">
                {{ review.promotion_eligible ? 'Eligible' : 'N/A' }}
              </p>
            </div>
            <div class="p-3 rounded-lg border text-center" :class="review.pip_required ? 'bg-red-50 border-red-100' : 'bg-gray-50 border-gray-100'">
              <p class="text-xs mb-1" :class="review.pip_required ? 'text-red-600' : 'text-gray-500'">PIP</p>
              <p class="text-lg font-bold" :class="review.pip_required ? 'text-red-700' : 'text-gray-400'">
                {{ review.pip_required ? 'Required' : 'Not Required' }}
              </p>
            </div>
          </div>
        </MainCard>

        <!-- Goal & Feedback Summary (visible for pending_calibration / completed) -->
        <MainCard
          v-if="['pending_calibration', 'completed'].includes(reviewStatus)"
          class="border-l-4 border-l-brand-primary"
        >
          <h3 class="text-lg font-semibold text-brand-dark mb-4">
            📊 Performance Data Summary
          </h3>
          <div v-if="readinessLoading" class="flex justify-center py-4">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-brand-primary"></div>
          </div>
          <div v-else-if="readinessResult?.summary" class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <!-- Sections Rated -->
            <div class="p-3 bg-blue-50 rounded-lg">
              <p class="text-xs text-blue-600 uppercase tracking-wide font-medium">Sections Rated</p>
              <p class="text-2xl font-bold text-blue-800 mt-1">
                {{ readinessResult.summary.sections_rated }}
              </p>
            </div>
            <!-- Goals Total -->
            <div class="p-3 rounded-lg" :class="readinessResult.summary.goals_count > 0 ? 'bg-emerald-50' : 'bg-amber-50'">
              <p class="text-xs uppercase tracking-wide font-medium" :class="readinessResult.summary.goals_count > 0 ? 'text-emerald-600' : 'text-amber-600'">
                Goals Total
              </p>
              <p class="text-2xl font-bold mt-1" :class="readinessResult.summary.goals_count > 0 ? 'text-emerald-800' : 'text-amber-800'">
                {{ readinessResult.summary.goals_count }}
              </p>
              <p v-if="readinessResult.summary.goals_count === 0" class="text-xs text-amber-600 mt-1">
                ⚠️ C3 & C4 = 0
              </p>
            </div>
            <!-- Goals Completed -->
            <div class="p-3 rounded-lg" :class="readinessResult.summary.goals_completed > 0 ? 'bg-emerald-50' : 'bg-gray-50'">
              <p class="text-xs uppercase tracking-wide font-medium" :class="readinessResult.summary.goals_completed > 0 ? 'text-emerald-600' : 'text-gray-500'">
                Goals Completed
              </p>
              <p class="text-2xl font-bold mt-1" :class="readinessResult.summary.goals_completed > 0 ? 'text-emerald-800' : 'text-gray-400'">
                {{ readinessResult.summary.goals_completed ?? 0 }}
              </p>
            </div>
            <!-- Goals On-Time -->
            <div class="p-3 rounded-lg" :class="readinessResult.summary.goals_on_time > 0 ? 'bg-teal-50' : 'bg-gray-50'">
              <p class="text-xs uppercase tracking-wide font-medium" :class="readinessResult.summary.goals_on_time > 0 ? 'text-teal-600' : 'text-gray-500'">
                Goals On-Time
              </p>
              <p class="text-2xl font-bold mt-1" :class="readinessResult.summary.goals_on_time > 0 ? 'text-teal-800' : 'text-gray-400'">
                {{ readinessResult.summary.goals_on_time ?? 0 }}
              </p>
            </div>
            <!-- Positive Feedback -->
            <div class="p-3 rounded-lg" :class="readinessResult.summary.positive_feedback_count > 0 ? 'bg-purple-50' : 'bg-amber-50'">
              <p class="text-xs uppercase tracking-wide font-medium" :class="readinessResult.summary.positive_feedback_count > 0 ? 'text-purple-600' : 'text-amber-600'">
                Positive Feedback
              </p>
              <p class="text-2xl font-bold mt-1" :class="readinessResult.summary.positive_feedback_count > 0 ? 'text-purple-800' : 'text-amber-800'">
                {{ readinessResult.summary.positive_feedback_count }}
              </p>
              <p v-if="readinessResult.summary.positive_feedback_count === 0" class="text-xs text-amber-600 mt-1">
                ⚠️ C5 = 0
              </p>
            </div>
          </div>
          <div v-else class="text-sm text-brand-light italic">
            Data summary not available. Click the Calibration tab to load readiness data.
          </div>
        </MainCard>

        <!-- Deadlines -->
        <MainCard>
          <h3 class="text-lg font-semibold text-brand-dark mb-4">
            Deadlines & Timeline
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div
              class="p-3 rounded-lg"
              :class="
                isDeadlinePassed(review.cycle?.self_assessment_deadline) &&
                reviewStatus === 'pending_self'
                  ? 'bg-red-50 border border-red-200'
                  : 'bg-gray-50'
              "
            >
              <div class="flex items-center gap-2 mb-1">
                <ClockIcon class="w-4 h-4 text-brand-light" />
                <p class="text-sm font-medium text-brand-dark">
                  Self Assessment
                </p>
              </div>
              <p class="text-sm text-brand-light">
                Deadline:
                {{ formatDate(review.cycle?.self_assessment_deadline) }}
              </p>
              <p
                v-if="review.self_assessment_submitted_at"
                class="text-sm text-emerald-600 flex items-center gap-1 mt-1"
              >
                <CheckCircleIcon class="w-3 h-3" />
                Submitted
                {{ formatDate(review.self_assessment_submitted_at) }}
              </p>
            </div>
            <div
              class="p-3 rounded-lg"
              :class="
                isDeadlinePassed(review.cycle?.manager_assessment_deadline) &&
                reviewStatus === 'pending_manager'
                  ? 'bg-red-50 border border-red-200'
                  : 'bg-gray-50'
              "
            >
              <div class="flex items-center gap-2 mb-1">
                <ClockIcon class="w-4 h-4 text-brand-light" />
                <p class="text-sm font-medium text-brand-dark">
                  Manager Assessment
                </p>
              </div>
              <p class="text-sm text-brand-light">
                Deadline:
                {{ formatDate(review.cycle?.manager_assessment_deadline) }}
              </p>
              <p
                v-if="review.manager_assessment_submitted_at"
                class="text-sm text-emerald-600 flex items-center gap-1 mt-1"
              >
                <CheckCircleIcon class="w-3 h-3" />
                Submitted
                {{ formatDate(review.manager_assessment_submitted_at) }}
              </p>
            </div>
            <div
              class="p-3 rounded-lg"
              :class="
                isDeadlinePassed(review.cycle?.calibration_deadline) &&
                reviewStatus === 'pending_calibration'
                  ? 'bg-red-50 border border-red-200'
                  : 'bg-gray-50'
              "
            >
              <div class="flex items-center gap-2 mb-1">
                <ClockIcon class="w-4 h-4 text-brand-light" />
                <p class="text-sm font-medium text-brand-dark">Calibration</p>
              </div>
              <p class="text-sm text-brand-light">
                Deadline:
                {{ formatDate(review.cycle?.calibration_deadline) }}
              </p>
              <p
                v-if="review.calibrated_at"
                class="text-sm text-emerald-600 flex items-center gap-1 mt-1"
              >
                <CheckCircleIcon class="w-3 h-3" />
                Calibrated {{ formatDate(review.calibrated_at) }}
              </p>
            </div>
          </div>
        </MainCard>
      </div>

      <!-- Tab: Self Assessment -->
      <div v-show="activeTab === 'self-assessment'" class="space-y-6">
        <div
          v-if="sectionsLoading"
          class="flex justify-center items-center py-12"
        >
          <div
            class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"
          ></div>
        </div>

        <template v-else>
          <!-- Section Cards -->
          <MainCard
            v-for="section in displaySections"
            :key="'self-' + section.id"
          >
            <div class="space-y-4">
              <!-- Section Header -->
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-lg font-semibold text-brand-dark">
                    {{ section.name }}
                  </h3>
                  <p
                    v-if="section.description"
                    class="text-sm text-brand-light mt-1"
                  >
                    {{ section.description }}
                  </p>
                </div>
                <span
                  class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700"
                >
                  Weight: {{ section.weight }}%
                </span>
              </div>

              <!-- Editable Form -->
              <template v-if="canSubmitSelfAssessment">
                <div>
                  <label class="block text-sm font-medium text-brand-dark mb-2"
                    >Rating</label
                  >
                  <div class="flex gap-2">
                    <button
                      v-for="n in 5"
                      :key="n"
                      type="button"
                      @click="selfAssessmentForm[section.id].rating = n"
                      class="w-12 h-12 rounded-lg border-2 flex items-center justify-center font-semibold transition-all"
                      :class="
                        selfAssessmentForm[section.id]?.rating === n
                          ? 'border-brand-primary bg-brand-primary text-white'
                          : 'border-gray-200 text-brand-dark hover:border-brand-primary'
                      "
                    >
                      {{ n }}
                    </button>
                  </div>
                  <p class="text-xs text-brand-light mt-1">
                    {{
                      selfAssessmentForm[section.id]?.rating
                        ? getRatingLabel(selfAssessmentForm[section.id].rating)
                            ?.label
                        : "Select a rating (1-5)"
                    }}
                  </p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-brand-dark mb-2"
                    >Comments (Optional)</label
                  >
                  <textarea
                    v-model="selfAssessmentForm[section.id].comments"
                    rows="3"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent resize-none"
                    placeholder="Share your thoughts on your performance in this area..."
                  ></textarea>
                </div>
              </template>

              <!-- Readonly Display -->
              <template v-else>
                <div v-if="getResponseForSection(section.id)?.self_rating">
                  <p class="text-sm font-medium text-brand-dark mb-1">Rating</p>
                  <div class="flex items-center gap-2">
                    <div class="flex gap-1">
                      <div
                        v-for="n in 5"
                        :key="n"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-semibold"
                        :class="
                          n <= getResponseForSection(section.id)?.self_rating
                            ? 'bg-brand-primary text-white'
                            : 'bg-gray-100 text-gray-400'
                        "
                      >
                        {{ n }}
                      </div>
                    </div>
                    <span
                      class="text-sm font-medium"
                      :class="
                        getRatingLabel(
                          getResponseForSection(section.id)?.self_rating,
                        )?.color
                      "
                    >
                      {{
                        getRatingLabel(
                          getResponseForSection(section.id)?.self_rating,
                        )?.label
                      }}
                    </span>
                  </div>
                  <p
                    v-if="getResponseForSection(section.id)?.self_comments"
                    class="text-sm text-brand-light mt-2 p-3 bg-gray-50 rounded-lg"
                  >
                    {{ getResponseForSection(section.id)?.self_comments }}
                  </p>
                </div>
                <div v-else class="text-sm text-brand-light italic">
                  No self-assessment submitted yet
                </div>
              </template>
            </div>
          </MainCard>

          <!-- Submit Button -->
          <div v-if="canSubmitSelfAssessment" class="flex justify-end">
            <button
              @click="openConfirmModal('self')"
              :disabled="!isSelfAssessmentValid || submitting"
              class="px-6 py-3 rounded-lg font-semibold flex items-center gap-2 transition-all"
              :class="
                isSelfAssessmentValid && !submitting
                  ? 'blue-gradient text-white hover:opacity-90'
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              "
            >
              <SendIcon class="w-4 h-4" />
              Submit Self Assessment
            </button>
          </div>
        </template>
      </div>

      <!-- Tab: Manager Assessment -->
      <div v-show="activeTab === 'manager-assessment'" class="space-y-6">
        <div
          v-if="sectionsLoading"
          class="flex justify-center items-center py-12"
        >
          <div
            class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"
          ></div>
        </div>

        <template v-else>
          <!-- Self-assessment summary -->
          <div
            v-if="
              reviewStatus !== 'pending_self' &&
              review?.responses?.some((r) => r.self_rating)
            "
            class="p-4 bg-gray-50 border border-gray-200 rounded-xl"
          >
            <p class="text-sm font-semibold text-brand-dark mb-2">
              Employee Self-Assessment Summary
            </p>
            <div class="flex flex-wrap gap-3">
              <div
                v-for="section in displaySections"
                :key="'summary-' + section.id"
                class="flex items-center gap-2 px-3 py-1 bg-white rounded-lg border"
              >
                <span class="text-xs text-brand-light"
                  >{{ section.name }}:</span
                >
                <span
                  class="text-sm font-semibold"
                  :class="
                    getRatingLabel(
                      getResponseForSection(section.id)?.self_rating,
                    )?.color
                  "
                >
                  {{ getResponseForSection(section.id)?.self_rating || "-" }}
                </span>
              </div>
            </div>
          </div>

          <!-- Section Cards -->
          <MainCard
            v-for="section in displaySections"
            :key="'mgr-' + section.id"
          >
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-lg font-semibold text-brand-dark">
                    {{ section.name }}
                  </h3>
                  <p
                    v-if="section.description"
                    class="text-sm text-brand-light mt-1"
                  >
                    {{ section.description }}
                  </p>
                </div>
                <span
                  class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700"
                >
                  Weight: {{ section.weight }}%
                </span>
              </div>

              <!-- Employee self-assessment reference -->
              <div
                v-if="getResponseForSection(section.id)?.self_rating"
                class="p-3 bg-yellow-50 border border-yellow-100 rounded-lg"
              >
                <p class="text-xs font-medium text-yellow-700 mb-1">
                  Employee Self-Rating
                </p>
                <div class="flex items-center gap-2">
                  <span class="text-sm font-bold text-yellow-800"
                    >{{
                      getResponseForSection(section.id)?.self_rating
                    }}/5</span
                  >
                  <span class="text-xs text-yellow-600">{{
                    getRatingLabel(
                      getResponseForSection(section.id)?.self_rating,
                    )?.label
                  }}</span>
                </div>
                <p
                  v-if="getResponseForSection(section.id)?.self_comments"
                  class="text-xs text-yellow-700 mt-1"
                >
                  "{{ getResponseForSection(section.id)?.self_comments }}"
                </p>
              </div>

              <!-- Editable Form -->
              <template v-if="canSubmitManagerAssessment">
                <div>
                  <label class="block text-sm font-medium text-brand-dark mb-2"
                    >Manager Rating</label
                  >
                  <div class="flex gap-2">
                    <button
                      v-for="n in 5"
                      :key="n"
                      type="button"
                      @click="managerAssessmentForm[section.id].rating = n"
                      class="w-12 h-12 rounded-lg border-2 flex items-center justify-center font-semibold transition-all"
                      :class="
                        managerAssessmentForm[section.id]?.rating === n
                          ? 'border-brand-primary bg-brand-primary text-white'
                          : 'border-gray-200 text-brand-dark hover:border-brand-primary'
                      "
                    >
                      {{ n }}
                    </button>
                  </div>
                  <p class="text-xs text-brand-light mt-1">
                    {{
                      managerAssessmentForm[section.id]?.rating
                        ? getRatingLabel(
                            managerAssessmentForm[section.id].rating,
                          )?.label
                        : "Select a rating (1-5)"
                    }}
                  </p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-brand-dark mb-2"
                    >Manager Comments (Optional)</label
                  >
                  <textarea
                    v-model="managerAssessmentForm[section.id].comments"
                    rows="3"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent resize-none"
                    placeholder="Provide your assessment and feedback..."
                  ></textarea>
                </div>
              </template>

              <!-- Readonly Display -->
              <template v-else>
                <div v-if="getResponseForSection(section.id)?.manager_rating">
                  <p class="text-sm font-medium text-brand-dark mb-1">
                    Manager Rating
                  </p>
                  <div class="flex items-center gap-2">
                    <div class="flex gap-1">
                      <div
                        v-for="n in 5"
                        :key="n"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-semibold"
                        :class="
                          n <= getResponseForSection(section.id)?.manager_rating
                            ? 'bg-blue-500 text-white'
                            : 'bg-gray-100 text-gray-400'
                        "
                      >
                        {{ n }}
                      </div>
                    </div>
                    <span
                      class="text-sm font-medium"
                      :class="
                        getRatingLabel(
                          getResponseForSection(section.id)?.manager_rating,
                        )?.color
                      "
                    >
                      {{
                        getRatingLabel(
                          getResponseForSection(section.id)?.manager_rating,
                        )?.label
                      }}
                    </span>
                  </div>
                  <p
                    v-if="getResponseForSection(section.id)?.manager_comments"
                    class="text-sm text-brand-light mt-2 p-3 bg-gray-50 rounded-lg"
                  >
                    {{ getResponseForSection(section.id)?.manager_comments }}
                  </p>
                </div>
                <div v-else class="text-sm text-brand-light italic">
                  No manager assessment submitted yet
                </div>
              </template>
            </div>
          </MainCard>

          <!-- Auto-Calculated Rating Preview (Manager) -->
          <MainCard v-if="canSubmitManagerAssessment">
            <div class="space-y-4">
              <h3 class="text-lg font-semibold text-brand-dark">
                Projected Final Rating
              </h3>
              <p class="text-sm text-brand-light">
                Auto-calculated from your section ratings above.
              </p>
              <div class="flex items-center gap-4">
                <p
                  class="text-3xl font-bold"
                  :class="
                    getRatingLabel(calculatedManagerRating)?.color ||
                    'text-gray-400'
                  "
                >
                  {{ calculatedManagerRating || "-" }}
                </p>
                <span
                  v-if="calculatedManagerRating"
                  class="px-3 py-1 text-sm font-medium rounded-full border"
                  :class="getRatingLabel(calculatedManagerRating)?.bg"
                >
                  {{ getRatingLabel(calculatedManagerRating)?.label }}
                </span>
              </div>
            </div>
          </MainCard>

          <!-- Submit Button -->
          <div v-if="canSubmitManagerAssessment" class="flex justify-end">
            <button
              @click="openConfirmModal('manager')"
              :disabled="!isManagerAssessmentValid || submitting"
              class="px-6 py-3 rounded-lg font-semibold flex items-center gap-2 transition-all"
              :class="
                isManagerAssessmentValid && !submitting
                  ? 'blue-gradient text-white hover:opacity-90'
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              "
            >
              <SendIcon class="w-4 h-4" />
              Submit Manager Assessment
            </button>
          </div>
        </template>
      </div>

      <!-- Tab: Calibration -->
      <div v-show="activeTab === 'calibration'" class="space-y-6">
        <div
          v-if="sectionsLoading"
          class="flex justify-center items-center py-12"
        >
          <div
            class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"
          ></div>
        </div>

        <template v-else>
          <!-- Side-by-side comparison -->
          <MainCard
            v-for="section in displaySections"
            :key="'cal-' + section.id"
          >
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-lg font-semibold text-brand-dark">
                    {{ section.name }}
                  </h3>
                  <p
                    v-if="section.description"
                    class="text-sm text-brand-light mt-1"
                  >
                    {{ section.description }}
                  </p>
                </div>
                <span
                  class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700"
                >
                  Weight: {{ section.weight }}%
                </span>
              </div>

              <!-- Comparison Grid -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Self Rating -->
                <div
                  class="p-3 bg-yellow-50 border border-yellow-100 rounded-lg"
                >
                  <p class="text-xs font-medium text-yellow-700 mb-2">
                    Self Assessment
                  </p>
                  <div
                    v-if="getResponseForSection(section.id)?.self_rating"
                    class="flex items-center gap-2"
                  >
                    <span class="text-lg font-bold text-yellow-800"
                      >{{
                        getResponseForSection(section.id)?.self_rating
                      }}/5</span
                    >
                    <span class="text-xs text-yellow-600">{{
                      getRatingLabel(
                        getResponseForSection(section.id)?.self_rating,
                      )?.label
                    }}</span>
                  </div>
                  <p v-else class="text-xs text-yellow-600 italic">
                    Not submitted
                  </p>
                  <p
                    v-if="getResponseForSection(section.id)?.self_comments"
                    class="text-xs text-yellow-700 mt-2"
                  >
                    "{{ getResponseForSection(section.id)?.self_comments }}"
                  </p>
                </div>

                <!-- Manager Rating -->
                <div class="p-3 bg-blue-50 border border-blue-100 rounded-lg">
                  <p class="text-xs font-medium text-blue-700 mb-2">
                    Manager Assessment
                  </p>
                  <div
                    v-if="getResponseForSection(section.id)?.manager_rating"
                    class="flex items-center gap-2"
                  >
                    <span class="text-lg font-bold text-blue-800"
                      >{{
                        getResponseForSection(section.id)?.manager_rating
                      }}/5</span
                    >
                    <span class="text-xs text-blue-600">{{
                      getRatingLabel(
                        getResponseForSection(section.id)?.manager_rating,
                      )?.label
                    }}</span>
                  </div>
                  <p v-else class="text-xs text-blue-600 italic">
                    Not submitted
                  </p>
                  <p
                    v-if="getResponseForSection(section.id)?.manager_comments"
                    class="text-xs text-blue-700 mt-2"
                  >
                    "{{ getResponseForSection(section.id)?.manager_comments }}"
                  </p>
                </div>
              </div>

              <!-- Calibration Rating (optional per section) -->
              <div v-if="canCalibrate">
                <label class="block text-sm font-medium text-brand-dark mb-2"
                  >Calibrated Rating (Optional)</label
                >
                <div class="flex gap-2">
                  <button
                    v-for="n in 5"
                    :key="n"
                    type="button"
                    @click="
                      calibrationForm[section.id] = {
                        rating:
                          calibrationForm[section.id]?.rating === n ? null : n,
                      }
                    "
                    class="w-10 h-10 rounded-lg border-2 flex items-center justify-center font-semibold transition-all text-sm"
                    :class="
                      calibrationForm[section.id]?.rating === n
                        ? 'border-purple-500 bg-purple-500 text-white'
                        : 'border-gray-200 text-brand-dark hover:border-purple-500'
                    "
                  >
                    {{ n }}
                  </button>
                </div>
              </div>

              <!-- Readonly calibrated rating -->
              <div v-else-if="getResponseForSection(section.id)?.final_rating">
                <p class="text-sm font-medium text-brand-dark mb-1">
                  Calibrated Rating
                </p>
                <div class="flex items-center gap-2">
                  <div class="flex gap-1">
                    <div
                      v-for="n in 5"
                      :key="n"
                      class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-semibold"
                      :class="
                        n <= getResponseForSection(section.id)?.final_rating
                          ? 'bg-purple-500 text-white'
                          : 'bg-gray-100 text-gray-400'
                      "
                    >
                      {{ n }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </MainCard>

          <!-- Auto-Calculated Rating & Normalization Context -->
          <MainCard v-if="canCalibrate">
            <div class="space-y-6">
              <!-- Projected Final Rating -->
              <div>
                <h3 class="text-lg font-semibold text-brand-dark">
                  Projected Final Rating
                </h3>
                <p class="text-sm text-brand-light mt-1">
                  Auto-calculated from weighted section ratings. Adjust section
                  overrides above to see changes.
                </p>
                <div class="mt-3 flex items-center gap-4">
                  <p
                    class="text-4xl font-bold"
                    :class="
                      getRatingLabel(calculatedFinalRating)?.color ||
                      'text-gray-400'
                    "
                  >
                    {{ calculatedFinalRating || "-" }}
                  </p>
                  <span
                    v-if="calculatedFinalRating"
                    class="px-3 py-1 text-sm font-medium rounded-full border"
                    :class="getRatingLabel(calculatedFinalRating)?.bg"
                  >
                    {{ getRatingLabel(calculatedFinalRating)?.label }}
                  </span>
                </div>
              </div>

              <!-- Manager's Recommended Rating -->
              <div
                v-if="review.manager_recommended_rating"
                class="p-4 bg-blue-50 border border-blue-200 rounded-xl"
              >
                <p class="text-sm font-medium text-blue-700">
                  Manager's Recommended Rating
                </p>
                <div class="flex items-center gap-3 mt-2">
                  <p class="text-2xl font-bold text-blue-800">
                    {{ Number(review.manager_recommended_rating).toFixed(2) }}
                  </p>
                  <span
                    class="px-2 py-1 text-xs font-medium rounded-full border"
                    :class="
                      getRatingLabel(review.manager_recommended_rating)?.bg
                    "
                  >
                    {{
                      getRatingLabel(review.manager_recommended_rating)?.label
                    }}
                  </span>
                </div>
              </div>

              <!-- Normalization Context -->
              <div v-if="calibrationContext && !calibrationContextLoading">
                <h4 class="text-sm font-semibold text-brand-dark mb-3">
                  Cross-Manager Normalization Context
                </h4>
                <div
                  class="p-4 bg-gray-50 border border-gray-200 rounded-xl space-y-3"
                >
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-brand-light">Cycle</span>
                    <span class="text-sm font-medium text-brand-dark">{{
                      calibrationContext.cycle_name
                    }}</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-brand-light"
                      >Total Reviews in Cycle</span
                    >
                    <span class="text-sm font-medium text-brand-dark">{{
                      calibrationContext.total_reviews_in_cycle
                    }}</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-brand-light"
                      >Cycle Average Rating</span
                    >
                    <span class="text-sm font-bold text-brand-dark">{{
                      calibrationContext.cycle_avg_rating || "-"
                    }}</span>
                  </div>
                  <div
                    v-if="calibrationContext.manager_breakdown?.length"
                    class="mt-3 pt-3 border-t border-gray-200"
                  >
                    <p
                      class="text-xs font-semibold text-brand-dark mb-2 uppercase tracking-wider"
                    >
                      Manager Breakdown
                    </p>
                    <div class="space-y-2">
                      <div
                        v-for="manager in calibrationContext.manager_breakdown"
                        :key="manager.manager_id"
                        class="flex items-center justify-between p-2 rounded-lg"
                        :class="
                          manager.is_current_reviewer
                            ? 'bg-blue-50 border border-blue-200'
                            : 'bg-white'
                        "
                      >
                        <div class="flex items-center gap-2">
                          <span class="text-sm text-brand-dark">{{
                            manager.manager_name
                          }}</span>
                          <span
                            v-if="manager.is_current_reviewer"
                            class="text-xs text-blue-600 font-medium"
                            >(This reviewer)</span
                          >
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                          <span class="text-brand-light"
                            >{{ manager.review_count }} reviews</span
                          >
                          <span class="font-semibold text-brand-dark"
                            >Avg: {{ manager.avg_rating || "-" }}</span
                          >
                          <span class="text-xs text-brand-light"
                            >{{ manager.min_rating }}-{{
                              manager.max_rating
                            }}</span
                          >
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div
                v-else-if="calibrationContextLoading"
                class="flex justify-center py-4"
              >
                <div
                  class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-primary"
                ></div>
              </div>

              <!-- Submit Button -->
              <div class="flex justify-end pt-2">
                <button
                  @click="openCalibrateConfirm()"
                  :disabled="!isCalibrationValid || submitting"
                  class="px-6 py-3 rounded-lg font-semibold flex items-center gap-2 transition-all"
                  :class="
                    isCalibrationValid && !submitting
                      ? 'blue-gradient blue-btn-shadow border-[#2151A0] text-white'
                      : 'border-[#DCDEDD] text-brand-dark hover:border-[#0C51D9] hover:border-2 bg-white'
                  "
                >
                  <ScaleIcon class="w-4 h-4" />
                  Finalize Calibration
                </button>
              </div>
            </div>
          </MainCard>

          <!-- Readonly final result for completed reviews -->
          <MainCard v-if="reviewStatus === 'completed' && review.final_rating">
            <div class="text-center py-4">
              <CheckCircleIcon
                class="w-12 h-12 text-emerald-500 mx-auto mb-3"
              />
              <p class="text-sm text-brand-light mb-1">
                Final Calibrated Rating
              </p>
              <p
                class="text-4xl font-bold"
                :class="getRatingLabel(review.final_rating)?.color"
              >
                {{ Number(review.final_rating).toFixed(2) }}
              </p>
              <span
                class="px-4 py-1 text-sm font-medium rounded-full border mt-2 inline-block"
                :class="getRatingLabel(review.final_rating)?.bg"
              >
                {{
                  review.final_rating_label ||
                  getRatingLabel(review.final_rating)?.label
                }}
              </span>
              <p v-if="review.calibrator" class="text-sm text-brand-light mt-3">
                Calibrated by {{ review.calibrator.name }} on
                {{ formatDate(review.calibrated_at) }}
              </p>
            </div>
          </MainCard>
        </template>
      </div>
    </template>

    <!-- Not Found -->
    <EmptyState
      v-else-if="!reviewsLoading"
      icon="SearchX"
      title="Review Not Found"
      subtitle="The review you're looking for doesn't exist or you don't have access to it."
    />

    <!-- Confirmation Modal -->
    <ConfirmationModal
      :show="showConfirmModal"
      :title="confirmModalConfig.title"
      :message="confirmModalConfig.message"
      :type="confirmModalConfig.type"
      :loading="submitting"
      confirm-text="Yes, Submit"
      cancel-text="Cancel"
      @confirm="handleConfirm"
      @cancel="showConfirmModal = false"
    />
  </div>
</template>
