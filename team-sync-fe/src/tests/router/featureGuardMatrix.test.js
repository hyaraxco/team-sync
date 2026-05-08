import { describe, expect, it } from "vitest";
import { appRoutes } from "@/router/index";
import { hasRoutePermissionAccess } from "@/router/permissionAccess";

const flattenRoutes = (routes, parentMeta = {}) =>
    routes.flatMap((route) => {
        const effectiveMeta = {
            ...parentMeta,
            ...(route.meta ?? {}),
        };

        const current = route.name ? [{ name: route.name, meta: effectiveMeta }] : [];

        if (!Array.isArray(route.children) || route.children.length === 0) {
            return current;
        }

        return [...current, ...flattenRoutes(route.children, effectiveMeta)];
    });

const routeEntries = flattenRoutes(appRoutes);
const routeMeta = (name) => routeEntries.find((route) => route.name === name)?.meta ?? {};

const rolePermissions = {
    manager: [
        "dashboard-menu",
        "dashboard-view",
        "dashboard-team-view",
        "team-menu",
        "team-list",
        "team-create",
        "team-edit",
        "team-view",
        "project-menu",
        "project-list",
        "project-create",
        "project-edit",
        "task-menu",
        "task-list",
        "task-create",
        "task-edit",
        "task-delete",
        "attendance-menu",
        "attendance-list",
        "attendance-correction-list",
        "attendance-correction-approve",
        "leave-request-list",
        "leave-request-approve",
        "overtime-list",
        "overtime-create",
        "overtime-approve",
        "profile-menu",
        "profile-view",
        "attendance-my-attendances",
        "attendance-my-statistics",
        "attendance-last-attendance",
        "attendance-check-in",
        "attendance-check-out",
        "attendance-correction-create",
        "leave-request-menu",
        "leave-request-create",
        "leave-request-my-requests",
        "payslip-view",
        "performance-menu",
        "review-self-submit",
        "review-manager-submit",
        "goal-create-own",
        "goal-assign-team",
        "feedback-give",
        "performance-analytics-view",
        "analytics-menu",
        "analytics-team-view",
        "analytics-performance-view",
        "analytics-project-view",
        "meeting-menu",
        "meeting-list",
    ],
    hr: [
        "dashboard-menu",
        "dashboard-view",
        "dashboard-hr-view",
        "dashboard-self-view",
        "team-menu",
        "team-list",
        "team-create",
        "team-edit",
        "team-view",
        "staff-member-menu",
        "staff-member-statistic",
        "staff-member-list",
        "staff-member-create",
        "staff-member-edit",
        "staff-member-delete",
        "project-menu",
        "project-list",
        "project-create",
        "project-edit",
        "task-menu",
        "task-list",
        "task-create",
        "task-edit",
        "attendance-menu",
        "attendance-list",
        "attendance-my-attendances",
        "attendance-my-statistics",
        "attendance-last-attendance",
        "attendance-check-in",
        "attendance-check-out",
        "attendance-correction-list",
        "attendance-correction-create",
        "attendance-correction-approve",
        "leave-request-menu",
        "leave-request-list",
        "leave-request-create",
        "leave-request-approve",
        "leave-request-my-requests",
        "profile-menu",
        "profile-view",
        "payslip-view",
        "performance-menu",
        "review-cycle-manage",
        "review-calibrate",
        "review-self-submit",
        "review-assign-reviewer",
        "goal-create-own",
        "goal-assign-team",
        "feedback-give",
        "performance-analytics-view",
        "analytics-menu",
        "analytics-view",
        "analytics-export",
        "analytics-hr-view",
        "analytics-performance-view",
        "analytics-project-view",
        "meeting-menu",
        "meeting-list",
        "meeting-create",
        "overtime-list",
        "overtime-create",
        "overtime-approve",
        "settings-hr-manage",
        "payroll-readiness-view",
        "thr-list",
    ],
    finance: [
        "dashboard-menu",
        "dashboard-view",
        "dashboard-finance-view",
        "payroll-menu",
        "payroll-list",
        "payroll-create",
        "payroll-edit",
        "payroll-delete",
        "payroll-process",
        "payroll-statistics",
        "payroll-readiness-view",
        "thr-list",
        "thr-generate",
        "thr-approve",
        "thr-process",
        "analytics-menu",
        "analytics-view",
        "analytics-export",
        "analytics-finance-view",
        "overtime-list",
        "meeting-list",
        "settings-finance-manage",
        "profile-menu",
        "profile-view",
        "attendance-my-attendances",
        "attendance-my-statistics",
        "attendance-last-attendance",
        "attendance-check-in",
        "attendance-check-out",
        "attendance-correction-create",
        "leave-request-menu",
        "leave-request-create",
        "leave-request-my-requests",
        "payslip-view",
        "performance-menu",
        "review-self-submit",
        "goal-create-own",
        "feedback-give",
        "meeting-menu",
    ],
    staff: [
        "dashboard-menu",
        "dashboard-view",
        "dashboard-self-view",
        "profile-menu",
        "profile-view",
        "team-view",
        "attendance-my-attendances",
        "attendance-my-statistics",
        "attendance-last-attendance",
        "attendance-check-in",
        "attendance-check-out",
        "attendance-correction-create",
        "leave-request-menu",
        "leave-request-create",
        "leave-request-my-requests",
        "payslip-view",
        "project-menu",
        "project-list",
        "task-menu",
        "task-list",
        "task-create",
        "task-edit",
        "overtime-create",
        "performance-menu",
        "review-self-submit",
        "goal-create-own",
        "feedback-give",
        "meeting-menu",
        "meeting-list",
    ],
};

