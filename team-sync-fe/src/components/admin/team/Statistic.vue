<script setup lang="ts">
import { onMounted, computed } from "vue";
import { CheckCircle, PlusCircle, Target, Star, TrendingUp, Users, UserPlus } from "lucide-vue-next";
import { useTeamStore } from "@/stores/team";
import AnimatedValue from "@/components/common/AnimatedValue.vue";

const teamStore = useTeamStore();

onMounted(() => {
    teamStore.fetchStatistics();
});

// Computed properties for statistics
const total = computed(() => teamStore.statistics.total);
const addedThisMonth = computed(() => teamStore.statistics.added_this_month);
const active = computed(() => teamStore.statistics.active);
const activeChange = computed(() => teamStore.statistics.active_change);
const members = computed(() => teamStore.statistics.members);
const membersChange = computed(() => teamStore.statistics.members_change);
const averageSize = computed(() => teamStore.statistics.average_size);
const newTeams = computed(() => teamStore.statistics.new_teams);
const loading = computed(() => teamStore.loadingStatistics);
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <!-- Total Teams Card (spans 2 rows on the left) -->
        <div class="lg:row-span-2 rounded-2xl border border-[#0B1042] relative overflow-hidden main-card p-5">
            <div class="flex flex-col justify-center h-full relative z-10">
                <!-- Trending Badge -->
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex items-center gap-1 px-3 py-1 bg-white/20 rounded-full backdrop-blur-sm">
                        <TrendingUp class="w-3 h-3 text-white" />
                        <span class="text-brand-white text-xs font-semibold">+{{ addedThisMonth }} this month</span>
                    </div>
                </div>

                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-brand-white-90 text-sm font-medium">Total Teams</p>
                        <p class="text-brand-white text-5xl font-extrabold leading-none my-4">
                            <template v-if="loading">...</template>
                            <AnimatedValue v-else :value="total" />
                        </p>
                        <p class="text-brand-white-80 text-base font-normal">Company teams</p>
                    </div>
                    <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center">
                        <Users class="w-8 h-8 text-white" />
                    </div>
                </div>

                <!-- Additional Info -->
                <div class="flex items-center gap-3 mt-auto">
                    <div class="flex items-center gap-1">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span class="text-brand-white-70 text-xs font-normal">All Departments</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <Star class="w-3 h-3 text-white opacity-70" />
                        <span class="text-brand-white-70 text-xs font-normal">Growing Teams</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Teams Card -->
        <div
            class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-primary-500/20 transition-all duration-300 p-5"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-brand-dark text-sm font-medium">Active Teams</p>
                    <p class="text-brand-dark text-3xl font-extrabold leading-none my-2">
                        <template v-if="loading">...</template>
                        <AnimatedValue v-else :value="active" />
                    </p>
                    <p :class="activeChange >= 0 ? 'text-success' : 'text-danger'" class="text-sm font-medium">
                        {{ activeChange >= 0 ? "+" : "" }}{{ activeChange }} this week
                    </p>
                </div>
                <div class="w-14 h-14 bg-green-50 rounded-2xl flex items-center justify-center">
                    <CheckCircle class="w-6 h-6 text-green-600" />
                </div>
            </div>
        </div>

        <!-- Team Members Card -->
        <div
            class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-primary-500/20 transition-all duration-300 p-5"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-brand-dark text-sm font-medium">Team Members</p>
                    <p class="text-brand-dark text-3xl font-extrabold leading-none my-2">
                        <template v-if="loading">...</template>
                        <AnimatedValue v-else :value="members" />
                    </p>
                    <p :class="membersChange >= 0 ? 'text-success' : 'text-danger'" class="text-sm font-medium">
                        {{ membersChange >= 0 ? "+" : "" }}{{ membersChange }} this month
                    </p>
                </div>
                <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center">
                    <UserPlus class="w-6 h-6 text-blue-600" />
                </div>
            </div>
        </div>

        <!-- Average Team Size Card -->
        <div
            class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-primary-500/20 transition-all duration-300 p-5"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-brand-dark text-sm font-medium">Average Team Size</p>
                    <p class="text-brand-dark text-3xl font-extrabold leading-none my-2">
                        <template v-if="loading">...</template>
                        <AnimatedValue v-else :value="averageSize" />
                    </p>
                    <p class="text-success text-sm font-medium">Optimal size</p>
                </div>
                <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center">
                    <Target class="w-6 h-6 text-orange-600" />
                </div>
            </div>
        </div>

        <!-- Recent Teams Card -->
        <div
            class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-primary-500/20 transition-all duration-300 p-5"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-brand-dark text-sm font-medium">New Teams</p>
                    <p class="text-brand-dark text-3xl font-extrabold leading-none my-2">
                        <template v-if="loading">...</template>
                        <AnimatedValue v-else :value="newTeams" />
                    </p>
                    <p class="text-success text-sm font-medium">This month</p>
                </div>
                <div class="w-14 h-14 bg-purple-50 rounded-2xl flex items-center justify-center">
                    <PlusCircle class="w-6 h-6 text-purple-600" />
                </div>
            </div>
        </div>
    </div>
</template>
