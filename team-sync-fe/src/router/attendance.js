import MyAttendance from '@/views/staff-member/MyAttendance.vue';
import AttendanceList from '@/views/admin/attendance/AttendanceList.vue';
import AttendanceCorrectionList from '@/views/admin/attendance/AttendanceCorrectionList.vue';
import LeaveRequestList from '@/views/admin/attendance/LeaveRequestList.vue';
import AttendanceSettings from '@/views/admin/attendance/AttendanceSettings.vue';
import AttendancePeriods from '@/views/admin/attendance/AttendancePeriods.vue';
import PolicyMismatches from '@/views/admin/attendance/PolicyMismatches.vue';
import OvertimeManagement from '@/views/admin/attendance/OvertimeManagement.vue';

export default [
    {
        path: 'attendances',
        name: 'admin.attendances',
        component: AttendanceList,
        meta: {
            requiredPermission: 'attendance-menu',
        },
    },
    {
        path: 'attendance-settings',
        name: 'admin.attendance.settings',
        component: AttendanceSettings,
        meta: {
            requiredPermission: 'attendance-menu',
        },
    },
    {
        path: 'attendance-periods',
        name: 'admin.attendance.periods',
        component: AttendancePeriods,
        meta: {
            requiredPermission: 'attendance-menu',
        },
    },
    {
        path: 'attendance-policy-mismatches',
        name: 'admin.attendance.mismatches',
        component: PolicyMismatches,
        meta: {
            requiredPermission: 'attendance-menu',
        },
    },
    {
        path: 'attendance-corrections',
        name: 'admin.attendance.corrections',
        component: AttendanceCorrectionList,
        meta: {
            requiredPermission: 'attendance-correction-list',
        },
    },
    {
        path: 'attendance-records',
        name: 'admin.attendance.records',
        component: () => import('@/views/admin/attendance/AttendanceRecordList.vue'),
        meta: {
            requiredPermission: 'attendance-list',
        },
    },
    {
        path: 'leave-requests',
        name: 'admin.attendance.leave-requests',
        component: LeaveRequestList,
        meta: {
            requiredPermission: 'leave-request-list',
        },
    },
    {
        path: 'holiday-calendar',
        name: 'admin.attendance.holidays',
        component: () => import('@/views/admin/attendance/HolidayCalendar.vue'),
        meta: {
            requiredPermission: 'attendance-menu',
        },
    },
    {
        path: 'hybrid-schedules',
        name: 'admin.attendance.hybrid-schedules',
        component: () => import('@/views/admin/attendance/HybridScheduleList.vue'),
        meta: {
            requiredPermission: 'attendance-menu',
        },
    },
    {
        path: 'overtime',
        name: 'admin.attendance.overtime',
        component: OvertimeManagement,
        meta: {
            requiredPermission: 'overtime-list',
        },
    },
    {
        path: 'attendance/my-attendances',
        name: 'staffMember.attendance.my-attendances',
        component: MyAttendance,
        meta: {
            requiredAnyPermissions: ['attendance-my-attendances', 'attendance-check-in', 'attendance-check-out'],
        },
    },
    {
        path: 'attendance/my-hybrid-schedule',
        name: 'staffMember.attendance.my-hybrid-schedule',
        component: () => import('@/views/staff-member/HybridSchedules.vue'),
        meta: {
            requiredAnyPermissions: ['attendance-my-attendances'],
        },
    },
    {
        path: 'attendance/my-overtime',
        name: 'staffMember.attendance.my-overtime',
        component: () => import('@/views/staff-member/MyOvertime.vue'),
        meta: {
            requiredAnyPermissions: ['attendance-my-attendances', 'overtime-list', 'overtime-create'],
        },
    },
    {
        path: 'attendance/clock',
        name: 'staffMember.attendance.clock',
        meta: {
            requiredAnyPermissions: ['attendance-check-in', 'attendance-check-out'],
        },
        beforeEnter: () => ({
            name: 'staffMember.attendance.my-attendances',
            query: { action: 'clock' },
        }),
    },
];
