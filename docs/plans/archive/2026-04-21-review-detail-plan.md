# ReviewDetail.vue + Performance Data Seeder Implementation Plan

> **Execution:** Use the **executing-plans** skill to execute this plan in single-flow mode.

**Goal:** Create minimal performance seed data and implement the ReviewDetail.vue page with tab-based layout for viewing and submitting performance assessments.

**Architecture:** Two-part implementation: (1) Laravel seeder that creates review sections, one active cycle, and reviews with varied statuses for the 3 existing users; (2) Vue 3 ReviewDetail page with 4 tabs (Overview, Self Assessment, Manager Assessment, Calibration) using the app's primary blue-gradient tab pattern, with role/status-aware form rendering.

**Tech Stack:** Laravel 12 (PHP 8.2, Eloquent), Vue 3 (Composition API, Pinia, Vue Router), Tailwind CSS, Lucide icons.

---

### Task 1: Add PerformanceReviewSectionSeeder to DatabaseSeeder

**Files:**
- Modify: `team-sync-be/database/seeders/DatabaseSeeder.php:14-36`

**Step 1: Add the section seeder call**

Add `PerformanceReviewSectionSeeder::class` to the `$this->call()` array in `DatabaseSeeder.php`, after `LeaveEntitlementSeeder::class`:

```php
LeaveEntitlementSeeder::class,

// 3. Seed performance review sections
PerformanceReviewSectionSeeder::class,
```

**Step 2: Run the section seeder to verify**

Run: `docker exec -it teamsync-api php artisan db:seed --class=PerformanceReviewSectionSeeder`
Expected: Seeder runs successfully, 5 sections created.

**Step 3: Commit**

```bash
git add team-sync-be/database/seeders/DatabaseSeeder.php
git commit -m "feat: add PerformanceReviewSectionSeeder to DatabaseSeeder"
```

---

### Task 2: Create PerformanceDataSeeder

**Files:**
- Create: `team-sync-be/database/seeders/PerformanceDataSeeder.php`
- Modify: `team-sync-be/database/seeders/DatabaseSeeder.php`

**Step 1: Create the seeder file**

