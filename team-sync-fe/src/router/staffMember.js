import StaffMemberList from "@/views/admin/staff-member/StaffMemberList.vue";
import StaffMemberSuccess from "@/views/admin/staff-member/StaffMemberSuccess.vue";

export default [
    {
        path: 'staff-members',
        name: 'admin.staffMembers',
        component: StaffMemberList,
        meta: {
            requiredPermission: 'staff-member-menu',
        },
    },
    {
        path: 'staff-members/success',
        name: 'admin.staffMembers.success',
        component: StaffMemberSuccess,
        meta: {
            requiredPermission: 'staff-member-create',
        },
    }
];
