<script setup>
import { onMounted, computed } from "vue";
import { useDashboardStore } from "@/stores/dashboard";
import { storeToRefs } from "pinia";
import { Clock, AlertCircle } from "lucide-vue-next";
import { DEFAULT_AVATAR } from "@/helpers/format";

const dashboardStore = useDashboardStore();
const { todayAttendance, todayAttendanceLoading } = storeToRefs(dashboardStore);

onMounted(() => {
    dashboardStore.fetchTodayAttendance();
});

const checkedInPercentage = computed(() => {
    if (!todayAttendance.value || !todayAttendance.value.total_employees) return 0;
    return Math.round((todayAttendance.value.checked_in_count / todayAttendance.value.total_employees) * 100);
});

const formatTime = (dateString) => {
    if (!dateString) return "-";
    const d = new Date(dateString);
    return d.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit", hour12: false });
};

const statusConfig = {
    present: { label: "On Time", color: "bg-green-100 text-green-700", dot: "bg-green-500" },
    late: { label: "Late", color: "bg-amber-100 text-amber-700", dot: "bg-amber-500" },
    remote: { label: "Remote", color: "bg-blue-100 text-blue-700", dot: "bg-blue-500" },
    half_day: { label: "Half Day", color: "bg-orange-100 text-orange-700", dot: "bg-orange-500" },
    on_leave: { label: "On Leave", color: "bg-purple-100 text-purple-700", dot: "bg-purple-500" },
    not_checked_in: { label: "Not In", color: "bg-gray-100 text-gray-500", dot: "bg-gray-400" },
};

const getStatusConfig = (status) => statusConfig[status] || statusConfig["present"];
</script>

<template>
    <div class="bg-white border border-brand-border rounded-2xl p-4 sm:p-5">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 bg-gradient-to-br from-brand-primary to-brand-primary rounded-xl flex items-center justify-center"
                >
                    <Clock class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h3 class="text-brand-dark text-lg font-bold">Today's Attendance</h3>
                    <p class="text-brand-light text-xs">Real-time overview</p>
                </div>
            </div>
            <div v-if="todayAttendance" class="text-right">
                <span class="text-2xl font-extrabold text-brand-dark">{{ checkedInPercentage }}%</span>
                <p class="text-xs text-brand-light">checked in</p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="todayAttendanceLoading" class="space-y-3">
            <div v-for="i in 4" :key="i" class="flex items-center gap-3 animate-pulse">
                <div class="w-9 h-9 bg-gray-200 rounded-full"></div>
                <div class="flex-1">
                    <div class="h-3 bg-gray-200 rounded w-1/3 mb-1"></div>
                    <div class="h-2 bg-gray-200 rounded w-1/2"></div>
                </div>
                <div class="w-14 h-5 bg-gray-200 rounded-full"></div>
            </div>
        </div>

        <template v-else-if="todayAttendance">
            <!-- Progress Bar -->
            <div class="mb-4">
                <div class="flex items-center justify-between text-xs text-brand-light mb-1.5">
                    <span>
                        {{ todayAttendance.checked_in_count }} / {{ todayAttendance.total_employees }} employees
                    </span>
                    <span>{{ todayAttendance.not_checked_in_count }} missing</span>
                </div>
                <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-brand-primary to-brand-primary rounded-full transition-all duration-500"
                        :style="{ width: checkedInPercentage + '%' }"
                    ></div>
                </div>
            </div>

            <!-- Status Breakdown Pills -->
            <div class="flex flex-wrap gap-2 mb-4">
                <span
                    v-if="todayAttendance.status_breakdown.present > 0"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700"
                >
                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                    {{ todayAttendance.status_breakdown.present }} On Time
                </span>
                <span
                    v-if="todayAttendance.status_breakdown.late > 0"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700"
                >
                    <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span>
                    {{ todayAttendance.status_breakdown.late }} Late
                </span>
                <span
                    v-if="todayAttendance.status_breakdown.remote > 0"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700"
                >
                    <span class="w-1.5 h-1.5 bg-blue-500 rounded-full"></span>
                    {{ todayAttendance.status_breakdown.remote }} Remote
                </span>
                <span
                    v-if="todayAttendance.status_breakdown.on_leave > 0"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-50 text-purple-700"
                >
                    <span class="w-1.5 h-1.5 bg-purple-500 rounded-full"></span>
                    {{ todayAttendance.status_breakdown.on_leave }} On Leave
                </span>
            </div>

            <!-- Staff Member Lists -->
            <div class="space-y-2 max-h-[280px] overflow-auto">
                <!-- Checked In -->
                <div
                    v-for="employee in todayAttendance.checked_in"
                    :key="'in-' + employee.id"
                    class="flex items-center gap-3 py-1.5"
                >
                    <div class="relative">
                        <img loading="lazy"
                            :src="employee.profile_photo || DEFAULT_AVATAR"
                            :alt="employee.name"
                            class="w-8 h-8 rounded-full object-cover"
                        />
                        <span
                            class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-white"
                            :class="getStatusConfig(employee.status).dot"
                        ></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-brand-dark truncate">{{ employee.name }}</p>
                        <p class="text-xs text-brand-light truncate">{{ employee.position || "-" }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="employee.check_in" class="text-xs text-brand-light font-medium">
                            {{ formatTime(employee.check_in) }}
                        </span>
                        <span
                            :class="getStatusConfig(employee.status).color"
                            class="px-2 py-0.5 rounded-full text-[10px] font-semibold whitespace-nowrap"
                        >
                            {{ getStatusConfig(employee.status).label }}
                        </span>
                    </div>
                </div>

                <!-- Not Checked In -->
                <template v-if="todayAttendance.not_checked_in.length > 0">
                    <div class="border-t border-dashed border-gray-200 pt-2 mt-2">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Not Checked In</p>
                    </div>
                    <div
                        v-for="employee in todayAttendance.not_checked_in"
                        :key="'out-' + employee.id"
                        class="flex items-center gap-3 py-1.5 opacity-60"
                    >
                        <div class="relative">
                            <img loading="lazy"
                                :src="employee.profile_photo || DEFAULT_AVATAR"
                                :alt="employee.name"
                                class="w-8 h-8 rounded-full object-cover grayscale"
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-500 truncate">{{ employee.name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ employee.position || "-" }}</p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500">
                            Missing
                        </span>
                    </div>
                </template>
            </div>
        </template>

        <!-- No Data -->
        <div v-else class="text-center py-8">
            <AlertCircle class="w-8 h-8 text-gray-300 mx-auto mb-2" />
            <p class="text-sm text-brand-light">No attendance data available</p>
        </div>
    </div>
</template>