Create `team-sync-be/database/seeders/PerformanceDataSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReviewResponse;
use App\Models\PerformanceReviewSection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PerformanceDataSeeder extends Seeder
{
    public function run(): void
    {
        // Get existing users
        $manager = User::where('email', 'yudhis@teamsync.com')->first();
        $employee = User::where('email', 'agung@teamsync.com')->first();
        $hr = User::where('email', 'tasyia@teamsync.com')->first();

        if (!$manager || !$employee || !$hr) {
            $this->command->warn('Required users not found. Run ManagerSeeder, EmployeeSeeder, and HrSeeder first.');
            return;
        }

        $managerProfile = $manager->employeeProfile;
        $employeeProfile = $employee->employeeProfile;
        $hrProfile = $hr->employeeProfile;

        if (!$managerProfile || !$employeeProfile || !$hrProfile) {
            $this->command->warn('Employee profiles not found for required users.');
            return;
        }

        $sections = PerformanceReviewSection::where('is_active', true)->orderBy('order')->get();

        if ($sections->isEmpty()) {
            $this->command->warn('No active review sections found. Run PerformanceReviewSectionSeeder first.');
            return;
        }

        // Create one active review cycle
        $cycle = PerformanceReviewCycle::updateOrCreate(
            ['name' => 'Q1 2026 Performance Review'],
            [
                'cycle_type' => 'quarterly',
                'start_date' => '2026-01-01',
                'end_date' => '2026-06-30',
                'review_period_start' => '2026-01-01',
                'review_period_end' => '2026-03-31',
                'status' => 'active',
                'self_assessment_deadline' => '2026-04-30',
                'manager_assessment_deadline' => '2026-05-15',
                'calibration_deadline' => '2026-05-31',
                'created_by' => $hr->id,
            ]
        );

        // Review 1: Employee Agung - pending_self (employee can test self-assessment)
        $review1 = PerformanceReview::updateOrCreate(
            ['cycle_id' => $cycle->id, 'employee_id' => $employeeProfile->id],
            [
                'reviewer_id' => $managerProfile->id,
                'status' => 'pending_self',
            ]
        );

        // Review 2: Manager Yudhis - pending_manager (has self-assessment filled)
        $review2 = PerformanceReview::updateOrCreate(
            ['cycle_id' => $cycle->id, 'employee_id' => $managerProfile->id],
            [
                'reviewer_id' => $managerProfile->id,
                'status' => 'pending_manager',
                'self_assessment_submitted_at' => Carbon::now()->subDays(5),
            ]
        );

        // Seed self-assessment responses for review 2
        foreach ($sections as $section) {
            PerformanceReviewResponse::updateOrCreate(
                ['review_id' => $review2->id, 'section_id' => $section->id],
                [
                    'self_rating' => rand(3, 5),
                    'self_comments' => 'Self-assessment for ' . $section->name . '. I have demonstrated strong performance in this area.',
                ]
            );
        }

        // Review 3: HR Tasyia - pending_calibration (has self + manager assessment)
        $review3 = PerformanceReview::updateOrCreate(
            ['cycle_id' => $cycle->id, 'employee_id' => $hrProfile->id],
            [
                'reviewer_id' => $managerProfile->id,
                'status' => 'pending_calibration',
                'self_assessment_submitted_at' => Carbon::now()->subDays(10),
                'manager_assessment_submitted_at' => Carbon::now()->subDays(3),
                'final_rating' => 3.80,
            ]
        );

        // Seed both self and manager responses for review 3
        foreach ($sections as $section) {
            PerformanceReviewResponse::updateOrCreate(
                ['review_id' => $review3->id, 'section_id' => $section->id],
                [
                    'self_rating' => rand(3, 5),
                    'self_comments' => 'Self-assessment for ' . $section->name . '.',
                    'manager_rating' => rand(3, 4),
                    'manager_comments' => 'Manager review for ' . $section->name . '. Good performance overall.',
                ]
            );
        }

        // Create a second completed cycle for history
        $completedCycle = PerformanceReviewCycle::updateOrCreate(
            ['name' => 'Q4 2025 Performance Review'],
            [
                'cycle_type' => 'quarterly',
                'start_date' => '2025-10-01',
                'end_date' => '2025-12-31',
                'review_period_start' => '2025-10-01',
                'review_period_end' => '2025-12-31',
                'status' => 'completed',
                'self_assessment_deadline' => '2026-01-15',
                'manager_assessment_deadline' => '2026-01-31',
                'calibration_deadline' => '2026-02-15',
                'created_by' => $hr->id,
            ]
        );

        // Review 4: Employee Agung - completed (all data filled)
        $review4 = PerformanceReview::updateOrCreate(
            ['cycle_id' => $completedCycle->id, 'employee_id' => $employeeProfile->id],
            [
                'reviewer_id' => $managerProfile->id,
                'status' => 'completed',
                'self_assessment_submitted_at' => Carbon::parse('2026-01-10'),
                'manager_assessment_submitted_at' => Carbon::parse('2026-01-25'),
                'final_rating' => 4.20,
                'final_rating_label' => 'Exceeds Expectations',
                'calibrated_at' => Carbon::parse('2026-02-10'),
                'calibrated_by' => $hr->id,
                'completed_at' => Carbon::parse('2026-02-10'),
            ]
        );

        // Seed all responses for completed review 4
        foreach ($sections as $section) {
            $selfRating = rand(3, 5);
            $managerRating = rand(3, 5);
            PerformanceReviewResponse::updateOrCreate(
                ['review_id' => $review4->id, 'section_id' => $section->id],
                [
                    'self_rating' => $selfRating,
                    'self_comments' => 'Self-assessment for ' . $section->name . '. I believe I performed well.',
                    'manager_rating' => $managerRating,
                    'manager_comments' => 'Manager assessment for ' . $section->name . '. Solid performance.',
                    'final_rating' => round(($selfRating + $managerRating) / 2),
                ]
            );
        }

        $this->command->info('Performance data seeded successfully:');
        $this->command->info("- Cycle: {$cycle->name} (active) with 3 reviews");
        $this->command->info("- Cycle: {$completedCycle->name} (completed) with 1 review");
    }
}
```

**Step 2: Add to DatabaseSeeder**

Add `PerformanceDataSeeder::class` after `PerformanceReviewSectionSeeder::class`:

```php
PerformanceReviewSectionSeeder::class,
PerformanceDataSeeder::class,
```

**Step 3: Run the seeder**

Run: `docker exec -it teamsync-api php artisan db:seed --class=PerformanceDataSeeder`
Expected: "Performance data seeded successfully" with 2 cycles and 4 reviews.

**Step 4: Verify data via tinker**

Run: `docker exec -it teamsync-api php artisan tinker --execute="echo 'Cycles: ' . \App\Models\PerformanceReviewCycle::count() . ', Reviews: ' . \App\Models\PerformanceReview::count() . ', Responses: ' . \App\Models\PerformanceReviewResponse::count();"`
Expected: Cycles: 2, Reviews: 4, Responses: 20 (4 reviews x 5 sections)

**Step 5: Commit**

```bash
git add team-sync-be/database/seeders/PerformanceDataSeeder.php team-sync-be/database/seeders/DatabaseSeeder.php
git commit -m "feat: add PerformanceDataSeeder with minimal test data for performance module"
```

---

### Task 3: Implement ReviewDetail.vue - Overview Tab

