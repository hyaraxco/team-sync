<script setup lang="ts">
import { onMounted, watch } from "vue";
import { useEmployeeStore } from "@/stores/employee";
import { useRouter } from "vue-router";
import { User } from "lucide-vue-next";
import { getTimeAgo } from "@/utils/dateUtils";
import { storeToRefs } from "pinia";
import EmptyState from "@/components/common/EmptyState.vue";

const props = defineProps({
  searchParams: {
    type: Object,
    default: () => ({}),
  },
});

const employeeStore = useEmployeeStore();
const { latestEmployees, loadingLatest } = storeToRefs(employeeStore);

const router = useRouter();

const loadEmployees = (params = {}) => {
  employeeStore.fetchLatestEmployees(params);
};

onMounted(() => {
  loadEmployees();
});

watch(
  () => props.searchParams,
  (newParams) => {
    loadEmployees(newParams);
  },
  { deep: true }
);

const goToEmployeeDetail = (id: number) => {
  router.push({ name: "admin.employees.detail", params: { id } });
};
</script>

<template>
  <!-- Latest Employees -->
  <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-4 sm:p-5">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-brand-dark text-lg font-bold">Latest Employees</h3>
    </div>

    <!-- Loading State -->
    <div v-if="loadingLatest" class="space-y-4">
      <div
        v-for="i in 5"
        :key="i"
        class="flex items-center gap-3 animate-pulse"
      >
        <div class="w-16 h-16 bg-gray-200 rounded-full"></div>
        <div class="flex-1">
          <div class="h-4 bg-gray-200 rounded w-1/3 mb-2"></div>
          <div class="h-3 bg-gray-200 rounded w-1/2"></div>
        </div>
        <div class="w-20 h-10 bg-gray-200 rounded-xl"></div>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else-if="!latestEmployees || latestEmployees.length === 0"
      icon="Users"
      title="No employees found"
    />

    <!-- Employee List -->
    <div v-else class="space-y-4">
      <div
        v-for="employee in latestEmployees"
        :key="employee.id"
        class="flex flex-col sm:flex-row sm:items-center gap-3"
      >
        <img
          :src="employee.user?.profile_photo"
          :alt="employee.user?.name"
          class="w-12 h-12 sm:w-16 sm:h-16 rounded-full object-cover"
          v-if="employee?.user?.profile_photo"
        />
        <div
          class="w-12 h-12 sm:w-20 sm:h-20 rounded-full flex items-center justify-center bg-gray-100"
          v-else
        >
          <User class="w-5 h-5 text-gray-400" />
        </div>
        <div class="flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <p class="text-brand-dark text-base sm:text-lg font-bold">
              {{ employee.user?.name || "Unknown" }}
            </p>
            <span
              v-if="employee.job_information?.employment_type"
              class="px-2 py-1 rounded-md text-xs font-semibold capitalize bg-[#EBF8FF] text-[#1E40AF]"
            >
              {{ employee.job_information.employment_type }}
            </span>
          </div>
          <p class="text-brand-dark text-xs sm:text-sm font-normal mt-1">
            {{ employee.job_information?.job_title || "N/A" }} •
            {{ getTimeAgo(employee.created_at) }}
          </p>
        </div>
        <button
          @click="goToEmployeeDetail(employee.id)"
          class="btn-details w-full sm:w-auto border border-[#DCDEDD] rounded-xl hover:ring-2 hover:ring-[#0C51D9] hover:text-[#0C51D9] transition-all duration-300 py-2 sm:py-[14px] px-3 sm:px-5 flex items-center justify-center"
        >
          <span class="text-brand-dark text-sm sm:text-base font-medium">Details</span>
        </button>
      </div>
    </div>
  </div>
</template>
