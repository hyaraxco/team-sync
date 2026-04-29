<script setup>
import { 
    WalletIcon, 
    CalendarIcon, 
    ClockIcon, 
    ShieldCheckIcon,
    FileTextIcon,
    UsersIcon,
    AwardIcon,
    ChevronRightIcon
} from "lucide-vue-next";
import { RouterLink } from "vue-router";
import { can } from "@/helpers/permissionHelper";

const settingsSections = [
    {
        title: "Payroll & Finance",
        description: "Configure payroll rules, tax brackets, and BPJS rates.",
        items: [
            {
                title: "Payroll Settings",
                description: "Manage salary components and global payroll rules.",
                icon: WalletIcon,
                routeName: "admin.payroll.settings",
                permission: "payroll-statistics"
            }
        ]
    },
    {
        title: "Attendance & Time",
        description: "Manage working hours, holiday calendars, and attendance policies.",
        items: [
            {
                title: "Attendance Policy",
                description: "Configure grace periods, late penalties, and clock-in rules.",
                icon: ClockIcon,
                routeName: "admin.attendance.settings",
                permission: "attendance-menu"
            },
            {
                title: "Attendance Periods",
                description: "Define monthly attendance cycle dates.",
                icon: CalendarIcon,
                routeName: "admin.attendance.periods",
                permission: "attendance-menu"
            },
            {
                title: "Holiday Calendar",
                description: "Manage public holidays and office closures.",
                icon: CalendarIcon,
                routeName: "admin.attendances", // Using this as placeholder for holidays if specific route not found
                permission: "attendance-menu"
            }
        ]
    },
    {
        title: "Performance & Growth",
        description: "Manage review cycles, templates, and outcome rules.",
        items: [
            {
                title: "Review Cycles",
                description: "Manage active and upcoming performance review periods.",
                icon: ShieldCheckIcon,
                routeName: "admin.performance.cycles",
                permission: "review-cycle-manage"
            },
            {
                title: "Outcome Rules",
                description: "Configure performance-to-payroll outcome mappings.",
                icon: AwardIcon,
                routeName: "admin.performance.outcome-rules",
                permission: "review-cycle-manage"
            },
            {
                title: "Review Templates",
                description: "Design and manage performance review forms.",
                icon: FileTextIcon,
                routeName: "admin.performance.templates",
                permission: "review-cycle-manage"
            }
        ]
    }
];

// Helper to check if a section has any visible items
const hasVisibleItems = (section) => {
    return section.items.some(item => !item.permission || can(item.permission));
};
</script>

<template>
    <div class="space-y-8 pb-10">
        <!-- Page Header -->
        <div>
            <h1 class="text-brand-dark text-2xl font-bold">Settings</h1>
            <p class="text-brand-light text-base font-normal">Manage your organization's configuration and policies.</p>
        </div>

        <!-- Settings Sections -->
        <div v-for="section in settingsSections" :key="section.title" v-show="hasVisibleItems(section)" class="space-y-4">
            <div class="border-b border-[#DCDEDD] pb-2">
                <h2 class="text-brand-dark text-lg font-bold">{{ section.title }}</h2>
                <p class="text-brand-light text-sm font-normal">{{ section.description }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <template v-for="item in section.items" :key="item.routeName">
                    <RouterLink 
                        v-if="!item.permission || can(item.permission)"
                        :to="{ name: item.routeName }"
                        class="group bg-white border border-[#DCDEDD] rounded-[20px] p-5 hover:border-[#0C51D9] hover:border-2 transition-all duration-300 flex flex-col justify-between"
                    >
                        <div class="space-y-4">
                            <div class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center group-hover:bg-blue-100 transition-colors">
                                <component :is="item.icon" class="w-6 h-6 text-[#0C51D9]" />
                            </div>
                            <div>
                                <h3 class="text-brand-dark text-base font-bold">{{ item.title }}</h3>
                                <p class="text-brand-light text-sm font-normal mt-1">{{ item.description }}</p>
                            </div>
                        </div>
                        <div class="mt-6 flex items-center text-[#0C51D9] text-sm font-semibold">
                            <span>Configure</span>
                            <ChevronRightIcon class="w-4 h-4 ml-1 transform group-hover:translate-x-1 transition-transform" />
                        </div>
                    </RouterLink>
                </template>
            </div>
        </div>
    </div>
</template>