**Files:**
- Modify: `team-sync-fe/src/views/admin/performance/ReviewDetail.vue`

**Step 1: Implement the full ReviewDetail.vue**

Replace the entire content of `team-sync-fe/src/views/admin/performance/ReviewDetail.vue` with the complete implementation. This is a large file so it will be built incrementally across Tasks 3-6, but the final file structure is:

```vue
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { usePerformanceReviewStore } from '@/stores/performanceReview'
import { useAuthStore } from '@/stores/auth'
import MainCard from '@/components/common/MainCard.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Alert from '@/components/common/Alert.vue'
import ConfirmationModal from '@/components/common/ConfirmationModal.vue'
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
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const reviewStore = usePerformanceReviewStore()
const authStore = useAuthStore()

const { currentReview, sections, reviewsLoading, sectionsLoading, error, success } = storeToRefs(reviewStore)

const reviewId = computed(() => route.params.id)
const activeTab = ref('overview')
const submitting = ref(false)
const showConfirmModal = ref(false)
const confirmAction = ref(null)

// Assessment form data
const selfAssessmentForm = ref({})
const managerAssessmentForm = ref({})
const managerFinalRating = ref(null)
const calibrationForm = ref({})
const calibrationFinalRating = ref(null)
const calibrationFinalLabel = ref('')

// Tabs config
const tabs = [
  { id: 'overview', label: 'Overview', icon: FileTextIcon },
  { id: 'self-assessment', label: 'Self Assessment', icon: UserIcon },
  { id: 'manager-assessment', label: 'Manager Assessment', icon: UserCheckIcon },
  { id: 'calibration', label: 'Calibration', icon: ScaleIcon },
]

// Role detection
const roleNames = computed(() =>
  (authStore.user?.roles || []).map((role) => role.name || role)
)
const hasRole = (role) => roleNames.value.includes(role)
const currentEmployeeId = computed(
  () => authStore.user?.employee_profile?.id || authStore.user?.employeeProfile?.id
)

// Review data helpers
const review = computed(() => currentReview.value)
const reviewStatus = computed(() => review.value?.status || '')

const statusConfig = {
  pending_self: { label: 'Pending Self-Assessment', color: 'bg-yellow-100 text-yellow-700 border-yellow-200' },
  pending_manager: { label: 'Pending Manager Review', color: 'bg-blue-100 text-blue-700 border-blue-200' },
  pending_calibration: { label: 'Pending Calibration', color: 'bg-purple-100 text-purple-700 border-purple-200' },
  completed: { label: 'Completed', color: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
  cancelled: { label: 'Cancelled', color: 'bg-red-100 text-red-700 border-red-200' },
}

const ratingLabels = [
  { min: 4.5, label: 'Outstanding', color: 'text-emerald-600', bg: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
  { min: 3.5, label: 'Exceeds Expectations', color: 'text-blue-600', bg: 'bg-blue-100 text-blue-700 border-blue-200' },
  { min: 2.5, label: 'Meets Expectations', color: 'text-yellow-600', bg: 'bg-yellow-100 text-yellow-700 border-yellow-200' },
  { min: 1.5, label: 'Needs Improvement', color: 'text-orange-600', bg: 'bg-orange-100 text-orange-700 border-orange-200' },
  { min: 0, label: 'Unsatisfactory', color: 'text-red-600', bg: 'bg-red-100 text-red-700 border-red-200' },
]

const getRatingLabel = (rating) => {
  if (!rating) return null
  return ratingLabels.find(r => rating >= r.min)
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const isDeadlinePassed = (deadline) => {
  if (!deadline) return false
  return new Date(deadline) < new Date()
}

// Permission checks
const canSubmitSelfAssessment = computed(() => {
  return reviewStatus.value === 'pending_self' &&
    (hasRole('employee') || hasRole('manager')) &&
    currentEmployeeId.value === review.value?.employee_id
})

const canSubmitManagerAssessment = computed(() => {
  return reviewStatus.value === 'pending_manager' &&
    (hasRole('manager') || hasRole('hr')) &&
    currentEmployeeId.value === review.value?.reviewer_id
})

const canCalibrate = computed(() => {
  return reviewStatus.value === 'pending_calibration' && hasRole('hr')
})

// Initialize form data from existing responses
const initFormData = () => {
  if (!review.value?.responses || !sections.value) return

  const sectionList = sections.value.length > 0 ? sections.value : review.value.responses.map(r => r.section)

  sectionList.forEach(section => {
    const existingResponse = review.value.responses?.find(r => r.section_id === section.id)

    selfAssessmentForm.value[section.id] = {
      rating: existingResponse?.self_rating || null,
      comments: existingResponse?.self_comments || '',
    }

    managerAssessmentForm.value[section.id] = {
      rating: existingResponse?.manager_rating || null,
      comments: existingResponse?.manager_comments || '',
    }

    calibrationForm.value[section.id] = {
      rating: existingResponse?.final_rating || null,
    }
  })

  if (review.value.final_rating) {
    managerFinalRating.value = review.value.final_rating
    calibrationFinalRating.value = review.value.final_rating
  }
  if (review.value.final_rating_label) {
    calibrationFinalLabel.value = review.value.final_rating_label
  }
}

// Get sections list (from review responses or from sections endpoint)
const displaySections = computed(() => {
  if (review.value?.responses?.length > 0) {
    return review.value.responses.map(r => r.section).filter(Boolean)
  }
  return sections.value || []
})

const getResponseForSection = (sectionId) => {
  return review.value?.responses?.find(r => r.section_id === sectionId)
}

// Form validation
const isSelfAssessmentValid = computed(() => {
  return displaySections.value.every(section => {
    const form = selfAssessmentForm.value[section.id]
    return form && form.rating && form.rating >= 1 && form.rating <= 5
  })
})

const isManagerAssessmentValid = computed(() => {
  return displaySections.value.every(section => {
    const form = managerAssessmentForm.value[section.id]
    return form && form.rating && form.rating >= 1 && form.rating <= 5
  })
})

const isCalibrationValid = computed(() => {
  return calibrationFinalRating.value && calibrationFinalRating.value >= 1 && calibrationFinalRating.value <= 5
})

// Submit handlers
const openConfirmModal = (action) => {
  confirmAction.value = action
  showConfirmModal.value = true
}

const handleConfirm = async () => {
  showConfirmModal.value = false
  if (confirmAction.value === 'self') await submitSelfAssessment()
  else if (confirmAction.value === 'manager') await submitManagerAssessment()
  else if (confirmAction.value === 'calibrate') await submitCalibration()
}

const submitSelfAssessment = async () => {
  submitting.value = true
  try {
    const responses = displaySections.value.map(section => ({
      section_id: section.id,
      rating: selfAssessmentForm.value[section.id]?.rating,
      comments: selfAssessmentForm.value[section.id]?.comments || null,
    }))
    await reviewStore.submitSelfAssessment(reviewId.value, responses)
    await reviewStore.fetchReviewById(reviewId.value)
  } finally {
    submitting.value = false
  }
}

const submitManagerAssessment = async () => {
  submitting.value = true
  try {
    const responses = displaySections.value.map(section => ({
      section_id: section.id,
      rating: managerAssessmentForm.value[section.id]?.rating,
      comments: managerAssessmentForm.value[section.id]?.comments || null,
    }))
    await reviewStore.submitManagerAssessment(reviewId.value, responses, managerFinalRating.value)
    await reviewStore.fetchReviewById(reviewId.value)
  } finally {
    submitting.value = false
  }
}

const submitCalibration = async () => {
  submitting.value = true
  try {
    const responses = displaySections.value.map(section => ({
      section_id: section.id,
      rating: calibrationForm.value[section.id]?.rating || null,
    })).filter(r => r.rating)
    await reviewStore.calibrateReview(
      reviewId.value,
      responses,
      calibrationFinalRating.value,
      calibrationFinalLabel.value
    )
    await reviewStore.fetchReviewById(reviewId.value)
  } finally {
    submitting.value = false
  }
}

// Confirmation modal text
const confirmModalConfig = computed(() => {
  const configs = {
    self: {
      title: 'Submit Self Assessment',
      message: 'Are you sure you want to submit your self-assessment? This action cannot be undone.',
      type: 'warning',
    },
    manager: {
      title: 'Submit Manager Assessment',
      message: 'Are you sure you want to submit the manager assessment? This will advance the review to calibration.',
      type: 'warning',
    },
    calibrate: {
      title: 'Finalize Calibration',
      message: 'Are you sure you want to finalize this review? The final rating will be locked.',
      type: 'warning',
    },
  }
  return configs[confirmAction.value] || configs.self
})

// Fetch data on mount
onMounted(async () => {
  reviewStore.resetState()
  await Promise.all([
    reviewStore.fetchReviewById(reviewId.value),
    reviewStore.fetchActiveSections(),
  ])
  initFormData()
})

watch(currentReview, () => {
  initFormData()
})
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
          {{ review?.cycle?.name || 'Loading...' }}
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
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"></div>
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
        <!-- Status & Action Banner -->
        <div
          v-if="canSubmitSelfAssessment"
          class="p-4 bg-yellow-50 border border-yellow-200 rounded-xl flex items-center gap-3"
        >
          <AlertCircleIcon class="w-5 h-5 text-yellow-600 flex-shrink-0" />
          <div class="flex-1">
            <p class="text-sm font-semibold text-yellow-800">Action Required: Self Assessment</p>
            <p class="text-sm text-yellow-700">
              Please complete your self-assessment
              <span v-if="review.cycle?.self_assessment_deadline">
                before {{ formatDate(review.cycle.self_assessment_deadline) }}
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
            <p class="text-sm font-semibold text-blue-800">Action Required: Manager Assessment</p>
            <p class="text-sm text-blue-700">
              Please complete the manager assessment for {{ review.employee?.full_name }}
              <span v-if="review.cycle?.manager_assessment_deadline">
                before {{ formatDate(review.cycle.manager_assessment_deadline) }}
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
            <p class="text-sm font-semibold text-purple-800">Action Required: Calibration</p>
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
              <div class="w-12 h-12 bg-brand-primary rounded-full flex items-center justify-center text-white font-semibold text-lg">
                {{ review.employee?.full_name?.charAt(0) || '?' }}
              </div>
              <div>
                <p class="text-sm text-brand-light">Employee</p>
                <p class="text-lg font-semibold text-brand-dark">{{ review.employee?.full_name || '-' }}</p>
                <p class="text-sm text-brand-light">{{ review.employee?.email || '' }}</p>
              </div>
            </div>
          </MainCard>

          <!-- Reviewer Info -->
          <MainCard>
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold text-lg">
                {{ review.reviewer?.full_name?.charAt(0) || '?' }}
              </div>
              <div>
                <p class="text-sm text-brand-light">Reviewer</p>
                <p class="text-lg font-semibold text-brand-dark">{{ review.reviewer?.full_name || '-' }}</p>
                <p class="text-sm text-brand-light">{{ review.reviewer?.email || '' }}</p>
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
              <p class="text-lg font-semibold text-brand-dark">{{ review.cycle?.name || '-' }}</p>
              <div class="flex items-center gap-2">
                <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700 capitalize">
                  {{ review.cycle?.cycle_type?.replace('_', ' ') || '-' }}
                </span>
              </div>
              <p class="text-sm text-brand-light">
                Period: {{ formatDate(review.cycle?.review_period_start) }} - {{ formatDate(review.cycle?.review_period_end) }}
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
                  :class="statusConfig[reviewStatus]?.color || 'bg-gray-100 text-gray-700'"
                >
                  {{ statusConfig[reviewStatus]?.label || reviewStatus }}
                </span>
              </div>
              <div v-if="review.final_rating" class="mt-2">
                <p class="text-3xl font-bold" :class="getRatingLabel(review.final_rating)?.color">
                  {{ Number(review.final_rating).toFixed(2) }}
                </p>
                <span
                  v-if="getRatingLabel(review.final_rating)"
                  class="px-2 py-1 text-xs font-medium rounded-full border mt-1 inline-block"
                  :class="getRatingLabel(review.final_rating)?.bg"
                >
                  {{ review.final_rating_label || getRatingLabel(review.final_rating)?.label }}
                </span>
              </div>
              <p v-else class="text-sm text-brand-light italic">No final rating yet</p>
            </div>
          </MainCard>
        </div>

        <!-- Deadlines -->
        <MainCard>
          <h3 class="text-lg font-semibold text-brand-dark mb-4">Deadlines & Timeline</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-3 rounded-lg" :class="isDeadlinePassed(review.cycle?.self_assessment_deadline) && reviewStatus === 'pending_self' ? 'bg-red-50 border border-red-200' : 'bg-gray-50'">
              <div class="flex items-center gap-2 mb-1">
                <ClockIcon class="w-4 h-4 text-brand-light" />
                <p class="text-sm font-medium text-brand-dark">Self Assessment</p>
              </div>
              <p class="text-sm text-brand-light">Deadline: {{ formatDate(review.cycle?.self_assessment_deadline) }}</p>
              <p v-if="review.self_assessment_submitted_at" class="text-sm text-emerald-600 flex items-center gap-1 mt-1">
                <CheckCircleIcon class="w-3 h-3" /> Submitted {{ formatDate(review.self_assessment_submitted_at) }}
              </p>
            </div>
            <div class="p-3 rounded-lg" :class="isDeadlinePassed(review.cycle?.manager_assessment_deadline) && reviewStatus === 'pending_manager' ? 'bg-red-50 border border-red-200' : 'bg-gray-50'">
              <div class="flex items-center gap-2 mb-1">
                <ClockIcon class="w-4 h-4 text-brand-light" />
                <p class="text-sm font-medium text-brand-dark">Manager Assessment</p>
              </div>
              <p class="text-sm text-brand-light">Deadline: {{ formatDate(review.cycle?.manager_assessment_deadline) }}</p>
              <p v-if="review.manager_assessment_submitted_at" class="text-sm text-emerald-600 flex items-center gap-1 mt-1">
                <CheckCircleIcon class="w-3 h-3" /> Submitted {{ formatDate(review.manager_assessment_submitted_at) }}
              </p>
            </div>
            <div class="p-3 rounded-lg" :class="isDeadlinePassed(review.cycle?.calibration_deadline) && reviewStatus === 'pending_calibration' ? 'bg-red-50 border border-red-200' : 'bg-gray-50'">
              <div class="flex items-center gap-2 mb-1">
                <ClockIcon class="w-4 h-4 text-brand-light" />
                <p class="text-sm font-medium text-brand-dark">Calibration</p>
              </div>
              <p class="text-sm text-brand-light">Deadline: {{ formatDate(review.cycle?.calibration_deadline) }}</p>
              <p v-if="review.calibrated_at" class="text-sm text-emerald-600 flex items-center gap-1 mt-1">
                <CheckCircleIcon class="w-3 h-3" /> Calibrated {{ formatDate(review.calibrated_at) }}
              </p>
            </div>
          </div>
        </MainCard>
      </div>

      <!-- Tab: Self Assessment -->
      <div v-show="activeTab === 'self-assessment'" class="space-y-6">
        <div v-if="sectionsLoading" class="flex justify-center items-center py-12">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"></div>
        </div>

        <template v-else>
          <!-- Section Cards -->
          <MainCard v-for="section in displaySections" :key="section.id">
            <div class="space-y-4">
              <!-- Section Header -->
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-lg font-semibold text-brand-dark">{{ section.name }}</h3>
                  <p v-if="section.description" class="text-sm text-brand-light mt-1">{{ section.description }}</p>
                </div>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700">
                  Weight: {{ section.weight }}%
                </span>
              </div>

              <!-- Editable Form -->
              <template v-if="canSubmitSelfAssessment">
                <div>
                  <label class="block text-sm font-medium text-brand-dark mb-2">Rating</label>
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
                    {{ selfAssessmentForm[section.id]?.rating ? getRatingLabel(selfAssessmentForm[section.id].rating)?.label : 'Select a rating (1-5)' }}
                  </p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-brand-dark mb-2">Comments (Optional)</label>
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
                    <span class="text-sm font-medium" :class="getRatingLabel(getResponseForSection(section.id)?.self_rating)?.color">
                      {{ getRatingLabel(getResponseForSection(section.id)?.self_rating)?.label }}
                    </span>
                  </div>
                  <p v-if="getResponseForSection(section.id)?.self_comments" class="text-sm text-brand-light mt-2 p-3 bg-gray-50 rounded-lg">
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
        <div v-if="sectionsLoading" class="flex justify-center items-center py-12">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"></div>
        </div>

        <template v-else>
          <!-- Info: Show self-assessment summary if available -->
          <div
            v-if="reviewStatus !== 'pending_self' && review?.responses?.some(r => r.self_rating)"
            class="p-4 bg-gray-50 border border-gray-200 rounded-xl"
          >
            <p class="text-sm font-semibold text-brand-dark mb-2">Employee Self-Assessment Summary</p>
            <div class="flex flex-wrap gap-3">
              <div
                v-for="section in displaySections"
                :key="'summary-' + section.id"
                class="flex items-center gap-2 px-3 py-1 bg-white rounded-lg border"
              >
                <span class="text-xs text-brand-light">{{ section.name }}:</span>
                <span class="text-sm font-semibold" :class="getRatingLabel(getResponseForSection(section.id)?.self_rating)?.color">
                  {{ getResponseForSection(section.id)?.self_rating || '-' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Section Cards -->
          <MainCard v-for="section in displaySections" :key="'mgr-' + section.id">
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-lg font-semibold text-brand-dark">{{ section.name }}</h3>
                  <p v-if="section.description" class="text-sm text-brand-light mt-1">{{ section.description }}</p>
                </div>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700">
                  Weight: {{ section.weight }}%
                </span>
              </div>

              <!-- Show employee's self-assessment for reference -->
              <div v-if="getResponseForSection(section.id)?.self_rating" class="p-3 bg-yellow-50 border border-yellow-100 rounded-lg">
                <p class="text-xs font-medium text-yellow-700 mb-1">Employee Self-Rating</p>
                <div class="flex items-center gap-2">
                  <span class="text-sm font-bold text-yellow-800">{{ getResponseForSection(section.id)?.self_rating }}/5</span>
                  <span class="text-xs text-yellow-600">{{ getRatingLabel(getResponseForSection(section.id)?.self_rating)?.label }}</span>
                </div>
                <p v-if="getResponseForSection(section.id)?.self_comments" class="text-xs text-yellow-700 mt-1">
                  "{{ getResponseForSection(section.id)?.self_comments }}"
                </p>
              </div>

              <!-- Editable Form -->
              <template v-if="canSubmitManagerAssessment">
                <div>
                  <label class="block text-sm font-medium text-brand-dark mb-2">Manager Rating</label>
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
                    {{ managerAssessmentForm[section.id]?.rating ? getRatingLabel(managerAssessmentForm[section.id].rating)?.label : 'Select a rating (1-5)' }}
                  </p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-brand-dark mb-2">Manager Comments (Optional)</label>
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
                  <p class="text-sm font-medium text-brand-dark mb-1">Manager Rating</p>
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
                    <span class="text-sm font-medium" :class="getRatingLabel(getResponseForSection(section.id)?.manager_rating)?.color">
                      {{ getRatingLabel(getResponseForSection(section.id)?.manager_rating)?.label }}
                    </span>
                  </div>
                  <p v-if="getResponseForSection(section.id)?.manager_comments" class="text-sm text-brand-light mt-2 p-3 bg-gray-50 rounded-lg">
                    {{ getResponseForSection(section.id)?.manager_comments }}
                  </p>
                </div>
                <div v-else class="text-sm text-brand-light italic">
                  No manager assessment submitted yet
                </div>
              </template>
            </div>
          </MainCard>

          <!-- Final Rating Input (Manager) -->
          <MainCard v-if="canSubmitManagerAssessment">
            <div class="space-y-4">
              <h3 class="text-lg font-semibold text-brand-dark">Overall Final Rating</h3>
              <p class="text-sm text-brand-light">Provide an overall rating for this employee (1.00 - 5.00)</p>
              <div class="flex items-center gap-4">
                <input
                  v-model.number="managerFinalRating"
                  type="number"
                  min="1"
                  max="5"
                  step="0.01"
                  class="w-32 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent text-center text-lg font-bold"
                  placeholder="0.00"
                />
                <span
                  v-if="managerFinalRating"
                  class="px-3 py-1 text-sm font-medium rounded-full border"
                  :class="getRatingLabel(managerFinalRating)?.bg"
                >
                  {{ getRatingLabel(managerFinalRating)?.label }}
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
        <div v-if="sectionsLoading" class="flex justify-center items-center py-12">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"></div>
        </div>

        <template v-else>
          <!-- Side-by-side comparison -->
          <MainCard v-for="section in displaySections" :key="'cal-' + section.id">
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-lg font-semibold text-brand-dark">{{ section.name }}</h3>
                  <p v-if="section.description" class="text-sm text-brand-light mt-1">{{ section.description }}</p>
                </div>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700">
                  Weight: {{ section.weight }}%
                </span>
              </div>

              <!-- Comparison Grid -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Self Rating -->
                <div class="p-3 bg-yellow-50 border border-yellow-100 rounded-lg">
                  <p class="text-xs font-medium text-yellow-700 mb-2">Self Assessment</p>
                  <div v-if="getResponseForSection(section.id)?.self_rating" class="flex items-center gap-2">
                    <span class="text-lg font-bold text-yellow-800">{{ getResponseForSection(section.id)?.self_rating }}/5</span>
                    <span class="text-xs text-yellow-600">{{ getRatingLabel(getResponseForSection(section.id)?.self_rating)?.label }}</span>
                  </div>
                  <p v-else class="text-xs text-yellow-600 italic">Not submitted</p>
                  <p v-if="getResponseForSection(section.id)?.self_comments" class="text-xs text-yellow-700 mt-2">
                    "{{ getResponseForSection(section.id)?.self_comments }}"
                  </p>
                </div>

                <!-- Manager Rating -->
                <div class="p-3 bg-blue-50 border border-blue-100 rounded-lg">
                  <p class="text-xs font-medium text-blue-700 mb-2">Manager Assessment</p>
                  <div v-if="getResponseForSection(section.id)?.manager_rating" class="flex items-center gap-2">
                    <span class="text-lg font-bold text-blue-800">{{ getResponseForSection(section.id)?.manager_rating }}/5</span>
                    <span class="text-xs text-blue-600">{{ getRatingLabel(getResponseForSection(section.id)?.manager_rating)?.label }}</span>
                  </div>
                  <p v-else class="text-xs text-blue-600 italic">Not submitted</p>
                  <p v-if="getResponseForSection(section.id)?.manager_comments" class="text-xs text-blue-700 mt-2">
                    "{{ getResponseForSection(section.id)?.manager_comments }}"
                  </p>
                </div>
              </div>

              <!-- Calibration Rating (optional per section) -->
              <div v-if="canCalibrate">
                <label class="block text-sm font-medium text-brand-dark mb-2">Calibrated Rating (Optional)</label>
                <div class="flex gap-2">
                  <button
                    v-for="n in 5"
                    :key="n"
                    type="button"
                    @click="calibrationForm[section.id] = { rating: calibrationForm[section.id]?.rating === n ? null : n }"
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
                <p class="text-sm font-medium text-brand-dark mb-1">Calibrated Rating</p>
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

          <!-- Final Rating & Label (Calibration) -->
          <MainCard v-if="canCalibrate">
            <div class="space-y-4">
              <h3 class="text-lg font-semibold text-brand-dark">Final Rating & Label</h3>
              <p class="text-sm text-brand-light">Set the final calibrated rating and performance label for this review.</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-brand-dark mb-2">Final Rating (Required)</label>
                  <input
                    v-model.number="calibrationFinalRating"
                    type="number"
                    min="1"
                    max="5"
                    step="0.01"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent text-lg font-bold"
                    placeholder="0.00"
                  />
                  <span
                    v-if="calibrationFinalRating"
                    class="px-3 py-1 text-xs font-medium rounded-full border mt-2 inline-block"
                    :class="getRatingLabel(calibrationFinalRating)?.bg"
                  >
                    {{ getRatingLabel(calibrationFinalRating)?.label }}
                  </span>
                </div>
                <div>
                  <label class="block text-sm font-medium text-brand-dark mb-2">Performance Label</label>
                  <select
                    v-model="calibrationFinalLabel"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                  >
                    <option value="">Select label...</option>
                    <option value="Outstanding">Outstanding</option>
                    <option value="Exceeds Expectations">Exceeds Expectations</option>
                    <option value="Meets Expectations">Meets Expectations</option>
                    <option value="Needs Improvement">Needs Improvement</option>
                    <option value="Unsatisfactory">Unsatisfactory</option>
                  </select>
                </div>
              </div>
            </div>
          </MainCard>

          <!-- Submit Button -->
          <div v-if="canCalibrate" class="flex justify-end">
            <button
              @click="openConfirmModal('calibrate')"
              :disabled="!isCalibrationValid || submitting"
              class="px-6 py-3 rounded-lg font-semibold flex items-center gap-2 transition-all"
              :class="
                isCalibrationValid && !submitting
                  ? 'bg-purple-600 text-white hover:bg-purple-700'
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              "
            >
              <ScaleIcon class="w-4 h-4" />
              Finalize Calibration
            </button>
          </div>

          <!-- Readonly final result for completed reviews -->
          <MainCard v-if="reviewStatus === 'completed' && review.final_rating">
            <div class="text-center py-4">
              <CheckCircleIcon class="w-12 h-12 text-emerald-500 mx-auto mb-3" />
              <p class="text-sm text-brand-light mb-1">Final Calibrated Rating</p>
              <p class="text-4xl font-bold" :class="getRatingLabel(review.final_rating)?.color">
                {{ Number(review.final_rating).toFixed(2) }}
              </p>
              <span
                class="px-4 py-1 text-sm font-medium rounded-full border mt-2 inline-block"
                :class="getRatingLabel(review.final_rating)?.bg"
              >
                {{ review.final_rating_label || getRatingLabel(review.final_rating)?.label }}
              </span>
              <p v-if="review.calibrator" class="text-sm text-brand-light mt-3">
                Calibrated by {{ review.calibrator.name }} on {{ formatDate(review.calibrated_at) }}
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
```

