import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import AuthLayout from "@/layouts/Auth.vue";
import AdminLayout from "@/layouts/Admin.vue";
import Login from "@/views/auth/Login.vue";
import ForgotPassword from "@/views/auth/ForgotPassword.vue";
import ResetPassword from "@/views/auth/ResetPassword.vue";
import VerifyEmailResult from "@/views/auth/VerifyEmailResult.vue";
import AdminDashboard from "@/views/admin/Dashboard.vue";
import AdminSettings from "@/views/admin/Settings.vue";
import teamRoutes from "./team";
import meetingRoutes from "./meeting";
import staffMemberRoutes from "./staffMember";
import StaffMemberCreate from "@/views/admin/staff-member/StaffMemberCreate.vue";
import StaffMemberEdit from "@/views/admin/staff-member/StaffMemberEdit.vue";
import StaffMemberDetail from "@/views/admin/staff-member/StaffMemberDetail.vue";
import StaffMemberProfile from "@/views/staff-member/StaffMemberProfile.vue";
import StaffMemberTeam from "@/views/staff-member/StaffMemberTeam.vue";
import StaffMemberCreateLayout from "@/layouts/StaffMemberCreateLayout.vue";
import projectRoutes from "./project";
import attendanceRoutes from "./attendance";
import payrollRoutes from "./payroll";
import analyticsRoutes from "./analytics";
import performanceRoutes from "./performance";
import { hasRoutePermissionAccess } from "./permissionAccess";

export const appRoutes = [
  {
    path: "/",
    redirect: "/admin/dashboard",
  },
  {
    path: "/admin",
    component: AdminLayout,
    meta: {
      requiresAuth: true,
    },
    children: [
      {
        path: "dashboard",
        name: "admin.dashboard",
        component: AdminDashboard,
        meta: {
          requiredPermission: "dashboard-menu",
        },
      },
      {
        path: "settings",
        name: "admin.settings",
        component: AdminSettings,
        meta: {
          requiredAnyPermissions: ["payroll-statistics", "attendance-menu", "review-cycle-manage"],
        },
      },
      {
        path: "notifications",
        name: "admin.notifications",
        component: () => import("@/views/admin/Notifications.vue"),
        meta: {
          allowAuthenticated: true,
        },
      },
      ...teamRoutes,
      ...meetingRoutes,
      ...staffMemberRoutes,
      ...projectRoutes,
      ...attendanceRoutes,
      ...payrollRoutes,
      ...analyticsRoutes,
      ...performanceRoutes,
      {
        path: "my-profile",
        name: "staffMember.profile",
        component: StaffMemberProfile,
        meta: {
          requiredPermission: "profile-menu",
        },
      },
      {
        path: "my-profile/edit",
        name: "staffMember.profile.edit",
        component: () => import("@/views/staff-member/StaffMemberProfileEdit.vue"),
        meta: {
          requiredPermission: "profile-menu",
        },
      },
      {
        path: "my-team",
        name: "staffMember.team",
        component: StaffMemberTeam,
        meta: {
          requiredPermission: "team-view",
        },
      },
    ],
  },
  {
    path: "/admin/staff-members/create",
    component: StaffMemberCreateLayout,
    meta: {
      requiresAuth: true,
      requiredPermission: "staff-member-create",
    },
    children: [
      {
        path: "",
        name: "admin.staffMembers.create",
        component: StaffMemberCreate,
      },
    ],
  },
  {
    path: "/admin/staff-members/:id/edit",
    component: StaffMemberCreateLayout,
    meta: {
      requiresAuth: true,
      requiredPermission: "staff-member-edit",
    },
    children: [
      {
        path: "",
        name: "admin.staffMembers.edit",
        component: StaffMemberEdit,
      },
    ],
  },
  {
    path: "/admin/staff-members/:id",
    component: AdminLayout,
    meta: {
      requiresAuth: true,
      requiredPermission: "staff-member-menu",
    },
    children: [
      {
        path: "",
        name: "admin.staffMembers.detail",
        component: StaffMemberDetail,
      },
    ],
  },
  {
    path: "/auth",
    component: AuthLayout,
    children: [
      {
        path: "login",
        name: "login",
        component: Login,
      },
      {
        path: "forgot-password",
        name: "forgot-password",
        component: ForgotPassword,
      },
      {
        path: "reset-password",
        name: "reset-password",
        component: ResetPassword,
      },
      {
        path: "verify-email",
        name: "verify-email",
        component: VerifyEmailResult,
      },
    ],
  },
  {
    path: "/:pathMatch(.*)*",
    name: "not-found",
    component: () => import("@/views/NotFound.vue"),
  },
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to, from, savedPosition) {
    // Scroll the main content area to top
    return new Promise((resolve) => {
      setTimeout(() => {
        const mainContent = document.querySelector(".main-content");
        if (mainContent) {
          mainContent.scrollTop = 0;
        }
        resolve({ top: 0, left: 0 });
      }, 100);
    });
  },
  routes: appRoutes,
});

export const registerAuthGuard = (targetRouter) => {
  targetRouter.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    if (to.meta.requiresAuth) {
      if (authStore.token) {
        try {
          if (!authStore.user) {
            await authStore.checkAuth();
          }

          if (!hasRoutePermissionAccess(authStore.user?.permissions, to.meta)) {
            next({ name: "admin.dashboard" });
            return;
          }

          next();
        } catch (error) {
          next({ name: "login" });
        }
      } else {
        next({ name: "login" });
      }
    } else if (to.meta.requiresUnauth && authStore.token) {
      next({ name: "admin.dashboard" });
    } else {
      next();
    }
  });
};

registerAuthGuard(router);

export default router;
