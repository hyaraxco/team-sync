<script setup>
import { ref } from "vue";
import { CalendarCheck, CalendarDays, Clock, Clock3, ChevronDown, ChevronUp } from "lucide-vue-next";
import AnimatedValue from "@/components/common/AnimatedValue.vue";

defineProps({
    statistics: {
        type: Object,
        required: true,
    },
    pendingRequestsCount: {
        type: Number,
        default: 0,
    },
    leaveBalances: {
        type: Array,
        default: () => [],
    },
    leaveLoading: {
        type: Boolean,
        default: false,
    },
});

const showLeaveBreakdown = ref(false);

const formatLeaveType = (type) => {
    return type.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
};
</script>

<template>
    <div class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- This Month Attendance -->
            <div
                class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-brand-dark text-sm font-medium">This Month</p>
                        <p class="text-brand-dark text-2xl font-extrabold leading-tight my-1">
                            <AnimatedValue :value="statistics.present_days" />
                            /{{ statistics.total_days }}
                        </p>
                        <p class="text-success text-xs font-medium">Working days present</p>
                    </div>
                    <div class="w-12 h-12 bg-green-50 rounded-[12px] flex items-center justify-center">
                        <CalendarCheck class="w-6 h-6 text-green-600" />
                    </div>
                </div>
            </div>

            <!-- Leave Balance (clickable to expand breakdown) -->
            <div
                class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5 cursor-pointer"
                @click="showLeaveBreakdown = !showLeaveBreakdown"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-brand-dark text-sm font-medium">Leave Balance</p>
                        <p class="text-brand-dark text-2xl font-extrabold leading-tight my-1">
                            <AnimatedValue :value="statistics.leave_balance ?? 0" />
                        </p>
                        <p class="text-brand-light text-xs font-medium flex items-center gap-1">
                            Days remaining
                            <component :is="showLeaveBreakdown ? ChevronUp : ChevronDown" class="w-3 h-3" />
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center">
                        <CalendarDays class="w-6 h-6 text-blue-600" />
                    </div>
                </div>
            </div>

            <!-- Pending Requests -->
            <div
                class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-brand-dark text-sm font-medium">Pending Requests</p>
                        <p class="text-brand-dark text-2xl font-extrabold leading-tight my-1">
                            <AnimatedValue :value="pendingRequestsCount" />
                        </p>
                        <p class="text-warning text-xs font-medium">Awaiting approval</p>
                    </div>
                    <div class="w-12 h-12 bg-orange-50 rounded-[12px] flex items-center justify-center">
                        <Clock class="w-6 h-6 text-orange-600" />
                    </div>
                </div>
            </div>

            <!-- Average Hours -->
            <div
                class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-brand-dark text-sm font-medium">Average Hours</p>
                        <p class="text-brand-dark text-2xl font-extrabold leading-tight my-1">
                            {{ statistics.avg_hours ? statistics.avg_hours + "h" : "0h" }}
                        </p>
                        <p class="text-success text-xs font-medium">Daily average</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-50 rounded-[12px] flex items-center justify-center">
                        <Clock3 class="w-6 h-6 text-purple-600" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Leave Breakdown (expandable) -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-2 max-h-0"
            enter-to-class="opacity-100 translate-y-0 max-h-[500px]"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0 max-h-[500px]"
            leave-to-class="opacity-0 -translate-y-2 max-h-0"
        >
            <div
                v-if="showLeaveBreakdown"
                class="mt-4 bg-white border border-[#DCDEDD] rounded-[20px] p-5 overflow-hidden"
            >
                <p class="text-brand-dark text-sm font-semibold mb-3">Leave Breakdown</p>

                <div v-if="leaveLoading" class="text-center py-3">
                    <p class="text-brand-light text-sm">Loading...</p>
                </div>

                <div v-else-if="leaveBalances.length === 0" class="text-center py-3">
                    <p class="text-brand-light text-sm">No entitlements found</p>
                </div>

                <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div
                        v-for="balance in leaveBalances"
                        :key="balance.leave_type"
                        class="border border-[#DCDEDD] rounded-[12px] p-3"
                    >
                        <p class="text-brand-dark text-sm font-semibold mb-1">
                            {{ formatLeaveType(balance.leave_type) }}
                        </p>
                        <div v-if="balance.quota_days !== null" class="flex items-baseline gap-1">
                            <span class="text-brand-dark text-lg font-bold">{{ balance.remaining_days }}</span>
                            <span class="text-brand-light text-xs">/ {{ balance.quota_days }}</span>
                        </div>
                        <div v-else>
                            <span class="text-brand-dark text-lg font-bold">∞</span>
                            <span class="text-brand-light text-xs ml-1">{{ balance.used_days }} used</span>
                        </div>
                        <div v-if="balance.quota_days !== null" class="w-full bg-gray-200 rounded-full h-1 mt-2">
                            <div
                                class="bg-blue-600 h-1 rounded-full"
                                :style="{ width: `${(balance.remaining_days / balance.quota_days) * 100}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
