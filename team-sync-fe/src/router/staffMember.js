export default [
    {
        path: "staff-members",
        name: "staffMember.list",
        component: () => import("@/views/admin/staff-member/StaffMemberList.vue"),
        meta: {
            requiredPermission: "staff-member-menu",
        },
    },
    {
        path: "staff-members/:id",
        name: "staffMember.detail",
        component: () => import("@/views/admin/staff-member/StaffMemberDetail.vue"),
        meta: {
            requiredPermission: "staff-member-view",
        },
    },
];
