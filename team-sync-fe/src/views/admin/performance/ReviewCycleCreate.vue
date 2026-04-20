<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { Calendar, ArrowLeft } from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";

const router = useRouter();

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
});

const goBack = () => {
  router.push({ name: "admin.performance.cycles" });
};

const createCycle = async () => {
  // TODO: Implement cycle creation
  console.log("Creating cycle:", formData.value);
};
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

        <div class="flex items-center gap-4 pt-6 border-t border-gray-200">
          <button
            type="submit"
            class="px-6 py-3 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors"
          >
            Create Cycle
          </button>
          <button
            type="button"
            class="px-6 py-3 bg-gray-100 text-brand-dark rounded-lg hover:bg-gray-200 transition-colors"
            @click="goBack"
          >
            Cancel
          </button>
        </div>
      </form>
    </MainCard>
  </div>
</template>
