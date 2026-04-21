import PayrollDashboard from '@/views/admin/payroll/PayrollDashboard.vue';
import PayrollCreate from '@/views/admin/payroll/PayrollCreate.vue';
import PayrollDetail from '@/views/admin/payroll/PayrollDetail.vue';
import PayrollSettings from '@/views/admin/payroll/PayrollSettings.vue';
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
