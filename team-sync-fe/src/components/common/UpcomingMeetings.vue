<script setup>
import { onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useMeetingStore } from "@/stores/meeting";
import { Video, ExternalLink } from "lucide-vue-next";
import { DateTime } from "luxon";

const meetingStore = useMeetingStore();
const { upcomingMeetings, loadingUpcoming } = storeToRefs(meetingStore);

const formatSchedule = (dateStr) => {
    if (!dateStr) return "";
    const dt = DateTime.fromISO(dateStr);
    const now = DateTime.now();

    if (dt.hasSame(now, "day")) {
        return `Today, ${dt.toFormat("HH:mm")}`;
    }
    if (dt.hasSame(now.plus({ days: 1 }), "day")) {
        return `Tomorrow, ${dt.toFormat("HH:mm")}`;
    }
    return dt.toFormat("dd MMM, HH:mm");
};

const isUrl = (str) => {
    if (!str) return false;
    return str.startsWith("http://") || str.startsWith("https://");
};

onMounted(() => {
    meetingStore.fetchUpcomingMeetings({ limit: 5 });
});
</script>

<template>
    <div class="bg-white border border-gray-200 rounded-2xl p-5">
        <div class="flex items-center gap-2 mb-4">
            <Video class="w-5 h-5 text-blue-600" />
            <h3 class="text-lg font-bold text-gray-900">Upcoming Meetings</h3>
        </div>

        <div v-if="loadingUpcoming" class="flex items-center justify-center py-6">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
        </div>

        <div v-else-if="!upcomingMeetings || upcomingMeetings.length === 0" class="text-center py-6">
            <p class="text-gray-400 text-sm">Belum ada meeting mendatang</p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="meeting in upcomingMeetings"
                :key="meeting.id"
                class="flex items-start justify-between gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors"
            >
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-gray-900 text-sm truncate">{{ meeting.title }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ formatSchedule(meeting.scheduled_at) }}</p>
                </div>
                <a
                    v-if="isUrl(meeting.location)"
                    :href="meeting.location"
                    target="_blank"
                    rel="noopener"
                    class="flex-shrink-0 inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 font-medium"
                >
                    Join
                    <ExternalLink class="w-3 h-3" />
                </a>
            </div>
        </div>

        <router-link
            to="/admin/meetings"
            class="block text-center text-sm text-blue-600 hover:text-blue-800 font-medium mt-4 pt-3 border-t border-gray-100"
        >
            View All Meetings
        </router-link>
    </div>
</template>
