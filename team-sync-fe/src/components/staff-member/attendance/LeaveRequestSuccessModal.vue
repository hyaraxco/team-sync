<script setup>
import { CheckCircle, X, Calendar, Clock } from "lucide-vue-next";
import { formatLeaveType } from "@/utils/attendanceUtils";
import { formatDateShort } from "@/utils/dateUtils";

const props = defineProps({
  show: {
    type: Boolean,
    required: true,
  },
  leaveData: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["close"]);

const handleClose = () => {
  emit("close");
};
</script>

<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 backdrop-blur-sm bg-black/20 z-50 flex items-center justify-center"
      @click.self="handleClose"
    >
      <div
        class="bg-white rounded-[20px] border border-[#DCDEDD] w-full max-w-md mx-4 overflow-hidden animate-scale-in"
      >
        <!-- Success Icon Header -->
        <div class="bg-gradient-to-br from-green-50 to-green-100 p-8 text-center">
          <div
            class="w-20 h-20 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center mx-auto mb-4 animate-bounce-in"
          >
            <CheckCircle class="w-10 h-10 text-white" />
          </div>
          <h3 class="text-brand-dark text-2xl font-bold mb-2">
            Request Submitted!
          </h3>
          <p class="text-brand-light text-base">
            Your leave request has been successfully submitted
          </p>
        </div>

        <!-- Request Details -->
        <div class="p-6 space-y-4" v-if="leaveData">
          <div
            class="bg-white border border-[#DCDEDD] rounded-[16px] p-4 space-y-3"
          >
            <!-- Leave Type -->
            <div class="flex items-center justify-between">
              <span class="text-brand-dark text-sm font-medium">Leave Type</span>
              <span class="text-brand-dark text-base font-bold">
                {{ formatLeaveType(leaveData.leave_type) }}
              </span>
            </div>

            <!-- Duration -->
            <div class="flex items-center justify-between">
              <span class="text-brand-dark text-sm font-medium">Duration</span>
              <div class="flex items-center gap-2">
                <Clock class="w-4 h-4 text-blue-600" />
                <span class="text-brand-dark text-base font-semibold">
                  {{ leaveData.total_days }}
                  {{ leaveData.total_days === 1 ? "day" : "days" }}
                </span>
              </div>
            </div>

            <!-- Date Range -->
            <div class="flex items-center justify-between">
              <span class="text-brand-dark text-sm font-medium">Period</span>
              <div class="flex items-center gap-2">
                <Calendar class="w-4 h-4 text-blue-600" />
                <span class="text-brand-dark text-base font-semibold">
                  {{ formatDateShort(leaveData.start_date) }} -
                  {{ formatDateShort(leaveData.end_date) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Info Message -->
          <div
            class="bg-blue-50 border border-blue-200 rounded-[12px] p-4 flex gap-3"
          >
            <div
              class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0"
            >
              <span class="text-blue-600 text-lg">ℹ️</span>
            </div>
            <p class="text-brand-dark text-sm leading-relaxed">
              You will receive a notification once your request has been reviewed
              by your manager.
            </p>
          </div>

          <!-- Close Button -->
          <button
            @click="handleClose"
            class="btn-primary w-full rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-6 py-3 flex items-center justify-center gap-2"
          >
            <span class="text-brand-white text-base font-semibold">Got it!</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@keyframes scale-in {
  0% {
    transform: scale(0.9);
    opacity: 0;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

@keyframes bounce-in {
  0% {
    transform: scale(0);
  }
  50% {
    transform: scale(1.1);
  }
  100% {
    transform: scale(1);
  }
}

.animate-scale-in {
  animation: scale-in 0.3s ease-out;
}

.animate-bounce-in {
  animation: bounce-in 0.5s ease-out;
}
</style>
