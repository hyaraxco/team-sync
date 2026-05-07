import PayrollDashboard from '@/views/admin/payroll/PayrollDashboard.vue';
import PayrollCreate from '@/views/admin/payroll/PayrollCreate.vue';
import PayrollDetail from '@/views/admin/payroll/PayrollDetail.vue';
import PayrollSettings from '@/views/admin/payroll/PayrollSettings.vue';
import ThrManagement from '@/views/admin/payroll/ThrManagement.vue';
import MyPayslips from '@/views/staff-member/MyPayslips.vue';
import PayslipDetail from '@/views/staff-member/PayslipDetail.vue';

export default [
    {
        path: 'payroll',
        name: 'admin.payroll.dashboard',
        component: PayrollDashboard,
        meta: {
            requiredPermission: 'payroll-menu',
            requiresAuth: true,
        },
    },
    {
        path: 'payroll/readiness',
        name: 'admin.payroll.readiness',
        component: () => import('@/views/admin/payroll/PayrollReadiness.vue'),
        meta: {
            requiredPermission: 'payroll-create',
            requiresAuth: true,
        },
    },
    {
        path: 'payroll/create',
        name: 'admin.payroll.create',
        component: PayrollCreate,
        meta: {
            requiredPermission: 'payroll-create',
            requiresAuth: true,
        },
    },
    {
        path: 'payroll/settings',
        name: 'admin.payroll.settings',
        component: PayrollSettings,
        meta: {
            requiredPermission: 'payroll-statistics',
            requiresAuth: true,
        },
    },
    {
        path: 'payroll/approval-matrix',
        name: 'admin.payroll.approval-matrix',
        component: () => import('@/views/admin/payroll/PayrollApprovalMatrix.vue'),
        meta: {
            requiredPermission: 'payroll-statistics',
            requiresAuth: true,
        },
    },
    {
        path: 'payroll/adjustments',
        name: 'admin.payroll.adjustments',
        component: () => import('@/views/admin/payroll/PayrollAdjustmentQueue.vue'),
        meta: {
            requiredPermission: 'payroll-menu',
            requiresAuth: true,
        },
    },
    {
        path: 'payroll/comparison',
        name: 'admin.payroll.comparison',
        component: () => import('@/views/admin/payroll/PayrollComparison.vue'),
        meta: {
            requiredPermission: 'payroll-statistics',
            requiresAuth: true,
        },
    },
    {
        path: 'payroll/thr',
        name: 'admin.payroll.thr',
        component: ThrManagement,
        meta: {
            requiredPermission: 'thr-list',
            requiresAuth: true,
        },
    },
    {
        path: 'payroll/thr/:id',
        name: 'admin.payroll.thr.detail',
        component: () => import('@/views/admin/payroll/ThrManagement.vue'),
        meta: {
            requiredPermission: 'thr-list',
            requiresAuth: true,
        },
    },
    {
        path: 'payroll/:id',
        name: 'admin.payroll.detail',
        component: PayrollDetail,
        meta: {
            requiredPermission: 'payroll-list',
            requiresAuth: true,
        },
    },
    {
        path: 'my-payroll',
        name: 'staffMember.payroll',
        component: MyPayslips,
        meta: {
            requiredPermission: 'payslip-view',
            requiresAuth: true,
        },
    },
    {
        path: 'my-payroll/:id',
        name: 'staffMember.payroll.detail',
        component: PayslipDetail,
        meta: {
            requiredPermission: 'payslip-view',
            requiresAuth: true,
        },
    },
    {
        path: 'my-payslips',
        name: 'staffMember.payslips',
        redirect: {
            name: 'staffMember.payroll',
        },
        meta: {
            requiredPermission: 'payslip-view',
            requiresAuth: true,
        },
    },
    {
        path: 'my-payslips/:id',
        name: 'staffMember.payslips.detail',
        redirect: to => ({
            name: 'staffMember.payroll.detail',
            params: to.params,
        }),
        meta: {
            requiredPermission: 'payslip-view',
            requiresAuth: true,
        },
    }
];
