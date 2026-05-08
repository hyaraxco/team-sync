<script setup lang="ts">
import { Building, User, Briefcase, Phone, Check, Eye } from "lucide-vue-next";

interface Props {
    currentStep?: number;
    isEditing?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    currentStep: 1,
    isEditing: false,
});
</script>

<template>
    <!-- Vertical Step Progress -->
    <aside class="hidden lg:flex lg:w-48 xl:w-52 2xl:w-56 bg-white border-r border-[#DCDEDD] flex-col">
        <!-- Logo Section -->
        <div class="px-4 py-4 border-b border-[#DCDEDD]">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 relative flex items-center justify-center">
                    <!-- Background circle -->
                    <div
                        class="w-11 h-11 absolute bg-gradient-to-br from-primary-100 to-primary-200 rounded-full"
                    ></div>
                    <!-- Overlapping smaller circle -->
                    <div
                        class="w-8 h-8 absolute bg-gradient-to-br from-primary-500 to-primary-600 rounded-full opacity-90"
                    ></div>
                    <!-- Lucide icon -->
                    <Building class="w-4 h-4 text-white relative z-10" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-brand-dark text-base font-bold leading-5">Team Sync Pro</h1>
                    <p class="text-brand-dark text-xs font-medium">
                        {{ props.isEditing ? "Edit Staff Member" : "Add New Staff Member" }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Step Progress -->
        <div class="space-y-4 flex-1 mt-6 px-4 pb-6">
            <!-- Step 1 -->
            <div class="flex items-start gap-3">
                <div
                    :class="[
                        'w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0',
                        currentStep > 1
                            ? 'bg-green-600 text-white'
                            : currentStep === 1
                              ? 'bg-blue-600 text-white'
                              : 'bg-gray-200 text-gray-500',
                    ]"
                >
                    <User class="w-5 h-5" v-if="currentStep <= 1" />
                    <Check class="w-5 h-5" v-else />
                </div>
                <div class="flex-1">
                    <h3
                        :class="[
                            'text-sm xl:text-[15px] font-semibold leading-5',
                            currentStep > 1 ? 'text-green-600' : currentStep === 1 ? 'text-blue-600' : 'text-gray-700',
                        ]"
                    >
                        Personal Information
                    </h3>
                    <p
                        :class="[
                            'text-xs font-medium mt-1 leading-4',
                            currentStep > 1 ? 'text-green-600' : currentStep === 1 ? 'text-blue-600' : 'text-gray-600',
                        ]"
                    >
                        <span class="xl:hidden">Basic details and contact info</span>
                        <span class="hidden xl:inline">Basic employee details and contact info</span>
                    </p>
                </div>
            </div>

            <!-- Connector Line 1 -->
            <div
                :class="[
                    'ml-[17px] xl:ml-5 w-0.5 h-5',
                    currentStep > 1 ? 'bg-green-600' : currentStep === 1 ? 'bg-blue-600' : 'bg-gray-200',
                ]"
            ></div>

            <!-- Step 2 -->
            <div class="flex items-start gap-3">
                <div
                    :class="[
                        'w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0',
                        currentStep > 2
                            ? 'bg-green-600 text-white'
                            : currentStep === 2
                              ? 'bg-blue-600 text-white'
                              : 'bg-gray-200 text-gray-500',
                    ]"
                >
                    <Briefcase class="w-5 h-5" v-if="currentStep <= 2" />
                    <Check class="w-5 h-5" v-else />
                </div>
                <div class="flex-1">
                    <h3
                        :class="[
                            'text-sm xl:text-[15px] font-semibold leading-5',
                            currentStep > 2 ? 'text-green-600' : currentStep === 2 ? 'text-blue-600' : 'text-gray-700',
                        ]"
                    >
                        Job Information
                    </h3>
                    <p
                        :class="[
                            'text-xs font-medium mt-1 leading-4',
                            currentStep > 2 ? 'text-green-600' : currentStep === 2 ? 'text-blue-600' : 'text-gray-600',
                        ]"
                    >
                        <span class="xl:hidden">Role and employment details</span>
                        <span class="hidden xl:inline">Role, department, and employment details</span>
                    </p>
                </div>
            </div>

            <!-- Connector Line 2 -->
            <div
                :class="[
                    'ml-[17px] xl:ml-5 w-0.5 h-5',
                    currentStep > 2 ? 'bg-green-600' : currentStep === 2 ? 'bg-blue-600' : 'bg-gray-200',
                ]"
            ></div>

            <!-- Step 3 -->
            <div class="flex items-start gap-3">
                <div
                    :class="[
                        'w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0',
                        currentStep > 3
                            ? 'bg-green-600 text-white'
                            : currentStep === 3
                              ? 'bg-blue-600 text-white'
                              : 'bg-gray-200 text-gray-500',
                    ]"
                >
                    <Phone class="w-5 h-5" v-if="currentStep <= 3" />
                    <Check class="w-5 h-5" v-else />
                </div>
                <div class="flex-1">
                    <h3
                        :class="[
                            'text-sm xl:text-[15px] font-semibold leading-5',
                            currentStep > 3 ? 'text-green-600' : currentStep === 3 ? 'text-blue-600' : 'text-gray-700',
                        ]"
                    >
                        Emergency Contact
                    </h3>
                    <p
                        :class="[
                            'text-xs font-medium mt-1 leading-4',
                            currentStep > 3 ? 'text-green-600' : currentStep === 3 ? 'text-blue-600' : 'text-gray-600',
                        ]"
                    >
                        <span class="xl:hidden">Person to contact in emergency</span>
                        <span class="hidden xl:inline">Person to contact in case of emergency</span>
                    </p>
                </div>
            </div>

            <!-- Connector Line 3 -->
            <div
                :class="[
                    'ml-[17px] xl:ml-5 w-0.5 h-5',
                    currentStep > 3 ? 'bg-green-600' : currentStep === 3 ? 'bg-blue-600' : 'bg-gray-200',
                ]"
            ></div>

            <!-- Step 4 -->
            <div class="flex items-start gap-3">
                <div
                    :class="[
                        'w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0',
                        currentStep === 4 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500',
                    ]"
                >
                    <Eye class="w-5 h-5" />
                </div>
                <div class="flex-1">
                    <h3
                        :class="[
                            'text-sm xl:text-[15px] font-semibold leading-5',
                            currentStep === 4 ? 'text-blue-600' : 'text-gray-700',
                        ]"
                    >
                        Review & Submit
                    </h3>
                    <p
                        :class="[
                            'text-xs font-medium mt-1 leading-4',
                            currentStep === 4 ? 'text-blue-600' : 'text-gray-600',
                        ]"
                    >
                        <span class="xl:hidden">Final review before submit</span>
                        <span class="hidden xl:inline">Review all information before submitting</span>
                    </p>
                </div>
            </div>
        </div>
    </aside>
</template>
