<script setup lang="ts">
import { computed } from "vue";
import { useRoute, RouterLink } from "vue-router";
import { BuildingIcon, CheckCircle2, MailWarning } from "lucide-vue-next";

const route = useRoute();
const status = computed(() => String(route.query.status || "pending"));
const success = computed(() => status.value === "success");
</script>

<template>
  <div class="w-full lg:w-1/2 flex items-center justify-center" style="padding: 20px">
    <div class="w-full max-w-md space-y-8 text-center">
      <div class="flex items-center justify-center gap-4">
        <div class="w-16 h-16 relative flex items-center justify-center">
          <div class="w-16 h-16 absolute bg-gradient-to-br from-primary-100 to-primary-200 rounded-full"></div>
          <div class="w-10 h-10 absolute bg-gradient-to-br from-primary-500 to-primary-600 rounded-full opacity-90"></div>
          <BuildingIcon class="w-5 h-5 text-white relative z-10" />
        </div>
      </div>

      <div class="space-y-4">
        <component :is="success ? CheckCircle2 : MailWarning" :class="success ? 'text-green-600' : 'text-amber-500'" class="w-16 h-16 mx-auto" />
        <div>
          <h1 class="text-brand-dark text-2xl font-bold">
            {{ success ? 'Email Verified' : 'Verification Link Invalid' }}
          </h1>
          <p class="text-brand-dark text-sm font-normal mt-2">
            {{ success
              ? 'Your email address has been verified successfully. You can now sign in and continue using TeamSync.'
              : 'The verification link is invalid or has expired. Please request a new verification email from your account.' }}
          </p>
        </div>
      </div>

      <RouterLink
        :to="{ name: 'login' }"
        class="inline-flex items-center justify-center rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-6 py-3 bg-gradient-to-l from-[#0c51d9] via-[#6f96e3] to-[#0c51d9] shadow-[inset_-2px_2px_1px_0_#6197ff,inset_2px_2px_1px_0_rgba(97,151,255,0.55)] text-white font-plus-jakarta-sans text-[14px] font-semibold"
      >
        Go to Sign In
      </RouterLink>
    </div>
  </div>
</template>
