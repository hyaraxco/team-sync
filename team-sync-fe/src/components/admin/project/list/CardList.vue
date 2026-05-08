<script setup>
import { formatToClientTimezone, DEFAULT_AVATAR } from "@/helpers/format";
import { can } from "@/helpers/permissionHelper";
import _ from "lodash";
import { Calendar, Crown, Edit, Eye, FileText, User } from "lucide-vue-next";
import StatusBadge from "@/components/common/StatusBadge.vue";
import AnimatedValue from "@/components/common/AnimatedValue.vue";
import { useRouter } from "vue-router";

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const router = useRouter();
const navigateToDetail = () => {
    router.push({ name: "admin.projects.detail", params: { id: props.data.id } });
};
</script>

<template>
    <div
        @click="navigateToDetail"
        class="border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 hover:shadow-lg transition-all duration-300 p-4 cursor-pointer"
    >
        <!-- Project Image -->
        <div
            class="w-full h-32 bg-gradient-to-br from-blue-100 to-purple-100 relative overflow-hidden rounded-[12px] mb-4"
        >
            <img class="w-full h-full object-cover rounded-[12px]" :src="data.photo" />
            <!-- Priority Badge Overlay -->
            <StatusBadge
                v-if="data.priority"
                type="priority"
                :value="data.priority"
                class="absolute bottom-2 left-2 shadow-[0_2px_8px_rgba(0,0,0,0.08)]"
            />
            <!-- Status Badge Overlay -->
            <StatusBadge
                v-if="data.status"
                type="project"
                :value="data.status"
                class="absolute bottom-2 right-2 shadow-[0_2px_8px_rgba(0,0,0,0.08)]"
            />
        </div>
        <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
                <h4 class="text-brand-dark text-lg font-bold mb-2">
                    {{ data.name }}
                </h4>
                <p class="text-brand-light text-sm line-clamp-2 mb-1">
                    {{ data.description }}
                </p>
            </div>
        </div>

        <div class="border-t border-[#DCDEDD] pt-4 mb-4" v-if="data.leader">
            <div class="flex items-center gap-3">
                <img
                    :src="data.leader?.user?.profile_photo || DEFAULT_AVATAR"
                    class="w-10 h-10 rounded-full object-cover"
                />

                <div class="flex-1">
                    <h5 class="text-brand-dark text-sm font-semibold">
                        {{ data.leader?.user?.name }}
                    </h5>
                    <p class="text-brand-light text-xs">
                        {{ data.leader?.job_information?.job_title }}
                    </p>
                </div>
                <div class="px-2 py-1 bg-green-50 border border-green-200 rounded-[6px] flex items-center gap-1">
                    <Crown class="w-3 h-3 text-green-600" />
                    <span class="text-green-700 text-xs font-medium">Leader</span>
                </div>
            </div>
            <div class="border-b border-[#DCDEDD] pb-4"></div>
        </div>

        <div class="mb-4">
            <div class="flex items-center justify-between text-sm mb-2">
                <span class="text-brand-light">Progress</span>
                <span class="text-brand-dark font-semibold"><AnimatedValue :value="data.progress" suffix="%" /></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="h-2 rounded-full ${getProgressColor(data.progress)}"></div>
            </div>
        </div>

        <div class="space-y-2 mb-4">
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <FileText class="w-4 h-4" />
                <span v-if="data.teams.length > 0">{{ data.teams.map((team) => team.name).join(", ") }}</span>
                <span v-else>No teams assigned</span>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <Calendar class="w-4 h-4" />
                <span>
                    {{ formatToClientTimezone(data.start_date) }} -
                    {{ data.end_date ? formatToClientTimezone(data.end_date) : "N/A" }}
                </span>
            </div>
        </div>

        <div class="flex gap-2" v-if="can('project-edit')">
            <RouterLink
                :to="{ name: 'admin.projects.edit', params: { id: data.id } }"
                class="flex-1 border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-3 py-2 flex items-center justify-center gap-2"
                @click.stop
            >
                <Edit class="w-4 h-4 text-gray-600" />
                <span class="text-brand-dark text-sm font-semibold">Edit</span>
            </RouterLink>
        </div>
    </div>
</template>
