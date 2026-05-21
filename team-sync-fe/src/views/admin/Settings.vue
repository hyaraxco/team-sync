<script setup>
import {
    WalletIcon,
    CalendarIcon,
    ClockIcon,
    ShieldCheckIcon,
    FileTextIcon,
    AwardIcon,
    ChevronRightIcon,
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
                permission: "settings-finance-manage",
            },
        ],
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
                permission: "settings-hr-manage",
            },
            {
                title: "Attendance Periods",
                description: "Define monthly attendance cycle dates.",
                icon: CalendarIcon,
                routeName: "admin.attendance.periods",
                permission: "settings-hr-manage",
            },
            {
                title: "Holiday Calendar",
                description: "Manage public holidays and office closures.",
                icon: CalendarIcon,
                routeName: "admin.attendance.holidays",
                permission: "settings-hr-manage",
            },
        ],
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
                permission: "settings-hr-manage",
            },
            {
                title: "Outcome Rules",
                description: "Configure performance-to-payroll outcome mappings.",
                icon: AwardIcon,
                routeName: "admin.performance.outcome-rules",
                permission: "settings-hr-manage",
            },
            {
                title: "Review Templates",
                description: "Design and manage performance review forms.",
                icon: FileTextIcon,
                routeName: "admin.performance.templates",
                permission: "settings-hr-manage",
            },
        ],
    },
];

// Helper to check if a section has any visible items
const hasVisibleItems = (section) => {
    return section.items.some((item) => !item.permission || can(item.permission));
};
</script>

<template>
    <div class="space-y-8 pb-10">
        <!-- Page Header -->
        <div>
            <h1 class="text-brand-dark text-2xl font-bold">Pengaturan</h1>
            <p class="text-brand-light text-base font-normal">Manage your organization's configuration and policies.</p>
        </div>

        <!-- Settings Sections -->
        <div
            v-for="section in settingsSections"
            :key="section.title"
            v-show="hasVisibleItems(section)"
            class="space-y-4"
        >
            <div class="border-b border-brand-border pb-2">
                <h2 class="text-brand-dark text-lg font-bold">{{ section.title }}</h2>
                <p class="text-brand-light text-sm font-normal">{{ section.description }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <template v-for="item in section.items" :key="item.routeName">
                    <RouterLink
                        v-if="!item.permission || can(item.permission)"
                        :to="{ name: item.routeName }"
                        class="group bg-white border border-brand-border rounded-2xl p-5 hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 flex flex-col justify-between"
                    >
                        <div class="space-y-4">
                            <div
                                class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center group-hover:bg-blue-100 transition-colors"
                            >
                                <component :is="item.icon" class="w-6 h-6 text-brand-primary" />
                            </div>
                            <div>
                                <h3 class="text-brand-dark text-base font-bold">{{ item.title }}</h3>
                                <p class="text-brand-light text-sm font-normal mt-1">{{ item.description }}</p>
                            </div>
                        </div>
                        <div class="mt-6 flex items-center text-brand-primary text-sm font-semibold">
                            <span>Configure</span>
                            <ChevronRightIcon
                                class="w-4 h-4 ml-1 transform group-hover:translate-x-1 transition-transform"
                            />
                        </div>
                    </RouterLink>
                </template>
            </div>
        </div>
    </div>
</template>
