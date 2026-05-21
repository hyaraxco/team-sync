<script setup>
import { computed, reactive } from "vue";
import { useRoute, RouterLink } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { storeToRefs } from "pinia";
import Input from "@/components/common/form/Input.vue";
import Alert from "@/components/common/Alert.vue";
import { AtSign, ArrowLeft, KeyRound, Lock, CheckCircle2 } from "lucide-vue-next";

const route = useRoute();
const authStore = useAuthStore();
const { loading, error, success } = storeToRefs(authStore);
const fieldErrors = computed(() => (error.value && typeof error.value === "object" ? error.value : {}));

const form = reactive({
    email: String(route.query.email || ""),
    token: String(route.query.token || ""),
    password: "",
    password_confirmation: "",
});

const missingToken = computed(() => !form.token);

const handleSubmit = async () => {
    await authStore.resetPassword({ ...form });
};
</script>

<template>
    <div class="w-full max-w-[420px] mx-auto animate-fadeIn px-4">
        <!-- Success State -->
        <div v-if="success" class="text-center">
            <div
                class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-green-50 mb-6 ring-4 ring-white shadow-sm"
            >
                <CheckCircle2 class="h-8 w-8 text-green-500" aria-hidden="true" stroke-width="1.5" />
            </div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Password Diperbarui</h1>
            <p class="mt-3 text-sm text-gray-600 max-w-xs mx-auto leading-relaxed">
                Your password has been reset successfully. You can now use it to log in to your account.
            </p>
            <RouterLink
                :to="{ name: 'login' }"
                class="group mt-8 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:bg-primary-700 active:bg-primary-800 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-primary-600/20"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-1" aria-hidden="true" />
                Return to sign in
            </RouterLink>
        </div>

        <!-- Invalid Token State -->
        <div v-else-if="missingToken" class="text-center">
            <div
                class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-red-50 mb-6 ring-4 ring-white shadow-sm"
            >
                <KeyRound class="h-8 w-8 text-red-500" aria-hidden="true" stroke-width="1.5" />
            </div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Tautan Reset Tidak Valid</h1>
            <p class="mt-3 text-sm text-gray-600 max-w-xs mx-auto leading-relaxed">
                This password reset link is missing a token or has expired. Please request a new one.
            </p>
            <RouterLink
                :to="{ name: 'forgot-password' }"
                class="group mt-8 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:bg-primary-700 active:bg-primary-800 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-primary-600/20"
            >
                Request new link
            </RouterLink>

            <div class="mt-6 text-center">
                <RouterLink
                    :to="{ name: 'login' }"
                    class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-gray-900 transition-colors"
                >
                    <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    Back to sign in
                </RouterLink>
            </div>
        </div>

        <!-- Form State -->
        <template v-else>
            <div class="text-center mb-8">
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 mb-6 ring-4 ring-white shadow-sm"
                >
                    <KeyRound class="h-6 w-6 text-brand-primary" aria-hidden="true" stroke-width="1.5" />
                </div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Password Baru</h1>
                <p class="mt-2 text-sm text-gray-500">Create a strong password for your account</p>
            </div>

            <!-- Error Alert -->
            <Transition name="fade-slide">
                <Alert
                    v-if="typeof error === 'string'"
                    type="danger"
                    title="Reset Failed"
                    :message="error"
                    :show="true"
                    class="mb-6"
                />
            </Transition>

            <!-- Form Card -->
            <div
                class="bg-white/80 backdrop-blur-xl rounded-2xl border border-gray-200/60 p-8 shadow-md text-left"
            >
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
                        autocomplete="new-password"
                        v-model="form.password"
                        label="New Password"
                        placeholder="Create a new password"
                        :required="true"
                        :error="fieldErrors?.password?.join(', ')"
                    >
                        <template #icon>
                            <Lock class="h-5 w-5" aria-hidden="true" stroke-width="1.5" />
                        </template>
                    </Input>

                    <Input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        label="Confirm Password"
                        placeholder="Repeat your new password"
                        :required="true"
                        :error="fieldErrors?.password_confirmation?.join(', ')"
                    >
                        <template #icon>
                            <Lock class="h-5 w-5" aria-hidden="true" stroke-width="1.5" />
                        </template>
                    </Input>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        class="group w-full flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:bg-primary-700 active:bg-primary-800 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-primary-600/20 disabled:cursor-not-allowed disabled:opacity-80 mt-2"
                        :disabled="loading"
                    >
                        <span class="flex items-center gap-2">
                            {{ loading ? "Updating…" : "Reset password" }}
                            <svg
                                v-if="loading"
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
                    </button>
                </form>
            </div>

            <!-- Back Link -->
            <div class="mt-8 text-center">
                <RouterLink
                    :to="{ name: 'login' }"
                    class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-gray-900 transition-colors"
                >
                    <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    Back to sign in
                </RouterLink>
            </div>
        </template>
    </div>
</template>


