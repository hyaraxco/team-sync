import PayrollDashboard from '@/views/admin/payroll/PayrollDashboard.vue';
import PayrollCreate from '@/views/admin/payroll/PayrollCreate.vue';
import PayrollDetail from '@/views/admin/payroll/PayrollDetail.vue';
import PayrollSettings from '@/views/admin/payroll/PayrollSettings.vue';
import MyPayslips from '@/views/employee/MyPayslips.vue';
import PayslipDetail from '@/views/employee/PayslipDetail.vue';

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
        name: 'employee.payroll',
        component: MyPayslips,
        meta: {
            requiredPermission: 'payslip-view',
            requiresAuth: true,
        },
    },
    {
        path: 'my-payroll/:id',
        name: 'employee.payroll.detail',
        component: PayslipDetail,
        meta: {
            requiredPermission: 'payslip-view',
            requiresAuth: true,
        },
    },
    {
        path: 'my-payslips',
        name: 'employee.payslips',
        redirect: {
            name: 'employee.payroll',
        },
        meta: {
            requiredPermission: 'payslip-view',
            requiresAuth: true,
        },
    },
    {
        path: 'my-payslips/:id',
        name: 'employee.payslips.detail',
        redirect: to => ({
            name: 'employee.payroll.detail',
            params: to.params,
        }),
        meta: {
            requiredPermission: 'payslip-view',
            requiresAuth: true,
        },
    }
];
