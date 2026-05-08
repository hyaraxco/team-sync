<script setup>
import { CheckCircle, Eye, Edit } from "lucide-vue-next";
import { DEFAULT_AVATAR } from "@/helpers/format";
import StatusBadge from "@/components/common/StatusBadge.vue";

defineProps({
    data: {
        type: Object,
        required: true,
    },
});
</script>
<template>
    <div
        class="border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 hover:shadow-lg transition-all duration-300 p-4"
    >
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <div class="w-14 h-14 relative flex items-center justify-center rounded-[12px] overflow-hidden">
                    <!-- Main blue background -->
                    <div
                        class="w-full h-full absolute bg-gradient-to-br from-primary-500 to-primary-600 rounded-[12px]"
                    ></div>
                    <!-- Lucide icon -->
                    <img :src="data.icon" alt="Team Icon" class="w-6 h-6 text-white relative z-10" />
                </div>
                <div>
                    <h4 class="text-brand-dark text-base font-bold">{{ data.name }}</h4>
                    <p class="text-brand-light text-sm font-normal">{{ data.members_count }} members</p>
                </div>
            </div>
            <StatusBadge type="status" :value="data.status" />
        </div>

        <!-- Border Divider -->
        <div class="border-b border-[#DCDEDD] mb-4"></div>

        <!-- Team Leader Section -->
        <div class="mb-4" v-if="data.leader">
            <div class="flex items-center gap-3">
                <img
                    :src="data.leader.profile_photo || DEFAULT_AVATAR"
                    alt="Team Lead"
                    class="w-12 h-12 rounded-full object-cover"
                />
                <div class="flex-1">
                    <h5 class="text-brand-dark text-sm font-semibold">
                        {{ data.leader.name }}
                    </h5>
                    <p class="text-brand-light text-xs font-normal">
                        {{ data.leader?.employee_profile?.job_information?.job_title }}
                    </p>
                </div>
                <!-- Team Leader Badge -->
                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-md">Team Leader</span>
            </div>
        </div>

        <!-- Team Leader Section Not Found -->
        <div class="mb-4" v-else>
            <div class="w-full h-12 rounded-md bg-gray-200 flex items-center justify-center text-gray-400 text-xs">
                No Leader
            </div>
        </div>

        <!-- Stats Section -->
        <div class="space-y-3 mb-4">
            <div class="flex items-center gap-2">
                <CheckCircle class="w-4 h-4 text-green-600" />
                <span class="text-brand-dark text-sm font-semibold">
                    {{ data.responsibilities?.length }} responsibilities
                </span>
            </div>
            <div class="flex items-center gap-2">
                <CheckCircle class="w-4 h-4 text-green-600" />
                <span class="text-brand-dark text-sm font-semibold">{{ data.projects_count }} active projects</span>
            </div>
            <div class="flex items-center gap-2">
                <CheckCircle class="w-4 h-4 text-green-600" />
                <span class="text-brand-dark text-sm font-semibold">0 completed tasks</span>
            </div>
        </div>
        <div class="flex gap-2">
            <RouterLink
                :to="{ name: 'admin.team.detail', params: { id: data.id } }"
                class="btn-secondary flex-1 text-brand-dark text-sm font-semibold border border-[#DCDEDD] rounded-[8px] px-4 py-3 hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 flex items-center justify-center gap-2"
            >
                <Eye class="w-4 h-4" />
                <span>View</span>
            </RouterLink>
            <RouterLink
                :to="{ name: 'admin.team.edit', params: { id: data.id } }"
                class="btn-secondary flex-1 text-brand-dark text-sm font-semibold border border-[#DCDEDD] rounded-[8px] px-4 py-3 hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 flex items-center justify-center gap-2"
            >
                <Edit class="w-4 h-4" />
                <span>Edit</span>
            </RouterLink>
        </div>
    </div>
</template>