**Step 2: Verify the app compiles**

Run: `cd team-sync-fe && bun run build` (or check dev server for errors)
Expected: No compilation errors.

**Step 3: Commit**

```bash
git add team-sync-fe/src/views/admin/performance/ReviewDetail.vue
git commit -m "feat: implement ReviewDetail.vue with tab-based layout for performance assessments"
```

---

### Task 4: Manual Testing Checklist

**Step 1: Seed the data**

Run:
```bash
docker exec -it teamsync-api php artisan db:seed --class=PerformanceReviewSectionSeeder
docker exec -it teamsync-api php artisan db:seed --class=PerformanceDataSeeder
```

**Step 2: Test as Employee (agung@teamsync.com / teamsync)**

- Navigate to My Reviews
- Verify review with status "Pending Self-Assessment" appears
- Click to open ReviewDetail
- Verify Overview tab shows employee info, reviewer, cycle, deadlines
- Verify action banner "Action Required: Self Assessment" appears
- Switch to Self Assessment tab
- Verify 5 sections appear with rating buttons and comment fields
- Fill in ratings and submit
- Verify confirmation modal appears
- Confirm and verify status changes to "Pending Manager"

**Step 3: Test as Manager (yudhis@teamsync.com / teamsync)**

- Navigate to Team Reviews
- Click on a review with "Pending Manager" status
- Verify Manager Assessment tab shows employee's self-ratings for reference
- Fill in manager ratings and final rating
- Submit and verify status changes to "Pending Calibration"

**Step 4: Test as HR (tasyia@teamsync.com / teamsync)**

- Navigate to a review with "Pending Calibration" status
- Verify Calibration tab shows side-by-side self/manager ratings
- Set final rating and label
- Submit and verify status changes to "Completed"

**Step 5: Test completed review**

- Open a completed review
- Verify all tabs show readonly data
- Verify final rating and calibrator info displayed
