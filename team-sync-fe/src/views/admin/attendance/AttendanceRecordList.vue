<script setup>
import { onMounted } from "vue";
import { storeToRefs } from "pinia";
import { Clock } from "lucide-vue-next";
import { useAttendanceStore } from "@/stores/attendance";
import { formatDateShort, formatTime as formatTimeUtil } from "@/utils/dateUtils";
import SearchFilter from "@/components/common/SearchFilter.vue";
import Alert from "@/components/common/Alert.vue";
import DataTableCard from "@/components/common/DataTableCard.vue";
import TableStateRows from "@/components/common/TableStateRows.vue";
import EmployeeCell from "@/components/common/EmployeeCell.vue";
import { useSearchFilter } from "@/composables/useSearchFilter";
import StatusBadge from "@/components/common/StatusBadge.vue";

const props = defineProps({
    embedded: {
        type: Boolean,
        default: false,
    },
});

const store = useAttendanceStore();
const { paginatedAttendances, meta, loading, error } = storeToRefs(store);

const { filters, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } = useSearchFilter({
    defaultFilters: { search: null, status: "" },
    fetchFn: store.fetchAllPaginated,
});

onMounted(() => {
    fetchData();
});

const formatTime = (timeStr) => (timeStr ? formatTimeUtil(timeStr) : "-");
const formatDate = (dateStr) => (dateStr ? formatDateShort(dateStr) : "-");
</script>

<template>
    <div :class="embedded ? 'space-y-6' : 'space-y-6 p-3 sm:p-4 md:p-6 lg:p-8'">
        <div v-if="!embedded" role="heading" aria-level="1" class="sr-only">Attendance Logs</div>

        <div class="mb-6">
            <SearchFilter
                placeholder="Search employees by name..."
                :filters="[
                    {
                        key: 'status',
                        label: 'All Statuses',
                        icon: 'CheckCircle',
                        options: [
                            { value: 'present', label: 'Present' },
                            { value: 'late', label: 'Late' },
                            { value: 'absent', label: 'Absent' },
                            { value: 'half_day', label: 'Half Day' },
                            { value: 'sick_leave', label: 'Sick Leave' },
                            { value: 'annual_leave', label: 'Annual Leave' },
                        ],
                    },
                ]"
                @search="handleSearch"
                @reset="handleReset"
            />
        </div>

        <Alert v-if="error" type="error" :message="error" dismissible @close="error = null" />

        <!-- Table -->
        <DataTableCard :meta="meta" :loading="loading" @page-change="handlePageChange" @per-page-change="handlePerPageChange">
            
            <table class="min-w-full divide-y divide-brand-border">
                <thead>
                    <tr class="bg-brand-border/20 border-b border-brand-border">
                        <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">
                            Employee
                        </th>
                        <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">
                            Date
                        </th>
                        <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">
                            Check In
                        </th>
                        <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">
                            Check Out
                        </th>
                        <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border">
                    <TableStateRows
                        :loading="loading"
                        :empty="!paginatedAttendances || paginatedAttendances.length === 0"
                        :colspan="5"
                        empty-icon="CalendarDays"
                        empty-title="No attendance data found"
                        empty-subtitle="Adjust your search filters or wait for employees to clock in."
                    />
                    <template v-if="paginatedAttendances && paginatedAttendances.length > 0 && !loading">
                    <tr
                        v-for="attendance in paginatedAttendances"
                        :key="attendance.id"
                        class="hover:bg-brand-gray/50"
                    >
                        <td class="py-4 px-6">
                            <EmployeeCell
                                :photo="attendance.staff_member?.user?.profile_photo"
                                :name="attendance.staff_member?.user?.name || ''"
                                :subtitle="attendance.staff_member?.staff_member_id || ''"
                            />
                        </td>

                        <td class="py-4 px-6 text-center">
                            <span class="text-sm text-brand-dark font-medium">
                                {{ formatDate(attendance.date) }}
                            </span>
                        </td>

                        <td class="py-4 px-6 text-center">
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-brand-border/20 rounded-lg border border-brand-border">
                                <Clock class="w-3.5 h-3.5 text-brand-light" />
                                <span class="text-sm font-medium text-brand-dark">
                                    {{ formatTime(attendance.check_in) }}
                                </span>
                            </div>
                        </td>

                        <td class="py-4 px-6 text-center">
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-brand-border/20 rounded-lg border border-brand-border">
                                <Clock class="w-3.5 h-3.5 text-brand-light" />
                                <span class="text-sm font-medium text-brand-dark">
                                    {{ formatTime(attendance.check_out) }}
                                </span>
                            </div>
                        </td>

                        <td class="py-4 px-6 text-center">
                            <StatusBadge type="attendance-status" :value="attendance.status" />
                        </td>
                    </tr>
                    </template>
                </tbody>
            </table>
        </DataTableCard>
    </div>
</template>
