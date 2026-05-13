export default [
    {
        path: "teams",
        name: "admin.teams",
        component: () => import("@/views/admin/team/TeamList.vue"),
        meta: {
            requiredPermission: "team-menu",
        },
    },
    {
        path: "teams/create",
        name: "admin.team.create",
        component: () => import("@/views/admin/team/TeamCreate.vue"),
        meta: {
            requiredPermission: "team-create",
        },
    },
    {
        path: "teams/:id",
        name: "admin.team.detail",
        component: () => import("@/views/admin/team/TeamDetail.vue"),
        meta: {
            requiredPermission: "team-view",
        },
    },
    {
        path: "teams/:id/edit",
        name: "admin.team.edit",
        component: () => import("@/views/admin/team/TeamEdit.vue"),
        meta: {
            requiredPermission: "team-edit",
        },
    },
];
