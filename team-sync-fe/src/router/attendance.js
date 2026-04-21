import MyAttendance from '@/views/staff-member/MyAttendance.vue';
import AttendanceList from '@/views/admin/attendance/AttendanceList.vue';
import AttendanceCorrectionList from '@/views/admin/attendance/AttendanceCorrectionList.vue';
import LeaveRequestList from '@/views/admin/attendance/LeaveRequestList.vue';

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
        path: 'attendance/my-attendances',
        name: 'staffMember.attendance.my-attendances',
        component: MyAttendance,
        meta: {
            requiredAnyPermissions: ['attendance-my-attendances', 'attendance-check-in', 'attendance-check-out'],
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
