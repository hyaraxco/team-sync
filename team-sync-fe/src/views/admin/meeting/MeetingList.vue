<script setup>
import { onMounted, onUnmounted, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useMeetingStore } from "@/stores/meeting";
import { can } from "@/helpers/permissionHelper";
import { Video, Plus, Search, ChevronLeft, ChevronRight, Calendar, ExternalLink } from "lucide-vue-next";
import { DateTime } from "luxon";
import MeetingCreateModal from "@/components/admin/meeting/MeetingCreateModal.vue";
import EmptyState from "@/components/common/EmptyState.vue";

const meetingStore = useMeetingStore();
const { meetings, meta, loading } = storeToRefs(meetingStore);

const showCreateModal = ref(false);
const searchQuery = ref("");
const currentPage = ref(1);
let debounceTimer = null;

const departmentLabels = {
    development: "Development",
    design: "Design",
    marketing: "Marketing",
    sales: "Sales",
    support: "Support",
    management: "Management",
};


const fetchMeetings = () => {
    meetingStore.fetchMeetingsPaginated({
        row_per_page: 10,
        page: currentPage.value,
        search: searchQuery.value || undefined,
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return "-";
    return DateTime.fromISO(dateStr).toFormat("dd MMM yyyy, HH:mm");
};

const isUrl = (str) => {
    if (!str) return false;
    return str.startsWith("http://") || str.startsWith("https://");
};

const truncate = (str, len = 40) => {
    if (!str) return "-";
    return str.length > len ? str.slice(0, len) + "..." : str;
};

const handleSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        currentPage.value = 1;
        fetchMeetings();
    }, 300);
};

const handlePageChange = (page) => {
    currentPage.value = page;
    fetchMeetings();
};

const handleCreated = () => {
    showCreateModal.value = false;
    currentPage.value = 1;
    fetchMeetings();
};

watch(searchQuery, handleSearch);

onMounted(() => {
    fetchMeetings();
});

onUnmounted(() => {
    clearTimeout(debounceTimer);
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="text-2xl font-bold text-brand-dark">Meetings</div>
                <p class="text-sm text-brand-light mt-1">Manage scheduled meetings and broadcasts</p>
            </div>
            <button
                v-if="can('meeting-create')"
                @click="showCreateModal = true"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-700 text-white rounded-lg hover:bg-primary-800 transition-colors font-medium text-sm"
            >
                <Plus class="w-4 h-4" />
                Schedule Meeting
            </button>
        </div>

        <div class="bg-[var(--color-surface)] border border-brand-border rounded-2xl p-4 sm:p-5">
            <div class="mb-4">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-brand-light" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search meetings by title..."
                        class="w-full pl-10 pr-4 py-2.5 border border-brand-border bg-[var(--color-surface)] text-brand-dark rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                    />
                </div>
            </div>

            <div v-if="loading" class="flex items-center justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-primary"></div>
            </div>

            <EmptyState v-else-if="!meetings || meetings.length === 0" icon="Video" title="Belum ada jadwal meeting" subtitle="Jadwalkan meeting baru untuk memulai" size="lg" />

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-brand-border">
                            <th class="text-left py-3 px-3 font-semibold text-brand-light">Title</th>
                            <th class="text-left py-3 px-3 font-semibold text-brand-light">Scheduled</th>
                            <th class="text-left py-3 px-3 font-semibold text-brand-light">Duration</th>
                            <th class="text-left py-3 px-3 font-semibold text-brand-light">Location</th>
                            <th class="text-left py-3 px-3 font-semibold text-brand-light">Departments</th>
                            <th class="text-left py-3 px-3 font-semibold text-brand-light">Teams</th>
                            <th class="text-left py-3 px-3 font-semibold text-brand-light">Created by</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="meeting in meetings"
                            :key="meeting.id"
                            class="border-b border-brand-border hover:bg-[var(--color-surface-overlay)] transition-colors"
                        >
                            <td class="py-3 px-3 font-medium text-brand-dark">{{ meeting.title }}</td>
                            <td class="py-3 px-3 text-brand-light">
                                <div class="flex items-center gap-1.5">
                                    <Calendar class="w-3.5 h-3.5 text-brand-light" />
                                    {{ formatDate(meeting.scheduled_at) }}
                                </div>
                            </td>
                            <td class="py-3 px-3 text-brand-light">{{ meeting.duration_minutes }} min</td>
                            <td class="py-3 px-3">
                                <a
                                    v-if="isUrl(meeting.location)"
                                    :href="meeting.location"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1 text-brand-dark hover:underline font-medium"
                                >
                                    {{ truncate(meeting.location) }}
                                    <ExternalLink class="w-3 h-3" />
                                </a>
                                <span v-else class="text-brand-light">{{ truncate(meeting.location) }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="dept in meeting.departments || []"
                                        :key="dept"
                                        class="px-2 py-0.5 rounded-full text-xs font-medium bg-[var(--color-surface-overlay)] text-brand-dark"
                                    >
                                        {{ departmentLabels[dept] || dept }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="team in meeting.teams || []"
                                        :key="team.id"
                                        class="px-2 py-0.5 rounded-full text-xs font-medium bg-[var(--color-surface-overlay)] text-brand-dark"
                                    >
                                        {{ team.name }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-3 px-3 text-brand-light">{{ meeting.creator?.name || "-" }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="meta.last_page > 1" class="flex items-center justify-between mt-4 pt-4 border-t border-brand-border">
                <p class="text-sm text-brand-light">
                    Page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} meetings)
                </p>
                <div class="flex items-center gap-2">
                    <button
                        :disabled="meta.current_page <= 1"
                        @click="handlePageChange(meta.current_page - 1)"
                        class="p-2 rounded-lg border border-brand-border hover:bg-[var(--color-surface-overlay)] disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                    <button
                        :disabled="meta.current_page >= meta.last_page"
                        @click="handlePageChange(meta.current_page + 1)"
                        class="p-2 rounded-lg border border-brand-border hover:bg-[var(--color-surface-overlay)] disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <MeetingCreateModal :show="showCreateModal" @close="showCreateModal = false" @created="handleCreated" />
    </div>
</template>
