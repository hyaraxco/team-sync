<script setup>
import { Plus, Mail } from "lucide-vue-next";
import { useRouter } from "vue-router";

const router = useRouter();

const goToEmployees = () => {
    router.push({ name: "admin.staffMembers" });
};

const addAnotherEmployee = () => {
    router.push({ name: "admin.staffMembers.create" });
};
</script>

<template>
    <!-- Success Content -->
    <main class="flex items-center justify-center py-12 min-h-[calc(100vh-80px)]">
        <div class="max-w-md mx-auto text-center px-4">
            <!-- Success Card -->
            <div class="bg-[var(--color-surface)] border border-brand-border rounded-2xl p-8 shadow-md">
                <!-- Success Icon -->
                <div class="flex justify-center mb-6">
                    <div class="w-32 h-32 flex items-center justify-center">
                        <div class="w-32 h-32 bg-green-100 rounded-full flex items-center justify-center">
                            <div class="w-24 h-24 bg-green-500 rounded-full flex items-center justify-center">
                                <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="3"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Success Message -->
                <div class="mb-8">
                    <h1 class="text-brand-dark text-2xl font-extrabold mb-3">Added Successfully!</h1>
                    <p class="text-brand-light text-base font-normal leading-relaxed">
                        The new employee has been successfully added to your system, let's help them onboarding.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col gap-3">
                    <!-- Primary CTA -->
                    <button
                        @click="goToEmployees"
                        class="btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 bg-brand-primary px-6 py-3 flex items-center justify-center gap-2"
                    >
                        <span class="text-white text-base font-semibold">View All Staff Members</span>
                        <Users class="w-4 h-4 text-white" />
                    </button>

                    <!-- Secondary CTA -->
                    <button
                        @click="addAnotherEmployee"
                        class="border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-6 py-3 flex items-center justify-center gap-2"
                    >
                        <span class="text-brand-dark text-base font-semibold">Add Another Employee</span>
                        <Plus class="w-4 h-4 text-gray-600" />
                    </button>
                </div>

                <!-- Additional Info -->
                <div class="mt-6 pt-6 border-t border-brand-border">
                    <div class="flex items-center justify-center gap-2 text-brand-light text-sm">
                        <Mail class="w-4 h-4" />
                        <span>Onboarding email sent automatically</span>
                    </div>
                </div>
            </div>

        </div>
    </main>
</template>
