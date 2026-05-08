import TeamList from "@/views/admin/team/TeamList.vue";
import TeamCreate from "@/views/admin/team/TeamCreate.vue";
import TeamEdit from "@/views/admin/team/TeamEdit.vue";
import TeamDetail from "@/views/admin/team/TeamDetail.vue";

// team routes
export default [
    {
        path: "/admin/teams",
        name: "admin.teams",
        component: TeamList,
        meta: {
            requiredPermission: "team-menu",
        },
    },
    {
        path: "/admin/teams/create",
        name: "admin.team.create",
        component: TeamCreate,
        meta: {
            requiredPermission: "team-create",
        },
    },
    {
        path: "/admin/teams/:id",
        name: "admin.team.detail",
        component: TeamDetail,
        meta: {
            requiredPermission: "team-menu",
        },
    },
    {
        path: "/admin/teams/edit/:id",
        name: "admin.team.edit",
        component: TeamEdit,
        meta: {
            requiredPermission: "team-edit",
        },
    },
];
