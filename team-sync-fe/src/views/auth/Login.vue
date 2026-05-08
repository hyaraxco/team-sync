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

const rememberMe = ref(false);

type LoginFieldErrors = Partial<Record<"email" | "password", string[]>>;

const fieldErrors = computed<LoginFieldErrors>(() =>
    error.value && typeof error.value === "object" ? (error.value as LoginFieldErrors) : {},
);

const hasAuthError = computed(() => error.value === "Unauthorized");
const hasGeneralError = computed(
    () => error.value && error.value !== "Unauthorized" && typeof error.value !== "object",
);

const handleSubmit = async () => {
    await login({
        ...form.value,
        remember: rememberMe.value,
    });

    if (error.value === "Unauthorized") {
        form.value.password = "";
    }
};
</script>

<template>
    <div class="w-full max-w-md mx-auto animate-fadeIn px-4">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Welcome back</h1>
            <p class="mt-2 text-[15px] text-gray-500">Sign in to access your Team Sync dashboard</p>
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
            <Alert
                v-else-if="hasGeneralError"
                type="danger"
                title="Login Error"
                :message="error"
                :show="hasGeneralError"
            />
        </Transition>

        <!-- Form Card -->
        <div
            class="bg-white/80 backdrop-blur-xl rounded-2xl border border-gray-200/60 p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)]"
        >
            <form class="space-y-5 mb-4" @submit.prevent="handleSubmit" novalidate>
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
                    class="mt-4"
                >
                    <template #icon>
                        <Lock class="h-5 w-5" aria-hidden="true" stroke-width="1.5" />
                    </template>
                </Input>

                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between p-4">
                    <div class="flex items-center gap-2">
                        <input
                            id="remember"
                            v-model="rememberMe"
                            name="remember"
                            type="checkbox"
                            class="h-4 w-4 cursor-pointer rounded border-gray-300 text-[#0C51D9] focus:ring-2 focus:ring-[#0C51D9]/20"
                        />
                        <label
                            for="remember"
                            class="cursor-pointer text-sm font-medium text-gray-600 transition-colors hover:text-gray-900"
                        >
                            Remember me
                        </label>
                    </div>

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
                        <ArrowRight
                            v-if="!loading"
                            class="h-4 w-4 transition-transform group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                        <svg
                            v-else
                            class="animate-spin h-4 w-4 text-white"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                    </span>
                    <div
                        v-if="!loading"
                        class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-out"
                    ></div>
                </button>
            </form>
        </div>

        <!-- Help Section -->
        <div class="mt-8 text-center text-sm font-medium text-gray-500">
            Need help? Contact your
            <span class="text-gray-900 cursor-pointer hover:underline underline-offset-4 decoration-gray-300">
                HR administrator
            </span>
        </div>
    </div>
</template>

<style>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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
