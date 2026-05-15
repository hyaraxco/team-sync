<script setup>
import { CheckCircle, TrendingUp, Users, Folder } from "lucide-vue-next";
import AnimatedValue from "@/components/common/AnimatedValue.vue";
import { watch } from "vue";
import { useTeamStore } from "@/stores/team";
import { storeToRefs } from "pinia";
import { useToast } from "@/composables/useToast";

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
});

const teamStore = useTeamStore();
const toast = useToast();
const { teamStatistics, loadingTeamStatistics } = storeToRefs(teamStore);
const { fetchTeamStatistics } = teamStore;

const loadStatistics = async () => {
    if (props.team?.id) {
        try {
            await fetchTeamStatistics(props.team.id);
        } catch (error) {
            toast.error("Failed to load statistics. Please try again.");
        }
    }
};

watch(
    () => props.team?.id,
    async (newId) => {
        if (newId) {
            await loadStatistics();
        }
    },
    { immediate: true },
);
</script>
<template>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div
            class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-primary-500/20 transition-all duration-300 p-5"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-brand-dark text-base font-medium">Active Members</p>
                    <p class="text-brand-dark text-3xl font-extrabold leading-tight my-2">
                        <template v-if="loadingTeamStatistics">...</template>
                        <AnimatedValue v-else :value="teamStatistics.active_members || 0" />
                    </p>
                    <p
                        class="text-success text-base font-medium"
                        v-if="!loadingTeamStatistics && teamStatistics.active_members >= team.expected_size"
                    >
                        Full capacity
                    </p>
                    <p
                        class="text-danger text-base font-medium"
                        v-if="!loadingTeamStatistics && teamStatistics.active_members < team.expected_size"
                    >
                        {{ team.expected_size - teamStatistics.active_members }} members needed
                    </p>
                </div>
                <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center">
                    <Users class="w-6 h-6 text-blue-600" />
                </div>
            </div>
        </div>
        <div
            class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-primary-500/20 transition-all duration-300 p-5"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-brand-dark text-base font-medium">Projects Assigned</p>
                    <p class="text-brand-dark text-3xl font-extrabold leading-tight my-2">
                        <template v-if="loadingTeamStatistics">...</template>
                        <AnimatedValue v-else :value="teamStatistics.projects_assigned || 0" />
                    </p>
                    <p class="text-success text-base font-medium">
                        {{ loadingTeamStatistics ? "..." : teamStatistics.projects_in_progress || 0 }} in progress
                    </p>
                </div>
                <div class="w-12 h-12 bg-green-50 rounded-2xl flex items-center justify-center">
                    <Folder class="w-6 h-6 text-green-600" />
                </div>
            </div>
        </div>
        <div
            class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-primary-500/20 transition-all duration-300 p-5"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-brand-dark text-base font-medium">Team Performance</p>
                    <p class="text-brand-dark text-3xl font-extrabold leading-tight my-2">
                        <template v-if="loadingTeamStatistics">...</template>
                        <AnimatedValue v-else :value="teamStatistics.team_performance || 0" suffix="%" />
                    </p>
                    <p
                        class="text-base font-medium"
                        :class="(teamStatistics.team_performance || 0) >= 80 ? 'text-success' : 'text-warning'"
                    >
                        {{ (teamStatistics.team_performance || 0) >= 80 ? "Above target" : "Below target" }}
                    </p>
                </div>
                <div class="w-12 h-12 bg-purple-50 rounded-2xl flex items-center justify-center">
                    <TrendingUp class="w-6 h-6 text-purple-600" />
                </div>
            </div>
        </div>
        <div
            class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-primary-500/20 transition-all duration-300 p-5"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-brand-dark text-base font-medium">Completion Rate</p>
                    <p class="text-brand-dark text-3xl font-extrabold leading-tight my-2">
                        <template v-if="loadingTeamStatistics">...</template>
                        <AnimatedValue v-else :value="teamStatistics.completion_rate || 0" suffix="%" />
                    </p>
                    <p class="text-success text-base font-medium">This month</p>
                </div>
                <div class="w-12 h-12 bg-orange-50 rounded-2xl flex items-center justify-center">
                    <CheckCircle class="w-6 h-6 text-orange-600" />
                </div>
            </div>
        </div>
    </div>
</template>
