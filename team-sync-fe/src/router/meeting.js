export default [
    {
        path: '/admin/meetings',
        name: 'admin.meetings',
        component: () => import('@/views/admin/meeting/MeetingList.vue'),
        meta: {
            requiredPermission: 'meeting-list',
        },
    },
];
