<script setup>
import { onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useMeetingStore } from "@/stores/meeting";
import { Video, ExternalLink } from "lucide-vue-next";
import { DateTime } from "luxon";

const meetingStore = useMeetingStore();
const { upcomingMeetings, loadingUpcoming, upcomingError } = storeToRefs(meetingStore);

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

const fetchUpcoming = () => meetingStore.fetchUpcomingMeetings({ limit: 5 });

onMounted(() => {
    fetchUpcoming();
});
</script>

<template>
    <div class="bg-[var(--color-surface)] border border-[var(--color-border-default)] rounded-2xl p-5">
        <div class="flex items-center gap-2 mb-4">
            <Video aria-hidden="true" class="w-5 h-5 text-[var(--color-feedback-info)]" />
            <h3 class="text-lg font-bold text-[var(--color-text-primary)]">Upcoming Meetings</h3>
        </div>

        <div v-if="loadingUpcoming" role="status" class="flex items-center justify-center gap-3 py-6">
            <div aria-hidden="true" class="motion-safe:animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
            <p class="text-sm text-[var(--color-text-secondary)]">Loading upcoming meetings...</p>
        </div>

        <div v-else-if="upcomingError" class="text-center py-6">
            <p role="alert" class="text-sm text-[var(--color-feedback-danger)]">Unable to load upcoming meetings.</p>
            <button type="button" class="mt-3 rounded-lg bg-primary-700 px-4 py-2 text-sm font-medium text-white hover:bg-primary-800" @click="fetchUpcoming">Retry</button>
        </div>
        <div v-else-if="!upcomingMeetings || upcomingMeetings.length === 0" role="status" class="text-center py-6">
            <p class="text-[var(--color-text-secondary)] text-sm">Belum ada meeting mendatang</p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="meeting in upcomingMeetings"
                :key="meeting.id"
                class="flex items-start justify-between gap-3 p-3 rounded-lg hover:bg-gray-50 motion-reduce:transition-none transition-colors"
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
                    class="flex-shrink-0 inline-flex items-center gap-1 text-xs text-[var(--color-feedback-info)] hover:underline font-medium"
                >
                    Join
                    <ExternalLink aria-hidden="true" class="w-3 h-3" />
                </a>
            </div>
        </div>

        <router-link
            to="/admin/meetings"
            class="block text-center text-sm text-[var(--color-feedback-info)] hover:underline font-medium mt-4 pt-3 border-t border-gray-100"
        >
            View All Meetings
        </router-link>
    </div>
</template>
