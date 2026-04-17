<script setup lang="ts">
import { shallowRef } from "vue";
import { useAuthStore } from "@/stores/auth";
import { storeToRefs } from "pinia";
import Input from "@/components/common/form/Input.vue";
import Alert from "@/components/common/Alert.vue";
import { AtSign, BuildingIcon } from "lucide-vue-next";
import { RouterLink } from "vue-router";

const authStore = useAuthStore();
const { loading, error, success } = storeToRefs(authStore);
const email = shallowRef("");

// Thin route-level component: one form, one request, explicit store action.

const handleSubmit = async () => {
  await authStore.forgotPassword({ email: email.value });
};
</script>

<template>
  <div class="w-full lg:w-1/2 flex items-center justify-center" style="padding: 20px">
    <div class="w-full max-w-md space-y-8">
      <div class="flex items-center gap-4">
        <div class="w-16 h-16 relative flex items-center justify-center">
          <div class="w-16 h-16 absolute bg-gradient-to-br from-primary-100 to-primary-200 rounded-full"></div>
          <div class="w-10 h-10 absolute bg-gradient-to-br from-primary-500 to-primary-600 rounded-full opacity-90"></div>
          <BuildingIcon class="w-5 h-5 text-white relative z-10" />
        </div>
        <div>
          <h1 class="text-brand-dark text-lg font-bold">Reset Password</h1>
          <p class="text-brand-dark text-xs font-normal">We'll send you a reset link</p>
        </div>
      </div>

      <Alert
        v-if="typeof success === 'string'"
        type="success"
        title="Email Sent"
        :message="success"
        :show="true"
      />

      <Alert
        v-if="typeof error === 'string'"
        type="danger"
        title="Request Failed"
        :message="error"
        :show="true"
      />

      <form class="space-y-6" @submit.prevent="handleSubmit">
        <Input
          id="email"
          name="email"
          type="email"
          v-model="email"
          label="Email Address"
          placeholder="Enter your email"
          :required="true"
          :error="typeof error === 'object' ? error?.email?.join(', ') : ''"
        >
          <template #icon>
            <AtSign class="h-5 w-5 text-gray-400" />
          </template>
        </Input>

        <button
          type="submit"
          class="btn-primary rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2 w-full justify-center bg-gradient-to-l from-[#0c51d9] via-[#6f96e3] to-[#0c51d9] shadow-[inset_-2px_2px_1px_0_#6197ff,inset_2px_2px_1px_0_rgba(97,151,255,0.55)] text-white font-plus-jakarta-sans text-[14px] font-semibold cursor-pointer"
          :disabled="loading"
        >
          {{ loading ? 'Sending...' : 'Send Reset Link' }}
        </button>
      </form>

      <div class="text-center">
        <RouterLink
          :to="{ name: 'login' }"
          class="hover:brightness-110 transition-all duration-300 text-primary-600"
        >
          Back to Sign In
        </RouterLink>
      </div>
    </div>
  </div>
</template>
