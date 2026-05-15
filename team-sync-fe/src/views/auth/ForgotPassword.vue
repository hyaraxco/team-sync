<script setup lang="ts">
import { computed, shallowRef } from "vue";
import { useAuthStore } from "@/stores/auth";
import { storeToRefs } from "pinia";
import Input from "@/components/common/form/Input.vue";
import Alert from "@/components/common/Alert.vue";
import { AtSign, ArrowLeft, Send, CheckCircle2 } from "lucide-vue-next";
import { RouterLink } from "vue-router";

const authStore = useAuthStore();
const { loading, error, success } = storeToRefs(authStore);
const email = shallowRef("");

type ForgotPasswordFieldErrors = Partial<Record<"email", string[]>>;

const fieldErrors = computed<ForgotPasswordFieldErrors>(() =>
    error.value && typeof error.value === "object" ? (error.value as ForgotPasswordFieldErrors) : {},
);

const handleSubmit = async () => {
    await authStore.forgotPassword({ email: email.value });
};
</script>

<template>
    <div class="w-full max-w-md mx-auto animate-fadeIn px-4">
        <!-- Success State -->
        <div v-if="success" class="text-center">
            <div
                class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-green-50 mb-6 ring-4 ring-white shadow-sm"
            >
                <CheckCircle2 class="h-8 w-8 text-green-500" aria-hidden="true" stroke-width="1.5" />
            </div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Check your email</h1>
            <p class="mt-3 text-[15px] text-gray-600 max-w-[280px] mx-auto leading-relaxed">
                We've sent a password reset link to
                <br />
                <strong class="text-gray-900 font-semibold">{{ email }}</strong>
            </p>
            <div class="mt-8 rounded-xl bg-gray-50 p-4 border border-gray-100">
                <p class="text-sm text-gray-500">Didn't receive it? Check your spam folder or try again.</p>
            </div>
            <RouterLink
                :to="{ name: 'login' }"
                class="group mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary-500 px-4 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:bg-primary-700 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-primary-500/20"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-1" aria-hidden="true" />
                Return to sign in
            </RouterLink>
        </div>

        <!-- Form State -->
        <template v-else>
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Reset password</h1>
                <p class="mt-2 text-[15px] text-gray-500 leading-relaxed max-w-[280px] mx-auto">
                    Enter your email and we'll send you a link to reset your password.
                </p>
            </div>

            <!-- Error Alert -->
            <Transition name="fade-slide">
                <Alert
                    v-if="typeof error === 'string'"
                    type="danger"
                    title="Request Failed"
                    :message="error"
                    :show="true"
                    class="mb-6"
                />
            </Transition>

            <!-- Form Card -->
            <div
                class="bg-white/80 backdrop-blur-xl rounded-2xl border border-gray-200/60 p-8 shadow-md"
            >
                <form class="space-y-6" @submit.prevent="handleSubmit" novalidate>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        autocomplete="email"
                        v-model="email"
                        label="Email Address"
                        placeholder="name@company.com"
                        :required="true"
                        :error="fieldErrors?.email?.join(', ')"
                    >
                        <template #icon>
                            <AtSign class="h-5 w-5" aria-hidden="true" stroke-width="1.5" />
                        </template>
                    </Input>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        class="group relative w-full flex items-center justify-center gap-2 rounded-xl bg-primary-500 px-4 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:bg-primary-700 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-70 disabled:hover:bg-primary-500 overflow-hidden"
                        :disabled="loading"
                    >
                        <span class="relative z-10 flex items-center gap-2">
                            {{ loading ? "Sending…" : "Send reset link" }}
                            <Send
                                v-if="!loading"
                                class="h-4 w-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
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

            <!-- Back Link -->
            <div class="mt-6">
                <RouterLink
                    :to="{ name: 'login' }"
                    class="group mx-auto flex w-full max-w-[260px] items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-600 transition-all duration-200 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-4 focus:ring-gray-200/70"
                >
                    <ArrowLeft
                        class="h-4 w-4 shrink-0 transition-transform group-hover:-translate-x-0.5"
                        aria-hidden="true"
                    />
                    <span>Back to sign in</span>
                </RouterLink>
            </div>
        </template>
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
