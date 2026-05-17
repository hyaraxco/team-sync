export default [
    {
        path: "staff-members",
        name: "admin.staffMembers",
        component: () => import("@/views/admin/staff-member/StaffMemberList.vue"),
        meta: {
            requiredPermission: "staff-member-menu",
        },
    },
    {
        path: "staff-members/success",
        name: "admin.staffMembers.success",
        component: () => import("@/views/admin/staff-member/StaffMemberSuccess.vue"),
        meta: {
            requiredPermission: "staff-member-create",
        },
    },
];