describe("feature guard matrix", () => {
    it("requires explicit permission guard for every named authenticated route", () => {
        const protectedNamedRoutes = routeEntries.filter((route) => route.meta.requiresAuth);

        expect(protectedNamedRoutes.length).toBeGreaterThan(0);

        for (const route of protectedNamedRoutes) {
            const hasGuard =
                Boolean(route.meta.requiredPermission) ||
                (Array.isArray(route.meta.requiredAnyPermissions) && route.meta.requiredAnyPermissions.length > 0) ||
                route.meta.allowAuthenticated === true;

            expect(hasGuard).toBe(true);
        }
    });

    it("enforces role access for admin core features", () => {
        expect(hasRoutePermissionAccess(rolePermissions.manager, routeMeta("admin.dashboard"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.teams"))).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.staff, routeMeta("admin.staffMembers"))).toBe(false);
        // Finance no longer has staff-member-menu
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.staffMembers"))).toBe(false);
        // Manager no longer has staff-member-menu
        expect(hasRoutePermissionAccess(rolePermissions.manager, routeMeta("admin.staffMembers"))).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.projects"))).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.staff, routeMeta("admin.projects"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.staff, routeMeta("admin.attendances"))).toBe(false);
        // Finance no longer has attendance-menu
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.attendances"))).toBe(false);
    });

    it("enforces role access for payroll and employee self-service", () => {
        // HR no longer has payroll-create (Finance-owned)
        expect(hasRoutePermissionAccess(rolePermissions.hr, routeMeta("admin.payroll.create"))).toBe(false);
        // HR no longer has payroll-list
        expect(hasRoutePermissionAccess(rolePermissions.hr, routeMeta("admin.payroll.detail"))).toBe(false);
        // Finance now has payroll-create
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.payroll.create"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.payroll.detail"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.manager, routeMeta("admin.payroll.detail"))).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.staff, routeMeta("staffMember.payroll"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.manager, routeMeta("staffMember.payroll"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("staffMember.payroll"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.hr, routeMeta("staffMember.payroll"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.staff, routeMeta("staffMember.profile"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("staffMember.profile"))).toBe(true);
        expect(
            hasRoutePermissionAccess(rolePermissions.manager, routeMeta("staffMember.attendance.my-attendances")),
        ).toBe(true);
    });

    it("enforces role access for performance calibration", () => {
        expect(hasRoutePermissionAccess(rolePermissions.hr, routeMeta("admin.performance.pending-calibration"))).toBe(
            true,
        );
        expect(
            hasRoutePermissionAccess(rolePermissions.manager, routeMeta("admin.performance.pending-calibration")),
        ).toBe(false);
        expect(
            hasRoutePermissionAccess(rolePermissions.staff, routeMeta("admin.performance.pending-calibration")),
        ).toBe(false);
        expect(
            hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.performance.pending-calibration")),
        ).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.hr, routeMeta("admin.performance.cycles"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.manager, routeMeta("admin.performance.cycles"))).toBe(false);
    });

    // P6: outcome-rules route — HR only via review-cycle-manage
    it("enforces role access for performance outcome-rules (P6)", () => {
        expect(hasRoutePermissionAccess(rolePermissions.hr, routeMeta("admin.performance.outcome-rules"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.manager, routeMeta("admin.performance.outcome-rules"))).toBe(
            false,
        );
        expect(hasRoutePermissionAccess(rolePermissions.staff, routeMeta("admin.performance.outcome-rules"))).toBe(
            false,
        );
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.performance.outcome-rules"))).toBe(
            false,
        );
    });

    // P4: review cycle detail — HR can view (review-cycle-manage), Manager cannot
    it("enforces role access for performance cycle detail (P4)", () => {
        expect(hasRoutePermissionAccess(rolePermissions.hr, routeMeta("admin.performance.cycles.detail"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.manager, routeMeta("admin.performance.cycles.detail"))).toBe(
            false,
        );
        expect(hasRoutePermissionAccess(rolePermissions.staff, routeMeta("admin.performance.cycles.detail"))).toBe(
            false,
        );
    });

    // Settings: domain-scoped access
    it("enforces role access for settings (domain-scoped)", () => {
        // HR has settings-hr-manage
        expect(hasRoutePermissionAccess(rolePermissions.hr, routeMeta("admin.settings"))).toBe(true);
        // Finance has settings-finance-manage
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.settings"))).toBe(true);
        // Staff has no settings permissions
        expect(hasRoutePermissionAccess(rolePermissions.staff, routeMeta("admin.settings"))).toBe(false);
        // Manager has no settings permissions
        expect(hasRoutePermissionAccess(rolePermissions.manager, routeMeta("admin.settings"))).toBe(false);
    });

    // Payroll settings: Finance-owned
    it("enforces Finance-only access for payroll settings and approval matrix", () => {
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.payroll.settings"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.payroll.approval-matrix"))).toBe(
            true,
        );
        expect(hasRoutePermissionAccess(rolePermissions.hr, routeMeta("admin.payroll.settings"))).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.hr, routeMeta("admin.payroll.approval-matrix"))).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.manager, routeMeta("admin.payroll.settings"))).toBe(false);
    });

    // HR read-only payroll readiness
    it("allows HR read-only payroll readiness access", () => {
        expect(hasRoutePermissionAccess(rolePermissions.hr, routeMeta("admin.payroll.readiness"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.payroll.readiness"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.staff, routeMeta("admin.payroll.readiness"))).toBe(false);
    });
});
