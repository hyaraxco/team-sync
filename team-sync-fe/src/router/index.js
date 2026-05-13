import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import teamRoutes from "./team";
import meetingRoutes from "./meeting";
import staffMemberRoutes from "./staffMember";
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
        component: () => import("@/layouts/Admin.vue"),
        meta: {
            requiresAuth: true,
        },
        children: [
            {
                path: "dashboard",
                name: "admin.dashboard",
                component: () => import("@/views/admin/Dashboard.vue"),
                meta: {
                    requiredPermission: "dashboard-menu",
                },
            },
            {
                path: "settings",
                name: "admin.settings",
                component: () => import("@/views/admin/Settings.vue"),
                meta: {
                    requiredPermission: "settings-menu",
                },
            },
            {
                path: "upgrade-plan",
                name: "admin.upgrade-plan",
                component: () => import("@/views/admin/UpgradePlan.vue"),
                meta: {
                    requiredPermission: "dashboard-menu",
                },
            },
            {
                path: "staff-members",
                name: "admin.staffMembers",
                component: () => import("@/views/admin/staff-member/StaffMemberList.vue"),
                meta: {
                    requiredPermission: "staff-member-menu",
                },
            },
            {
                path: "staff-members/create",
                name: "admin.staffMember.create",
                component: () => import("@/layouts/StaffMemberCreateLayout.vue"),
                meta: {
                    requiredPermission: "staff-member-create",
                },
                children: [
                    {
                        path: "",
                        name: "admin.staffMember.create.form",
                        component: () => import("@/views/admin/staff-member/StaffMemberCreate.vue"),
                    },
                ],
            },
            {
                path: "staff-members/:id/edit",
                name: "admin.staffMember.edit",
                component: () => import("@/views/admin/staff-member/StaffMemberEdit.vue"),
                meta: {
                    requiredPermission: "staff-member-edit",
                },
            },
            {
                path: "staff-members/:id",
                name: "admin.staffMember.detail",
                component: () => import("@/views/admin/staff-member/StaffMemberDetail.vue"),
                meta: {
                    requiredPermission: "staff-member-view",
                },
            },
            ...teamRoutes,
            ...meetingRoutes,
            ...projectRoutes,
            ...attendanceRoutes,
            ...payrollRoutes,
            ...analyticsRoutes,
            ...performanceRoutes,
        ],
    },
    {
        path: "/staff",
        component: () => import("@/layouts/Admin.vue"),
        meta: {
            requiresAuth: true,
        },
        children: [
            {
                path: "profile",
                name: "staff.profile",
                component: () => import("@/views/staff-member/StaffMemberProfile.vue"),
                meta: {
                    requiredPermission: "employee-self-service",
                },
            },
            {
                path: "profile/edit",
                name: "staff.profile.edit",
                component: () => import("@/views/staff-member/StaffMemberProfileEdit.vue"),
                meta: {
                    requiredPermission: "employee-self-service",
                },
            },
            {
                path: "team",
                name: "staff.team",
                component: () => import("@/views/staff-member/StaffMemberTeam.vue"),
                meta: {
                    requiredPermission: "employee-self-service",
                },
            },
            ...staffMemberRoutes,
        ],
    },
    {
        path: "/auth",
        component: () => import("@/layouts/Auth.vue"),
        children: [
            {
                path: "login",
                name: "auth.login",
                component: () => import("@/views/auth/Login.vue"),
            },
            {
                path: "forgot-password",
                name: "auth.forgotPassword",
                component: () => import("@/views/auth/ForgotPassword.vue"),
            },
            {
                path: "reset-password",
                name: "auth.resetPassword",
                component: () => import("@/views/auth/ResetPassword.vue"),
            },
            {
                path: "verify-email",
                name: "auth.verifyEmail",
                component: () => import("@/views/auth/VerifyEmailResult.vue"),
            },
        ],
    },
    {
        path: "/setup",
        name: "setup",
        component: () => import("@/views/setup/SetupWizard.vue"),
    },
    {
        path: "/:pathMatch(.*)*",
        name: "notFound",
        component: () => import("@/views/NotFound.vue"),
    },
];

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: appRoutes,
    scrollBehavior(_to, _from, _savedPosition) {
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
});

router.beforeEach(async (to, _from, next) => {
    const authStore = useAuthStore();

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return next({ name: "auth.login", query: { redirect: to.fullPath } });
    }

    if (to.name === "auth.login" && authStore.isAuthenticated) {
        return next({ name: "admin.dashboard" });
    }

    if (to.meta.requiredPermission || to.meta.requiredAnyPermissions) {
        const hasAccess = hasRoutePermissionAccess(to, authStore.permissions);
        if (!hasAccess) {
            return next({ name: "admin.dashboard" });
        }
    }

    next();
});

export default router;
