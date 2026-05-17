export default [
    {
        path: "payroll",
        name: "admin.payroll.dashboard",
        component: () => import("@/views/admin/payroll/PayrollDashboard.vue"),
        meta: {
            requiredPermission: "payroll-menu",
            requiresAuth: true,
        },
    },
    {
        path: "payroll/readiness",
        name: "admin.payroll.readiness",
        component: () => import("@/views/admin/payroll/PayrollReadiness.vue"),
        meta: {
            requiredAnyPermissions: ["payroll-create", "payroll-readiness-view"],
            requiresAuth: true,
        },
    },
    {
        path: "payroll/create",
        name: "admin.payroll.create",
        component: () => import("@/views/admin/payroll/PayrollCreate.vue"),
        meta: {
            requiredPermission: "payroll-create",
            requiresAuth: true,
        },
    },
    {
        path: "payroll/settings",
        name: "admin.payroll.settings",
        component: () => import("@/views/admin/payroll/PayrollSettings.vue"),
        meta: {
            requiredPermission: "settings-finance-manage",
            requiresAuth: true,
        },
    },
    {
        path: "payroll/approval-matrix",
        name: "admin.payroll.approval-matrix",
        component: () => import("@/views/admin/payroll/PayrollApprovalMatrix.vue"),
        meta: {
            requiredPermission: "settings-finance-manage",
            requiresAuth: true,
        },
    },
    {
        path: "payroll/adjustments",
        name: "admin.payroll.adjustments",
        component: () => import("@/views/admin/payroll/PayrollAdjustmentQueue.vue"),
        meta: {
            requiredPermission: "payroll-edit",
            requiresAuth: true,
        },
    },
    {
        path: "payroll/comparison",
        name: "admin.payroll.comparison",
        component: () => import("@/views/admin/payroll/PayrollComparison.vue"),
        meta: {
            requiredPermission: "payroll-list",
            requiresAuth: true,
        },
    },
    {
        path: "payroll/thr",
        name: "admin.payroll.thr",
        component: () => import("@/views/admin/payroll/ThrManagement.vue"),
        meta: {
            requiredPermission: "thr-list",
            requiresAuth: true,
        },
    },
    {
        path: "payroll/thr/:id",
        name: "admin.payroll.thr.detail",
        component: () => import("@/views/admin/payroll/ThrManagement.vue"),
        meta: {
            requiredPermission: "thr-list",
            requiresAuth: true,
        },
    },
    {
        path: "payroll/:id",
        name: "admin.payroll.detail",
        component: () => import("@/views/admin/payroll/PayrollDetail.vue"),
        meta: {
            requiredPermission: "payroll-list",
            requiresAuth: true,
        },
    },
    {
        path: "my-payroll",
        name: "staffMember.payroll",
        component: () => import("@/views/staff-member/MyPayslips.vue"),
        meta: {
            requiredPermission: "payslip-view",
            requiresAuth: true,
        },
    },
    {
        path: "my-payroll/:id",
        name: "staffMember.payroll.detail",
        component: () => import("@/views/staff-member/PayslipDetail.vue"),
        meta: {
            requiredPermission: "payslip-view",
            requiresAuth: true,
        },
    },
    {
        path: "my-payslips",
        name: "staffMember.payslips",
        redirect: {
            name: "staffMember.payroll",
        },
        meta: {
            requiredPermission: "payslip-view",
            requiresAuth: true,
        },
    },
    {
        path: "my-payslips/:id",
        name: "staffMember.payslips.detail",
        redirect: (to) => ({
            name: "staffMember.payroll.detail",
            params: to.params,
        }),
        meta: {
            requiredPermission: "payslip-view",
            requiresAuth: true,
        },
    },
];
