export default [
    {
        path: "/admin/projects",
        name: "admin.projects",
        component: () => import("@/views/admin/project/ProjectList.vue"),
        meta: {
            requiredPermission: "project-menu",
        },
    },
    {
        path: "/admin/projects/:id",
        name: "admin.projects.detail",
        component: () => import("@/views/admin/project/ProjectDetail.vue"),
        meta: {
            requiredPermission: "project-list",
        },
    },
    {
        path: "/admin/projects/create",
        name: "admin.projects.create",
        component: () => import("@/views/admin/project/ProjectCreate.vue"),
        meta: {
            requiredPermission: "project-create",
        },
    },
    {
        path: "/admin/projects/:id/edit",
        name: "admin.projects.edit",
        component: () => import("@/views/admin/project/ProjectEdit.vue"),
        meta: {
            requiredPermission: "project-edit",
        },
    },
];
