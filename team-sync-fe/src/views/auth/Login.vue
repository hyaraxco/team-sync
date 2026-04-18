<script setup lang="ts">
import { computed, ref } from "vue";
import { useAuthStore } from "@/stores/auth";
import Alert from "@/components/common/Alert.vue";
import Input from "@/components/common/form/Input.vue";
import { AtSign, Lock, ArrowRight, UserCircle2 } from "lucide-vue-next";
import { storeToRefs } from "pinia";
import { RouterLink } from "vue-router";

const authStore = useAuthStore();
const { loading, error } = storeToRefs(authStore);
const { login } = authStore;

const form = ref({
  email: "",
  password: "",
});

const fieldErrors = computed(() =>
  error.value && typeof error.value === "object" ? error.value : {}
);

const hasAuthError = computed(() => error.value === "Unauthorized");

const handleSubmit = async () => {
  await login(form.value);

  if (error.value === "Unauthorized") {
    form.value.password = "";
  }
};
</script>

<template>
  <div class="w-full max-w-[420px] mx-auto animate-fadeIn px-4">
    <div class="text-center mb-8">
      <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-tr from-blue-600 to-blue-500 text-white mb-6 shadow-lg shadow-blue-500/30 ring-4 ring-blue-50">
        <UserCircle2 class="w-7 h-7" aria-hidden="true" stroke-width="1.5" />
      </div>
      <h1 class="text-3xl font-bold text-gray-900 tracking-tight">
        Welcome back
      </h1>
      <p class="mt-2 text-[15px] text-gray-500">
        Sign in to access your TeamSync dashboard
      </p>
    </div>

    <!-- Error Alert -->
    <Transition name="fade-slide">
      <Alert
        v-if="hasAuthError"
        type="danger"
        title="Invalid Credentials"
        message="The email or password you entered is incorrect. Please try again."
        :show="hasAuthError"
      />
    </Transition>

    <!-- Form Card -->
    <div class="bg-white/80 backdrop-blur-xl rounded-2xl border border-gray-200/60 p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
      <form class="space-y-5" @submit.prevent="handleSubmit" novalidate>
        <Input
          id="email"
          name="email"
          type="email"
          autocomplete="email"
          v-model="form.email"
          label="Email Address"
          placeholder="name@company.com"
          :required="true"
          :error="fieldErrors?.email?.join(', ')"
        >
          <template #icon>
            <AtSign class="h-5 w-5" aria-hidden="true" stroke-width="1.5" />
          </template>
        </Input>

        <Input
          id="password"
          name="password"
          type="password"
          autocomplete="current-password"
          v-model="form.password"
          label="Password"
          placeholder="Enter your password"
          :required="true"
          :error="fieldErrors?.password?.join(', ')"
        >
          <template #icon>
            <Lock class="h-5 w-5" aria-hidden="true" stroke-width="1.5" />
          </template>
        </Input>

        <!-- Remember Me & Forgot Password -->
        <div class="flex items-center justify-between pt-1">
          <label class="flex cursor-pointer items-center gap-2.5 group">
            <div class="relative flex items-center justify-center">
              <input
                type="checkbox"
                id="remember"
                name="remember"
                class="peer h-4 w-4 appearance-none rounded border border-gray-300 bg-white outline-none transition-all checked:bg-[#0C51D9] checked:border-[#0C51D9] group-hover:border-[#0C51D9] focus-visible:ring-4 focus-visible:ring-[#0C51D9]/20"
              />
              <svg 
                class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" 
                viewBox="0 0 14 14" 
                fill="none" 
                xmlns="http://www.w3.org/2000/svg"
              >
                <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <span class="text-sm font-medium text-gray-600 group-hover:text-gray-900 transition-colors">Remember me</span>
          </label>

          <RouterLink
            :to="{ name: 'forgot-password' }"
            class="text-sm font-semibold text-[#0C51D9] hover:text-[#083da6] transition-colors"
          >
            Forgot password?
          </RouterLink>
        </div>

        <!-- Submit Button -->
        <button
          type="submit"
          data-testid="login-submit"
          class="group relative w-full flex items-center justify-center gap-2 rounded-xl bg-[#0C51D9] px-4 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:bg-[#083da6] hover:shadow-md focus:outline-none focus:ring-4 focus:ring-[#0C51D9]/20 disabled:cursor-not-allowed disabled:opacity-70 disabled:hover:bg-[#0C51D9] disabled:hover:shadow-sm overflow-hidden mt-2"
          :disabled="loading"
        >
          <span class="relative z-10 flex items-center gap-2">
            {{ loading ? "Signing in…" : "Sign in" }}
            <ArrowRight v-if="!loading" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
            <svg v-else class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </span>
          <div v-if="!loading" class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-out"></div>
        </button>
      </form>
    </div>

    <!-- Help Section -->
    <div class="mt-8 text-center text-sm font-medium text-gray-500">
      Need help? Contact your <span class="text-gray-900 cursor-pointer hover:underline underline-offset-4 decoration-gray-300">HR administrator</span>
    </div>
  </div>
</template>

<style>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fadeIn {
  animation: fadeIn 0.5s ease-out forwards;
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
