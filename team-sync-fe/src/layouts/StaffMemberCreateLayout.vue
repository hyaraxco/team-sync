<script setup>
import Stepper from "@/components/admin/staff-member/create/Stepper.vue";
import Header from "@/components/admin/Header.vue";
import {
  BellIcon,
  SettingsIcon,
  ChevronDownIcon,
  MessageCircleIcon,
  ArrowLeft,
  UserIcon,
} from "lucide-vue-next";
import { useAuthStore } from "@/stores/auth";
import { storeToRefs } from "pinia";
import { ref, provide, computed, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import { DEFAULT_AVATAR } from "@/helpers/format";

const authStore = useAuthStore();
const { user } = storeToRefs(authStore);

const router = useRouter();
const route = useRoute();

// Check if editing or creating
const isEditing = computed(() => route.name === "admin.staffMembers.edit");

// Main content ref for scrolling
const mainContentRef = ref(null);

// Step management
const currentStep = ref(1);
const totalSteps = 4;

const scrollToTop = () => {
  if (mainContentRef.value) {
    mainContentRef.value.scrollTo({
      top: 0,
      behavior: "smooth",
    });
  }
};

const goToStep = (step) => {
  if (step >= 1 && step <= totalSteps) {
    currentStep.value = step;
    scrollToTop();
  }
};

const nextStep = () => {
  if (currentStep.value < totalSteps) {
    currentStep.value++;
    scrollToTop();
  }
};

const previousStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--;
    scrollToTop();
  } else {
    router.push({ name: "admin.staffMembers" });
  }
};

// Provide step management to child components
provide("currentStep", currentStep);
provide("totalSteps", totalSteps);
provide("goToStep", goToStep);
provide("nextStep", nextStep);
provide("previousStep", previousStep);

const getStepTitle = () => {
  switch (currentStep.value) {
    case 1:
      return "Personal Information";
    case 2:
      return "Job Information";
    case 3:
      return "Emergency Contact";
    case 4:
      return "Review & Submit";
    default:
      return "Personal Information";
  }
};

// Reset step when route changes
watch(
  () => route.params.id,
  () => {
    currentStep.value = 1;
    scrollToTop();
  },
);
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-[#F8FAFC]">
    <Stepper :current-step="currentStep" :is-editing="isEditing" />

    <!-- Main Content -->
    <div class="flex-1 flex flex-col">
      <!-- Top Navbar -->
      <header class="page-header bg-white border-b border-[#DCDEDD] px-4 md:px-5 py-3 md:py-4">
        <div class="flex items-start md:items-center justify-between gap-4">
          <div class="flex items-center gap-4">
            <button
              @click="previousStep"
              class="border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-3 py-2 flex items-center gap-2"
            >
              <ArrowLeft class="w-4 h-4 text-gray-600" />
              <span class="text-brand-dark text-base font-semibold">Back</span>
            </button>
            <div>
              <h2 class="text-brand-dark text-xl md:text-2xl font-extrabold">
                {{ isEditing ? "Edit Staff Member" : "Add New Staff Member" }}
              </h2>
              <p class="text-brand-light text-sm font-normal mt-1">
                Step {{ currentStep }} of {{ totalSteps }}: {{ getStepTitle() }}
              </p>
            </div>
          </div>

          <div class="hidden xl:flex items-center gap-4">
            <!-- Action Buttons -->
            <div class="flex items-center gap-3">
              <button
                class="w-14 h-14 rounded-full border border-[#DCDEDD] flex items-center justify-center hover:border-[#0C51D9] hover:border-2 transition-all duration-200"
              >
                <BellIcon class="w-5 h-5 text-gray-600" />
              </button>
              <button
                class="w-14 h-14 rounded-full border border-[#DCDEDD] flex items-center justify-center hover:border-[#0C51D9] hover:border-2 transition-all duration-200"
              >
                <MessageCircleIcon class="w-5 h-5 text-gray-600" />
              </button>
              <button
                class="w-14 h-14 rounded-full border border-[#DCDEDD] flex items-center justify-center hover:border-[#0C51D9] hover:border-2 transition-all duration-200"
              >
                <SettingsIcon class="w-5 h-5 text-gray-600" />
              </button>
            </div>

            <!-- Divider -->
            <div class="w-px h-8 bg-[#DCDEDD] mx-5"></div>

            <!-- User Profile -->
            <div class="flex items-center gap-3">
              <img
                :src="user?.profile_photo || DEFAULT_AVATAR"
                alt="User Avatar"
                class="w-12 h-12 rounded-full object-cover"
              />
              <div class="text-left">
                <p class="text-brand-dark text-base font-semibold">
                  {{ user?.name }}
                </p>
                <p class="text-brand-dark text-base font-normal leading-7">
                  {{ user?.roles.join(", ") }}
                </p>
              </div>
              <ChevronDownIcon class="w-4 h-4 text-gray-400" />
            </div>
          </div>
        </div>
      </header>
      <!-- Dashboard Content -->
      <main ref="mainContentRef" class="main-content flex-1 overflow-auto p-4 md:p-5">
        <RouterView />
      </main>
    </div>
  </div>
</template>
