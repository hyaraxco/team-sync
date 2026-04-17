<script setup>
import { X, AlertTriangle } from "lucide-vue-next";

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: "Confirm Action",
  },
  message: {
    type: String,
    default: "Are you sure you want to proceed?",
  },
  confirmText: {
    type: String,
    default: "Confirm",
  },
  cancelText: {
    type: String,
    default: "Cancel",
  },
  loading: {
    type: Boolean,
    default: false,
  },
  type: {
    type: String,
    default: "danger", // danger, warning, info
  },
});

const emit = defineEmits(["confirm", "cancel"]);

const handleConfirm = () => {
  emit("confirm");
};

const handleCancel = () => {
  emit("cancel");
};
</script>

<template>
  <div
    v-if="show"
    class="fixed inset-0 backdrop-blur-sm bg-black/30 z-50 flex items-center justify-center p-4"
    @click.self="handleCancel"
  >
    <div
      class="bg-white rounded-[20px] border border-[#DCDEDD] w-full max-w-md overflow-hidden"
    >
      <!-- Header -->
      <div class="p-6 border-b border-[#DCDEDD]">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-12 h-12 rounded-[12px] flex items-center justify-center"
              :class="{
                'bg-red-50': type === 'danger',
                'bg-yellow-50': type === 'warning',
                'bg-blue-50': type === 'info',
              }"
            >
              <AlertTriangle
                class="w-6 h-6"
                :class="{
                  'text-red-600': type === 'danger',
                  'text-yellow-600': type === 'warning',
                  'text-blue-600': type === 'info',
                }"
              />
            </div>
            <div>
              <h3 class="text-brand-dark text-xl font-bold">{{ title }}</h3>
            </div>
          </div>
          <button
            type="button"
            @click="handleCancel"
            class="w-10 h-10 rounded-full border border-[#DCDEDD] flex items-center justify-center hover:border-[#0C51D9] hover:border-2 transition-all duration-200"
          >
            <X class="w-5 h-5 text-gray-600" />
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6">
        <p class="text-brand-light text-base">{{ message }}</p>
      </div>

      <!-- Actions -->
      <div class="p-6 border-t border-[#DCDEDD] flex gap-3 justify-end">
        <button
          type="button"
          @click="handleCancel"
          :disabled="loading"
          class="border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-6 py-3"
        >
          <span class="text-brand-dark text-base font-semibold">{{
            cancelText
          }}</span>
        </button>
        <button
          type="button"
          @click="handleConfirm"
          :disabled="loading"
          class="rounded-[8px] border hover:brightness-110 focus:ring-2 transition-all duration-300 px-6 py-3 flex items-center gap-2"
          :class="{
            'border-[#A02121] bg-gradient-to-r from-red-500 to-red-600 shadow-lg focus:ring-red-500':
              type === 'danger',
            'border-[#A07621] bg-gradient-to-r from-yellow-500 to-yellow-600 shadow-lg focus:ring-yellow-500':
              type === 'warning',
            'border-[#2151A0] blue-gradient blue-btn-shadow focus:ring-[#0C51D9]':
              type === 'info',
          }"
        >
          <span class="text-brand-white text-base font-semibold">
            {{ loading ? "Processing..." : confirmText }}
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
