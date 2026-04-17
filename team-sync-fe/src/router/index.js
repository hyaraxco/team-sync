import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AuthLayout from '@/layouts/Auth.vue'
import AdminLayout from '@/layouts/Admin.vue'
import Login from '@/views/auth/Login.vue'
import ForgotPassword from '@/views/auth/ForgotPassword.vue'
import ResetPassword from '@/views/auth/ResetPassword.vue'
import VerifyEmailResult from '@/views/auth/VerifyEmailResult.vue'
import AdminDashboard from '@/views/admin/Dashboard.vue'
import teamRoutes from './team';
import employeeRoutes from './employee';
import EmployeeCreate from '@/views/admin/employee/EmployeeCreate.vue';
import EmployeeEdit from '@/views/admin/employee/EmployeeEdit.vue';
import EmployeeDetail from '@/views/admin/employee/EmployeeDetail.vue';
import EmployeeProfile from '@/views/employee/EmployeeProfile.vue';
import EmployeeTeam from '@/views/employee/EmployeeTeam.vue';
import EmployeeCreateLayout from '@/layouts/EmployeeCreateLayout.vue';
import projectRoutes from './project';
import attendanceRoutes from './attendance';
import payrollRoutes from './payroll';
import analyticsRoutes from './analytics';
import { hasRoutePermissionAccess } from './permissionAccess';

export const appRoutes = [
  {
    path: '/',
    redirect: '/admin/dashboard',
  },
  {
    path: '/admin',
    component: AdminLayout,
    meta: {
      requiresAuth: true,
    },
    children: [
      {
        path: 'dashboard',
        name: 'admin.dashboard',
        component: AdminDashboard,
        meta: {
          requiredPermission: 'dashboard-menu',
        },
      },
      {
        path: 'notifications',
        name: 'admin.notifications',
        component: () => import('@/views/admin/Notifications.vue'),
        meta: {
          allowAuthenticated: true,
        },
      },
      ...teamRoutes,
      ...employeeRoutes,
      ...projectRoutes,
      ...attendanceRoutes,
      ...payrollRoutes,
      ...analyticsRoutes,
      {
        path: 'my-profile',
        name: 'employee.profile',
        component: EmployeeProfile,
        meta: {
          requiredPermission: 'profile-menu',
        },
      },
      {
        path: 'my-profile/edit',
        name: 'employee.profile.edit',
        component: () => import('@/views/employee/EmployeeProfileEdit.vue'),
        meta: {
          requiredPermission: 'profile-menu',
        },
      },
      {
        path: 'my-team',
        name: 'employee.team',
        component: EmployeeTeam,
        meta: {
          requiredPermission: 'team-view',
        },
      }
    ],
  },
  {
    path: '/admin/employees/create',
    component: EmployeeCreateLayout,
    meta: {
      requiresAuth: true,
      requiredPermission: 'employee-create',
    },
    children: [
      {
        path: '',
        name: 'admin.employees.create',
        component: EmployeeCreate
      }
    ]
  },
  {
    path: '/admin/employees/:id/edit',
    component: EmployeeCreateLayout,
    meta: {
      requiresAuth: true,
      requiredPermission: 'employee-edit',
    },
    children: [
      {
        path: '',
        name: 'admin.employees.edit',
        component: EmployeeEdit
      }
    ]
  },
  {
    path: '/admin/employees/:id',
    component: AdminLayout,
    meta: {
      requiresAuth: true,
      requiredPermission: 'employee-menu',
    },
    children: [
      {
        path: '',
        name: 'admin.employees.detail',
        component: EmployeeDetail
      }
    ]
  },
  {
    path: '/auth',
    component: AuthLayout,
    children: [
      {
        path: 'login',
        name: 'login',
        component: Login,
      },
      {
        path: 'forgot-password',
        name: 'forgot-password',
        component: ForgotPassword,
      },
      {
        path: 'reset-password',
        name: 'reset-password',
        component: ResetPassword,
      },
      {
        path: 'verify-email',
        name: 'verify-email',
        component: VerifyEmailResult,
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFound.vue'),
  },
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to, from, savedPosition) {
    // Scroll the main content area to top
    return new Promise((resolve) => {
      setTimeout(() => {
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
          mainContent.scrollTop = 0;
        }
        resolve({ top: 0, left: 0 });
      }, 100);
    });
  },
  routes: appRoutes,
})

export const registerAuthGuard = (targetRouter) => {
  targetRouter.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore()

    if (to.meta.requiresAuth) {
      if (authStore.token) {
        try {
          if (!authStore.user) {
            await authStore.checkAuth()
          }

          if (!hasRoutePermissionAccess(authStore.user?.permissions, to.meta)) {
            next({ name: 'admin.dashboard' })
            return
          }

          next()
        } catch (error) {
          next({ name: 'login' })
        }
      } else {
        next({ name: 'login' })
      }
    } else if (to.meta.requiresUnauth && authStore.token) {
      next({ name: 'admin.dashboard' })
    } else {
      next()
    }
  })
}

registerAuthGuard(router)


export default router
