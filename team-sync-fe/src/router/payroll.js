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
            requiredPermission: "payroll-menu",
        },
    },
    {
        path: "payroll/create",
        name: "admin.payroll.create",
        component: () => import("@/views/admin/payroll/PayrollCreate.vue"),
        meta: {
            requiredPermission: "payroll-generate",
        },
    },
    {
        path: "payroll/:id",
        name: "admin.payroll.detail",
        component: () => import("@/views/admin/payroll/PayrollDetail.vue"),
        meta: {
            requiredPermission: "payroll-view",
        },
    },
    {
        path: "payroll/settings",
        name: "admin.payroll.settings",
        component: () => import("@/views/admin/payroll/PayrollSettings.vue"),
        meta: {
            requiredPermission: "payroll-settings-view",
        },
    },
    {
        path: "payroll/comparison",
        name: "admin.payroll.comparison",
        component: () => import("@/views/admin/payroll/PayrollComparison.vue"),
        meta: {
            requiredPermission: "payroll-menu",
        },
    },
    {
        path: "payroll/approval-matrix",
        name: "admin.payroll.approval-matrix",
        component: () => import("@/views/admin/payroll/PayrollApprovalMatrix.vue"),
        meta: {
            requiredPermission: "payroll-menu",
        },
    },
    {
        path: "payroll/adjustment-queue",
        name: "admin.payroll.adjustment-queue",
        component: () => import("@/views/admin/payroll/PayrollAdjustmentQueue.vue"),
        meta: {
            requiredPermission: "payroll-menu",
        },
    },
    {
        path: "thr",
        name: "admin.thr.management",
        component: () => import("@/views/admin/payroll/ThrManagement.vue"),
        meta: {
            requiredPermission: "thr-menu",
        },
    },
    {
        path: "payroll/my-payslips",
        name: "staffMember.payroll.my-payslips",
        component: () => import("@/views/staff-member/MyPayslips.vue"),
        meta: {
            requiredAnyPermissions: ["payroll-my-payslips"],
        },
    },
    {
        path: "payroll/my-payslips/:id",
        name: "staffMember.payroll.payslip-detail",
        component: () => import("@/views/staff-member/PayslipDetail.vue"),
        meta: {
            requiredAnyPermissions: ["payroll-my-payslips"],
        },
    },
];
