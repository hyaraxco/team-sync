export default [
    {
        path: "projects",
        name: "admin.projects",
        component: () => import("@/views/admin/project/ProjectList.vue"),
        meta: {
            requiredPermission: "project-menu",
        },
    },
    {
        path: "projects/create",
        name: "admin.project.create",
        component: () => import("@/views/admin/project/ProjectCreate.vue"),
        meta: {
            requiredPermission: "project-create",
        },
    },
    {
        path: "projects/:id",
        name: "admin.project.detail",
        component: () => import("@/views/admin/project/ProjectDetail.vue"),
        meta: {
            requiredPermission: "project-view",
        },
    },
    {
        path: "projects/:id/edit",
        name: "admin.project.edit",
        component: () => import("@/views/admin/project/ProjectEdit.vue"),
        meta: {
            requiredPermission: "project-edit",
        },
    },
];
