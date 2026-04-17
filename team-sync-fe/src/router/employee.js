import EmployeeList from "@/views/admin/employee/EmployeeList.vue";
import EmployeeSuccess from "@/views/admin/employee/EmployeeSuccess.vue";

export default [
    {
        path: 'employees',
        name: 'admin.employees',
        component: EmployeeList,
        meta: {
            requiredPermission: 'employee-menu',
        },
    },
    {
        path: 'employees/success',
        name: 'admin.employees.success',
        component: EmployeeSuccess,
        meta: {
            requiredPermission: 'employee-create',
        },
    }
];
