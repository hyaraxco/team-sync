export default [
    {
        path: "attendances",
        name: "admin.attendances",
        component: () => import("@/views/admin/attendance/AttendanceList.vue"),
        meta: {
            requiredPermission: "attendance-menu",
        },
    },
    {
        path: "attendance-settings",
        name: "admin.attendance.settings",
        component: () => import("@/views/admin/attendance/AttendanceSettings.vue"),
        meta: {
            requiredPermission: "attendance-menu",
        },
    },
    {
        path: "attendance-periods",
        name: "admin.attendance.periods",
        component: () => import("@/views/admin/attendance/AttendancePeriods.vue"),
        meta: {
            requiredPermission: "attendance-menu",
        },
    },
    {
        path: "attendance-policy-mismatches",
        name: "admin.attendance.mismatches",
        component: () => import("@/views/admin/attendance/PolicyMismatches.vue"),
        meta: {
            requiredPermission: "attendance-menu",
        },
    },
    {
        path: "attendance-corrections",
        name: "admin.attendance.corrections",
        component: () => import("@/views/admin/attendance/AttendanceCorrectionList.vue"),
        meta: {
            requiredPermission: "attendance-correction-list",
        },
    },
    {
        path: "attendance-records",
        name: "admin.attendance.records",
        component: () => import("@/views/admin/attendance/AttendanceRecordList.vue"),
        meta: {
            requiredPermission: "attendance-list",
        },
    },
    {
        path: "leave-requests",
        name: "admin.attendance.leave-requests",
        component: () => import("@/views/admin/attendance/LeaveRequestList.vue"),
        meta: {
            requiredPermission: "leave-request-list",
        },
    },
    {
        path: "holiday-calendar",
        name: "admin.attendance.holidays",
        component: () => import("@/views/admin/attendance/HolidayCalendar.vue"),
        meta: {
            requiredPermission: "attendance-menu",
        },
    },
    {
        path: "hybrid-schedules",
        name: "admin.attendance.hybrid-schedules",
        component: () => import("@/views/admin/attendance/HybridScheduleList.vue"),
        meta: {
            requiredPermission: "attendance-menu",
        },
    },
    {
        path: "overtime",
        name: "admin.attendance.overtime",
        component: () => import("@/views/admin/attendance/OvertimeManagement.vue"),
        meta: {
            requiredPermission: "overtime-list",
        },
    },
    {
        path: "attendance/my-attendances",
        name: "staffMember.attendance.my-attendances",
        component: () => import("@/views/staff-member/MyAttendance.vue"),
        meta: {
            requiredAnyPermissions: ["attendance-my-attendances", "attendance-check-in", "attendance-check-out"],
        },
    },
    {
        path: "attendance/my-hybrid-schedule",
        name: "staffMember.attendance.my-hybrid-schedule",
        component: () => import("@/views/staff-member/HybridSchedules.vue"),
        meta: {
            requiredAnyPermissions: ["attendance-my-attendances"],
        },
    },
    {
        path: "attendance/my-overtime",
        name: "staffMember.attendance.my-overtime",
        component: () => import("@/views/staff-member/MyOvertime.vue"),
        meta: {
            requiredAnyPermissions: ["attendance-my-attendances", "overtime-list", "overtime-create"],
        },
    },
    {
        path: "attendance/clock",
        name: "staffMember.attendance.clock",
        meta: {
            requiredAnyPermissions: ["attendance-check-in", "attendance-check-out"],
        },
        beforeEnter: () => ({
            name: "staffMember.attendance.my-attendances",
            query: { action: "clock" },
        }),
    },
];
