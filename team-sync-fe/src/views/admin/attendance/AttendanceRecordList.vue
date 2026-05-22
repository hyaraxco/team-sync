<script setup>
import { onMounted } from "vue";
import { DEFAULT_AVATAR } from "@/helpers/format";
import { storeToRefs } from "pinia";
import { Clock } from "lucide-vue-next";
import { useAttendanceStore } from "@/stores/attendance";
import { formatDateShort, formatTime as formatTimeUtil } from "@/utils/dateUtils";
import SearchFilter from "@/components/common/SearchFilter.vue";
import Pagination from "@/components/admin/team/Pagination.vue";
import Alert from "@/components/common/Alert.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import { useSearchFilter } from "@/composables/useSearchFilter";
import StatusBadge from "@/components/common/StatusBadge.vue";

const store = useAttendanceStore();
const { paginatedAttendances, meta, loading, error } = storeToRefs(store);

const { filters, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } = useSearchFilter({
    defaultFilters: { search: null },
    fetchFn: store.fetchAllPaginated,
});

onMounted(() => {
    fetchData();
});

const formatTime = (timeStr) => (timeStr ? formatTimeUtil(timeStr) : "-");
const formatDate = (dateStr) => (dateStr ? formatDateShort(dateStr) : "-");
</script>

<template>
    <div class="p-3 sm:p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <div>
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <span class="sr-only" role="heading" aria-level="1">Attendance Logs</span>
                        <p class="text-2xl font-bold text-brand-dark">Attendance Logs</p>
                        <p class="text-sm text-brand-light mt-1">
                            Review historical attendance logs across the organization.
                        </p>
                    </div>
                </div>

                <!-- Search Filters -->
                <SearchFilter
                    placeholder="Search by Employee..."
                    :search="filters.search"
                    @update:search="filters.search = $event"
                    @search="handleSearch"
                    @reset="handleReset"
                    @change="handleSearch"
                />
            </div>

            <Alert v-if="error" type="error" :message="error" dismissible @close="error = null" />

            <!-- Main Content Card -->
            <div class="bg-white rounded-2xl theme-card-shadow border border-brand-border overflow-hidden">
                <!-- Desktop Table View -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-brand-border/20 border-b border-brand-border">
                                <th
                                    class="py-4 px-6 text-xs font-semibold text-brand-dark uppercase tracking-wider w-[30%]"
                                >
                                    Employee
                                </th>
                                <th
                                    class="py-4 px-6 text-xs font-semibold text-brand-dark uppercase tracking-wider text-center"
                                >
                                    Date
                                </th>
                                <th
                                    class="py-4 px-6 text-xs font-semibold text-brand-dark uppercase tracking-wider text-center"
                                >
                                    Check In
                                </th>
                                <th
                                    class="py-4 px-6 text-xs font-semibold text-brand-dark uppercase tracking-wider text-center"
                                >
                                    Check Out
                                </th>
                                <th
                                    class="py-4 px-6 text-xs font-semibold text-brand-dark uppercase tracking-wider text-center"
                                >
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            <tr v-if="loading">
                                <td colspan="5" class="py-12 text-center text-brand-light">
                                    Loading attendance logs...
                                </td>
                            </tr>
                            <tr v-else-if="paginatedAttendances?.length === 0">
                                <td colspan="5" class="py-12">
                                    <EmptyState
                                        icon="CalendarDays"
                                        title="Data kehadiran tidak ditemukan"
                                        subtitle="Adjust your search filters or wait for employees to clock in."
                                    />
                                </td>
                            </tr>
                            <tr
                                v-for="attendance in paginatedAttendances"
                                :key="attendance.id"
                                class="hover:bg-brand-border/20 transition-colors duration-200"
                            >
                                <!-- Employee Column -->
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <img
                                            loading="lazy"
                                            :src="attendance.staff_member?.user?.profile_photo || DEFAULT_AVATAR"
                                            class="w-10 h-10 rounded-full object-cover border border-brand-border shadow-sm"
                                            alt="Profile"
                                        />
                                        <div>
                                            <p class="text-sm font-semibold text-brand-dark">
                                                {{ attendance.staff_member?.user?.name }}
                                            </p>
                                            <p class="text-xs text-brand-light">
                                                {{ attendance.staff_member?.staff_member_id }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Date -->
                                <td class="py-4 px-6 text-center">
                                    <span class="text-sm text-brand-dark font-medium">
                                        {{ formatDate(attendance.date) }}
                                    </span>
                                </td>

                                <!-- Check In -->
                                <td class="py-4 px-6 text-center">
                                    <div
                                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-brand-border/20 rounded-lg border border-brand-border"
                                    >
                                        <Clock class="w-3.5 h-3.5 text-brand-light" />
                                        <span class="text-sm font-medium text-brand-dark">
                                            {{ formatTime(attendance.check_in) }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Check Out -->
                                <td class="py-4 px-6 text-center">
                                    <div
                                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-brand-border/20 rounded-lg border border-brand-border"
                                    >
                                        <Clock class="w-3.5 h-3.5 text-brand-light" />
                                        <span class="text-sm font-medium text-brand-dark">
                                            {{ formatTime(attendance.check_out) }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="py-4 px-6 text-center">
                                    <StatusBadge type="leave-type" :value="attendance.status" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile View -->
                <div class="md:hidden divide-y divide-brand-border">
                    <div v-if="loading" class="py-12 text-center text-brand-light">Loading attendance logs...</div>
                    <div v-else-if="paginatedAttendances?.length === 0" class="py-12">
                        <EmptyState
                            icon="CalendarDays"
                            title="Data tidak ditemukan"
                            subtitle="Tidak ada data yang cocok dengan filter."
                        />
                    </div>
                    <div v-for="attendance in paginatedAttendances" :key="attendance.id" class="p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <img
                                    loading="lazy"
                                    :src="attendance.staff_member?.user?.profile_photo || DEFAULT_AVATAR"
                                    class="w-10 h-10 rounded-full object-cover shadow-sm"
                                    alt="Profile"
                                />
                                <div>
                                    <p class="text-sm font-semibold text-brand-dark">
                                        {{ attendance.staff_member?.user?.name }}
                                    </p>
                                    <p class="text-xs text-brand-light">{{ formatDate(attendance.date) }}</p>
                                </div>
                            </div>
                            <StatusBadge type="leave-type" :value="attendance.status" />
                        </div>

                        <div
                            class="grid grid-cols-2 gap-4 bg-brand-border/20 rounded-xl p-3 border border-brand-border"
                        >
                            <div>
                                <p class="text-xs text-brand-light mb-1 font-medium">Check In</p>
                                <div class="flex items-center gap-1.5 text-brand-dark">
                                    <Clock class="w-3.5 h-3.5 text-brand-light" />
                                    <span class="text-sm font-semibold">{{ formatTime(attendance.check_in) }}</span>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-brand-light mb-1 font-medium">Check Out</p>
                                <div class="flex items-center gap-1.5 text-brand-dark">
                                    <Clock class="w-3.5 h-3.5 text-brand-light" />
                                    <span class="text-sm font-semibold">{{ formatTime(attendance.check_out) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="p-4 border-t border-brand-border bg-brand-border/10">
                    <Pagination
                        v-if="meta.total > 0"
                        :current-page="meta.current_page"
                        :last-page="meta.last_page"
                        :total="meta.total"
                        :per-page="meta.per_page"
                        @page-change="handlePageChange"
                        @per-page-change="handlePerPageChange"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
