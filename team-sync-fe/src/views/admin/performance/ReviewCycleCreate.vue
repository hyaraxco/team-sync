<script setup>
import { ref, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import { Calendar, ArrowLeft, Layout } from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";
import Alert from "@/components/common/Alert.vue";
import { usePerformanceReviewStore } from "@/stores/performanceReview";
import { useToast } from "@/composables/useToast";
import { storeToRefs } from "pinia";

const router = useRouter();
const reviewStore = usePerformanceReviewStore();
const toast = useToast();
const { templates, templatesLoading, cyclesLoading } = storeToRefs(reviewStore);

const errorMessage = ref("");
const submitting = ref(false);

const formData = ref({
  name: "",
  cycle_type: "quarterly",
  start_date: "",
  end_date: "",
  review_period_start: "",
  review_period_end: "",
  self_assessment_deadline: "",
  manager_assessment_deadline: "",
  calibration_deadline: "",
  template_id: "",
});

const defaultTemplateId = computed(() => {
  const defaultTpl = templates.value?.find((t) => t.is_default);
  return defaultTpl?.id || "";
});

const isFormValid = computed(() => {
  return (
    !!formData.value.name.trim() &&
    !!formData.value.cycle_type &&
    !!formData.value.start_date &&
    !!formData.value.end_date &&
    !!formData.value.review_period_start &&
    !!formData.value.review_period_end
  );
});

const goBack = () => {
  router.push({ name: "admin.performance.cycles" });
};

const parseErrorMessage = (error) => {
  const storeError = reviewStore.error;
  if (storeError && typeof storeError === "object") {
    return Object.values(storeError).flat().join(". ");
  }
  return storeError || error?.response?.data?.message || "Failed to create review cycle.";
};

const createCycle = async () => {
  if (!isFormValid.value || submitting.value) {
    return;
  }

  errorMessage.value = "";
  submitting.value = true;

  try {
    const payload = { ...formData.value };
    // Send template_id as integer or null
    payload.template_id = payload.template_id ? parseInt(payload.template_id) : null;

    await reviewStore.createCycle(payload);

    toast.success(
      "Review cycle created",
      `"${formData.value.name}" has been created successfully.`,
    );
    router.push({ name: "admin.performance.cycles" });
  } catch (error) {
    errorMessage.value = parseErrorMessage(error);
    toast.error("Failed to create cycle", errorMessage.value);
  } finally {
    submitting.value = false;
  }
};

onMounted(async () => {
  await reviewStore.fetchTemplates();
  if (!formData.value.template_id && defaultTemplateId.value) {
    formData.value.template_id = defaultTemplateId.value;
  }
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <button
        class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
        @click="goBack"
      >
        <ArrowLeft class="w-5 h-5 text-brand-dark" />
      </button>
      <div>
        <h1 class="text-3xl font-bold text-brand-dark">Create Review Cycle</h1>
        <p class="text-brand-light mt-1">
          Set up a new performance review cycle
        </p>
      </div>
    </div>

    <Alert
      v-if="errorMessage"
      type="danger"
      title="Unable to create review cycle"
      :message="errorMessage"
    />

    <!-- Form -->
    <MainCard>
      <form @submit.prevent="createCycle" class="space-y-6">
        <div>
          <label class="block text-sm font-medium text-brand-dark mb-2"
            >Cycle Name *</label
          >
          <input
            v-model="formData.name"
            type="text"
            required
            placeholder="e.g., Q1 2026 Performance Review"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-brand-dark mb-2"
            >Cycle Type *</label
          >
          <select
            v-model="formData.cycle_type"
            required
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
          >
            <option value="quarterly">Quarterly</option>
            <option value="semi_annual">Semi-Annual</option>
            <option value="annual">Annual</option>
            <option value="probation">Probation</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-brand-dark mb-2">
            <Layout class="w-4 h-4 inline-block mr-1 -mt-0.5" />
            Assessment Template
          </label>
          <select
            v-model="formData.template_id"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
          >
            <option value="">— Use default section weights —</option>
            <option
              v-for="tpl in templates"
              :key="tpl.id"
              :value="tpl.id"
            >
              {{ tpl.name }}{{ tpl.is_default ? ' ★ Default' : '' }}
              {{ tpl.sections_count ? `(${tpl.sections_count} sections)` : '' }}
            </option>
          </select>
          <p class="mt-1 text-xs text-brand-light">
            Templates define which sections are evaluated and their weight distribution.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium text-brand-dark mb-2"
              >Review Period Start *</label
            >
            <input
              v-model="formData.review_period_start"
              type="date"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-brand-dark mb-2"
              >Review Period End *</label
            >
            <input
              v-model="formData.review_period_end"
              type="date"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium text-brand-dark mb-2"
              >Cycle Start Date *</label
            >
            <input
              v-model="formData.start_date"
              type="date"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-brand-dark mb-2"
              >Cycle End Date *</label
            >
            <input
              v-model="formData.end_date"
              type="date"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <label class="block text-sm font-medium text-brand-dark mb-2"
              >Self-Assessment Deadline</label
            >
            <input
              v-model="formData.self_assessment_deadline"
              type="date"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-brand-dark mb-2"
              >Manager Assessment Deadline</label
            >
            <input
              v-model="formData.manager_assessment_deadline"
              type="date"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-brand-dark mb-2"
              >Calibration Deadline</label
            >
            <input
              v-model="formData.calibration_deadline"
              type="date"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
            />
          </div>
        </div>

        <div class="p-4 bg-blue-50 border border-blue-100 rounded-lg">
          <h3 class="text-sm font-semibold text-blue-900 mb-2">Reviewer Assignment Rules</h3>
          <p class="text-sm text-blue-800 mb-3">
            Reviewers will be automatically assigned when you generate reviews for this cycle based on the system rules.
          </p>
          <ul class="list-disc pl-5 text-sm text-blue-800 space-y-1">
            <li><strong>Staff members</strong> are reviewed by their Team Manager (same team preference).</li>
            <li><strong>Managers</strong> are reviewed by HR Admins.</li>
            <li>If no matching role is found, the reviewer will be left empty and can be manually assigned later.</li>
          </ul>
        </div>

        <div class="flex items-center gap-4 pt-6 border-t border-gray-200">
          <button
            type="submit"
            :disabled="!isFormValid || submitting"
            class="px-6 py-3 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ submitting ? "Creating..." : "Create Cycle" }}
          </button>
          <button
            type="button"
            :disabled="submitting"
            class="px-6 py-3 bg-gray-100 text-brand-dark rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-50"
            @click="goBack"
          >
            Cancel
          </button>
        </div>
      </form>
    </MainCard>
  </div>
</template>
