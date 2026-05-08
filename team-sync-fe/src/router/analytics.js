export default [
    {
        path: "analytics",
        name: "admin.analytics",
        component: () => import("@/views/admin/analytics/AnalyticsDashboard.vue"),
        meta: {
            requiredPermission: "analytics-menu",
        },
    },
];
