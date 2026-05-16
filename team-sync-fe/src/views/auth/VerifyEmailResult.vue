<script setup lang="ts">
import { computed } from "vue";
import { useRoute, RouterLink } from "vue-router";
import { ArrowLeft, CheckCircle2, MailWarning } from "lucide-vue-next";

const route = useRoute();
const status = computed(() => String(route.query.status || "pending"));
const success = computed(() => status.value === "success");
</script>

<template>
    <div class="w-full max-w-sm mx-auto text-center">
        <!-- Icon -->
        <div
            class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full mb-5"
            :class="success ? 'bg-green-100 text-green-600' : 'bg-amber-100 text-amber-500'"
        >
            <component :is="success ? CheckCircle2 : MailWarning" class="h-7 w-7" aria-hidden="true" />
        </div>

        <!-- Content -->
        <h1 class="text-2xl font-bold text-gray-900">
            {{ success ? "Email verified" : "Link invalid" }}
        </h1>
        <p class="mt-2 text-sm text-gray-600">
            {{
                success
                    ? "Your email address has been verified successfully. You can now sign in to TeamSync."
                    : "The verification link is invalid or has expired. Please request a new verification email."
            }}
        </p>

        <!-- Action Button -->
            <RouterLink
                :to="{ name: 'login' }"
                class="mt-6 inline-flex items-center justify-center gap-2 rounded-lg bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:brightness-110 hover:shadow focus:outline-none focus:ring-2 focus:ring-brand-primary focus:ring-offset-2"
            >
            <ArrowLeft class="h-4 w-4" aria-hidden="true" />
            Go to sign in
        </RouterLink>

        <p class="mt-5 text-xs text-gray-500">If the problem continues, contact your administrator.</p>
    </div>
</template>
