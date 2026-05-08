<script setup lang="ts">
import { onMounted, watch } from "vue";
import { useTeamStore } from "@/stores/team";
import { useRouter } from "vue-router";
import { getTimeAgo } from "@/utils/dateUtils";
import { getStatusBadgeClass } from "@/utils/badgeUtils";
import { storeToRefs } from "pinia";
import EmptyState from "@/components/common/EmptyState.vue";

const props = defineProps({
    searchParams: {
        type: Object,
        default: () => ({}),
    },
});

const teamStore = useTeamStore();
const { latestTeams, loadingLatest } = storeToRefs(teamStore);

const router = useRouter();

const loadTeams = (params = {}) => {
    teamStore.fetchLatestTeams(params);
};

onMounted(() => {
    loadTeams();
});

watch(
    () => props.searchParams,
    (newParams) => {
        loadTeams(newParams);
    },
    { deep: true },
);

const goToTeamDetail = (id: number) => {
    router.push({ name: "admin.team.detail", params: { id } });
};
</script>

<template>
    <!-- Latest Teams -->
    <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-4 sm:p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-brand-dark text-lg font-bold">Latest Teams</h3>
        </div>

        <!-- Loading State -->
        <div v-if="loadingLatest" class="space-y-4">
            <div v-for="i in 5" :key="i" class="flex items-center gap-3 animate-pulse">
                <div class="w-16 h-14 bg-gray-200 rounded-lg"></div>
                <div class="flex-1">
                    <div class="h-4 bg-gray-200 rounded w-1/3 mb-2"></div>
                    <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                </div>
                <div class="w-24 h-6 bg-gray-200 rounded"></div>
                <div class="w-20 h-10 bg-gray-200 rounded-xl"></div>
            </div>
        </div>

        <!-- Empty State -->
        <EmptyState v-else-if="!latestTeams || latestTeams.length === 0" icon="Briefcase" title="No teams found" />

        <!-- Team List -->
        <div v-else class="space-y-4">
            <div v-for="team in latestTeams" :key="team.id" class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div
                    class="w-14 h-12 sm:w-16 sm:h-14 relative flex items-center justify-center rounded-[8px] overflow-hidden"
                >
                    <!-- Main blue background -->
                    <div
                        class="w-full h-full absolute bg-gradient-to-br from-primary-500 to-primary-600 rounded-[8px]"
                    ></div>
                    <!-- Lucide icon -->
                    <img :src="team.icon" class="w-6 h-6 relative z-10" />
                </div>

                <!-- Team Info -->
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <p class="text-brand-dark text-base sm:text-lg font-bold">{{ team.name }}</p>
                        <span
                            v-if="team.status"
                            :class="getStatusBadgeClass(team.status)"
                            class="px-2 py-1 rounded-md text-xs font-semibold capitalize"
                        >
                            {{ team.status }}
                        </span>
                    </div>
                    <p class="text-brand-dark text-xs sm:text-sm font-normal">
                        {{ team.members_count || 0 }} members •
                        {{ getTimeAgo(team.created_at) }}
                    </p>
                </div>

                <!-- Details Button -->
                <button
                    @click="goToTeamDetail(team.id)"
                    class="btn-details w-full sm:w-auto border border-[#DCDEDD] rounded-xl hover:ring-2 hover:ring-[#0C51D9] hover:text-[#0C51D9] transition-all duration-300 py-2 sm:py-[14px] px-3 sm:px-5 flex items-center justify-center"
                >
                    <span class="text-brand-dark text-sm sm:text-base font-medium">Details</span>
                </button>
            </div>
        </div>
    </div>
</template>
