import ProjectList from "@/views/admin/project/ProjectList.vue";
import ProjectCreate from "@/views/admin/project/ProjectCreate.vue";
import ProjectEdit from "@/views/admin/project/ProjectEdit.vue";
import ProjectDetail from "@/views/admin/project/ProjectDetail.vue";

export default [
    {
        path: "/admin/projects",
        name: "admin.projects",
        component: ProjectList,
        meta: {
            requiredPermission: "project-menu",
        },
    },
    {
        path: "/admin/projects/:id",
        name: "admin.projects.detail",
        component: ProjectDetail,
        meta: {
            requiredPermission: "project-list",
        },
    },
    {
        path: "/admin/projects/create",
        name: "admin.projects.create",
        component: ProjectCreate,
        meta: {
            requiredPermission: "project-create",
        },
    },
    {
        path: "/admin/projects/:id/edit",
        name: "admin.projects.edit",
        component: ProjectEdit,
        meta: {
            requiredPermission: "project-edit",
        },
    },
];
