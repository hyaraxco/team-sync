<script setup>
import { can } from "@/helpers/permissionHelper";
import { DEFAULT_AVATAR } from "@/helpers/format";
import _ from "lodash";
import { Building, User, Calendar, Eye, Edit } from "lucide-vue-next";
import { useRouter } from "vue-router";

const router = useRouter();

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const goToEdit = () => {
    router.push({ name: "admin.staffMembers.edit", params: { id: props.data.id } });
};

const goToDetail = () => {
    router.push({
        name: "admin.staffMembers.detail",
        params: { id: props.data.id },
    });
};
</script>

<template>
    <!-- Employee Card 1 -->
    <div
        class="border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 hover:shadow-lg transition-all duration-300 p-4"
    >
        <div class="flex flex-col items-center mb-3">
            <div class="relative">
                <img loading="lazy"
                    :src="data?.user?.profile_photo || DEFAULT_AVATAR"
                    alt="Sarah Johnson"
                    class="w-20 h-20 rounded-full object-cover mb-3"
                />
                <!-- Active Badge Overlapped at Bottom -->
                <span
                    class="absolute bottom-2 left-1/2 transform -translate-x-1/2 px-2 py-1 rounded-md text-xs font-semibold bg-success-50 text-success-700"
                >
                    {{ _.capitalize(data?.job_information?.status) }}
                </span>
            </div>
        </div>
        <div class="flex items-center justify-between mb-3">
            <div class="text-left">
                <h4 class="text-brand-dark font-['Plus_Jakarta_Sans'] text-[16px] font-bold">
                    {{ data?.user?.name }}
                </h4>
                <p class="mt-1 text-brand-light font-['Plus_Jakarta_Sans'] text-[14px] font-normal">
                    {{ _.capitalize(data?.job_information?.job_title) }}
                </p>
            </div>
            <span class="px-2 py-1 rounded-md text-xs font-semibold bg-primary-100 text-primary-800">
                {{ _.capitalize(data?.job_information?.employment_type) }}
            </span>
        </div>

        <!-- Divider -->
        <div class="border-b border-brand-border mb-3"></div>

        <!-- Staff Member Details -->
        <div class="space-y-2 mb-4">
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <Building class="w-3.5 h-3.5" />
                <span>{{ _.capitalize(data?.job_information?.work_location) }}</span>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <User class="w-3.5 h-3.5" />
                <span>{{ _.capitalize(data?.gender) }}</span>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <Calendar class="w-3.5 h-3.5" />
                <span>{{ data?.job_information?.start_date || "-" }}</span>
            </div>
        </div>
        <div class="flex gap-2">
            <button
                @click="goToEdit"
                class="flex-1 border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-4 py-3 flex items-center justify-center gap-2"
                v-if="can('staff-member-edit')"
            >
                <Edit class="w-4 h-4 text-gray-600" />
                <span class="text-brand-dark text-sm font-semibold">Edit</span>
            </button>
            <button
                @click="goToDetail"
                class="flex-1 border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-4 py-3 flex items-center justify-center gap-2"
            >
                <Eye class="w-4 h-4 text-gray-600" />
                <span class="text-brand-dark text-sm font-semibold">View</span>
            </button>
        </div>
    </div>
</template>
