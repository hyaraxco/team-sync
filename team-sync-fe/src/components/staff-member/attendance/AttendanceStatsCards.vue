<script setup>
import { CalendarCheck, CalendarDays, Clock, Clock3 } from "lucide-vue-next";
import AnimatedValue from "@/components/common/AnimatedValue.vue";

const props = defineProps({
    statistics: {
        type: Object,
        required: true,
    },
    pendingRequestsCount: {
        type: Number,
        default: 0,
    },
});
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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

        <!-- Leave Balance -->
        <div
            class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-brand-dark text-sm font-medium">Leave Balance</p>
                    <p class="text-brand-dark text-2xl font-extrabold leading-tight my-1">
                        <AnimatedValue :value="statistics.leave_balance ?? 0" />
                    </p>
                    <p class="text-brand-light text-xs font-medium">Days remaining</p>
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
</template>
