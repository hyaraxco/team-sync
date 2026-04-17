<script setup lang="ts">
import { useAuthStore } from "@/stores/auth";
import { ref } from "vue";
import Alert from "@/components/common/Alert.vue";
import Input from "@/components/common/form/Input.vue";
import { AtSign, BuildingIcon, Lock } from "lucide-vue-next";
import { storeToRefs } from "pinia";
import { RouterLink } from "vue-router";

const authStore = useAuthStore();
const { loading, error } = storeToRefs(authStore);
const { login } = authStore;

const form = ref({
  email: "",
  password: "",
});

const handleSubmit = async () => {
  await login(form.value);

  if (error.value === "Unauthorized") {
    form.value.password = null;
  }
};
</script>

<template>
  <!-- Right Side - Login Form -->
  <div
    class="w-full lg:w-1/2 flex items-center justify-center"
    style="padding: 20px"
  >
    <div class="w-full max-w-md space-y-8">
      <!-- Logo/Brand -->
      <div class="flex items-center gap-4">
        <div class="w-16 h-16 relative flex items-center justify-center">
          <!-- Background circle -->
          <div
            class="w-16 h-16 absolute bg-gradient-to-br from-primary-100 to-primary-200 rounded-full"
          ></div>
          <!-- Overlapping smaller circle -->
          <div
            class="w-10 h-10 absolute bg-gradient-to-br from-primary-500 to-primary-600 rounded-full opacity-90"
          ></div>
          <!-- Lucide icon -->
          <BuildingIcon class="w-5 h-5 text-white relative z-10" />
        </div>
        <div>
          <h1 class="text-brand-dark text-lg font-bold">Team Sync Pro</h1>
          <p class="text-brand-dark text-xs font-normal">HRIS Dashboard</p>
        </div>
      </div>

      <Alert
        type="danger"
        title="Invalid Credentials"
        message="The email or password you entered is incorrect. Please try again."
        :show="error === 'Unauthorized'"
      />

      <!-- Login Form -->
      <form class="space-y-6" @submit.prevent="handleSubmit">
        <!-- Email Field -->
        <Input
          id="email"
          name="email"
          type="email"
          v-model="form.email"
          label="Email Address"
          placeholder="Enter your email"
          :required="true"
          :error="error?.email?.join(', ')"
        >
          <template #icon>
            <AtSign class="h-5 w-5 text-gray-400" />
          </template>
        </Input>

        <!-- Password Field -->
        <Input
          id="password"
          name="password"
          type="password"
          v-model="form.password"
          label="Password"
          placeholder="Enter your password"
          :required="true"
          :error="error?.password?.join(', ')"
        >
          <template #icon>
            <Lock class="h-5 w-5 text-gray-400" />
          </template>
        </Input>

        <!-- Remember Me & Forgot Password -->
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <input
              type="checkbox"
              id="remember"
              name="remember"
              class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
            />
            <label for="remember" class="ml-2 text-gray-400">
              Remember me
            </label>
          </div>
          <RouterLink
            :to="{ name: 'forgot-password' }"
            class="hover:brightness-110 transition-all duration-300 text-primary-600"
          >
            Forgot password?
          </RouterLink>
        </div>

        <!-- Login Button -->
        <button
          type="submit"
          data-testid="login-submit"
          class="btn-primary rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2 w-full justify-center bg-gradient-to-l from-[#0c51d9] via-[#6f96e3] to-[#0c51d9] shadow-[inset_-2px_2px_1px_0_#6197ff,inset_2px_2px_1px_0_rgba(97,151,255,0.55)] text-white font-plus-jakarta-sans text-[14px] font-semibold cursor-pointer"
          :disabled="loading"
        >
          Sign In to Dashboard
        </button>
      </form>

      <!-- Divider -->
      <div class="divider-custom">
        <span>Or continue with</span>
      </div>

      <!-- Social Login -->
      <div class="grid grid-cols-2 gap-3">
        <button
          class="border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 flex items-center justify-center gap-2 px-4 py-3 w-full"
        >
          <svg class="w-5 h-5 text-gray-400" viewBox="0 0 24 24">
            <path
              fill="currentColor"
              d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
            />
            <path
              fill="currentColor"
              d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
            />
            <path
              fill="currentColor"
              d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
            />
            <path
              fill="currentColor"
              d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
            />
          </svg>
          <span
            class="ml-1 text-[#4b5563] font-plus-jakarta-sans text-[14px] font-semibold"
            >Google</span
          >
        </button>
        <button
          class="border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 flex items-center justify-center gap-2 px-4 py-3 w-full"
        >
          <svg
            class="w-5 h-5 text-gray-400"
            fill="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"
            />
          </svg>
          <span
            class="ml-1 text-[#4b5563] font-plus-jakarta-sans text-[14px] font-semibold"
            >Facebook</span
          >
        </button>
      </div>

      <!-- Sign Up Link -->
      <div class="text-center">
        <p
          class="text-[#6b7280] font-plus-jakarta-sans text-[14px] font-normal"
        >
          Don't have an account?
          <a
            href="#"
            class="hover:brightness-110 transition-all duration-300 text-[#3b82f6] font-plus-jakarta-sans text-[14px] font-semibold"
          >
            Sign up here
          </a>
        </p>
      </div>
    </div>
  </div>
</template>
