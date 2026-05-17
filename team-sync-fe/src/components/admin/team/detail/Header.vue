<script setup>
import { Building, Calendar, Edit, Share, Users } from "lucide-vue-next";
import { RouterLink } from "vue-router";
import _ from "lodash";
import { formatToClientTimezone } from "@/helpers/format";

defineProps({
    team: {
        type: Object,
        required: true,
    },
});
</script>
<template>
    <div class="bg-white border border-brand-border rounded-2xl mb-6 p-6">
        <div class="flex items-center gap-6">
            <div class="relative">
                <div class="w-32 h-32 relative flex items-center justify-center rounded-full overflow-hidden">
                    <!-- Main blue background -->
                    <div
                        class="w-full h-full absolute bg-brand-primary rounded-full"
                    ></div>
                    <!-- Lucide icon -->
                    <img loading="lazy" class="w-16 h-16 text-white relative z-10" :src="team.icon" alt="Team Icon" />
                </div>
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-4 mb-3">
                    <h1 class="text-brand-dark text-2xl font-semibold">
                        {{ team.name }}
                    </h1>
                    <span class="px-3 py-1 rounded-md text-sm font-semibold bg-primary-100 text-primary-800">Verified</span>
                </div>
                <div class="flex items-center gap-6 text-base text-gray-600">
                    <div class="flex items-center gap-2">
                        <Users class="w-4 h-4"></Users>
                        <span>{{ team.members_count }} members</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Building class="w-4 h-4"></Building>
                        <span>{{ _.capitalize(team.department) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Calendar class="w-4 h-4"></Calendar>
                        <span>{{ formatToClientTimezone(team.created_at) }}</span>
                    </div>
                </div>
            </div>
            <div class="flex gap-3">
                <RouterLink
                    :to="{ name: 'admin.team.edit', params: { id: team.id } }"
                    class="btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-6 py-3 flex items-center gap-2"
                >
                    <Edit class="w-4 h-4 text-white" />
                    <span class="text-brand-white text-sm font-semibold">Edit Team</span>
                </RouterLink>
                <button
                    class="bg-white border border-brand-border text-brand-dark py-3 px-6 rounded-lg font-medium hover:bg-gray-50 transition-colors flex items-center gap-2"
                >
                    <Share class="w-4 h-4" />
                    Share Team
                </button>
            </div>
        </div>
    </div>
</template>
