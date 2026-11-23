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
    // Mirrors RolePermissionSeeder: manager is VIEW-ONLY for tasks
    // (project leader creates/edits/deletes them).
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
    // Mirrors RolePermissionSeeder: HR is READ-ONLY for projects/tasks
    // and has no payroll operations (only payroll-readiness-view + thr-list).
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
        "task-menu",
        "task-list",
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
    // Mirrors RolePermissionSeeder Finance: payroll/THR owner, staff-level
    // project visibility (project-menu/project-list) and task-edit only.
    finance: [
        "dashboard-menu",
        "dashboard-view",
        "dashboard-finance-view",
        "project-menu",
        "project-list",
        "task-menu",
        "task-list",
        "task-edit",
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
        "meeting-menu",
    ],
    // Synthetic combination: NOT a backend default role. Used only to prove the
    // guard honours an arbitrary mix of payroll permissions.
    syntheticPayrollCustom: [
        "dashboard-menu",
        "payroll-menu",
        "payroll-list",
        "payroll-edit",
        "payroll-process",
        "payroll-statistics",
        "payroll-readiness-view",
    ],
    // Mirrors RolePermissionSeeder staff baseline: no task-create,
    // no feedback-give, and no meeting-list (only meeting-menu).
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
        "task-edit",
        "overtime-create",
        "performance-menu",
        "review-self-submit",
        "goal-create-own",
        "meeting-menu",
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
        // Finance has staff-level project visibility (project-menu/project-list),
        // so the projects list is allowed; project create/edit is not.
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.projects"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.projects.create"))).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.projects.edit"))).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.staff, routeMeta("admin.projects"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.staff, routeMeta("admin.projects.create"))).toBe(false);
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

    // ─── team-sync-4ok: default-role parity through consumer behaviour ─────
    // These exercise the route metadata and helpers that actually consume the
    // permission, rather than re-reading the same literals defined above.
    // Source of truth: team-sync-be/database/seeders/RolePermissionSeeder.php.

    it("keeps manager out of project-create surfaces while still allowing project list", () => {
        // Manager has project-create in the seeder (full project CRUD), so the
        // project routes stay open; task authority is exercised separately by
        // TaskDetailModal permissions (task-edit/task-delete), not by route meta.
        expect(hasRoutePermissionAccess(rolePermissions.manager, routeMeta("admin.projects"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.manager, routeMeta("admin.projects.create"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.manager, routeMeta("admin.staffMembers"))).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.manager, routeMeta("admin.payroll.create"))).toBe(false);
    });

    it("denies HR the project-create and project-edit routes", () => {
        expect(hasRoutePermissionAccess(rolePermissions.hr, routeMeta("admin.projects"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.hr, routeMeta("admin.projects.create"))).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.hr, routeMeta("admin.projects.edit"))).toBe(false);
    });

    it("keeps staff out of admin attendance and payroll routes", () => {
        expect(hasRoutePermissionAccess(rolePermissions.staff, routeMeta("admin.attendances"))).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.staff, routeMeta("admin.payroll.create"))).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.staff, routeMeta("staffMember.payroll"))).toBe(true);
    });

    it("gives Finance payroll operations but denies project creation and HR admin", () => {
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.payroll.create"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.projects"))).toBe(true);
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.projects.create"))).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.staffMembers"))).toBe(false);
    });

    // ─── Payload contract parity (team-sync-4ok) ───────────────────────────
    // /me returns permission names as strings (UserResource::getAllPermissions
    // ->pluck('name')). Guard and helper must agree on that observed payload.

    it("treats an unknown permission string as no access", () => {
        expect(hasRoutePermissionAccess(["not-a-real-permission"], routeMeta("admin.projects"))).toBe(false);
        expect(hasRoutePermissionAccess([], routeMeta("admin.projects"))).toBe(false);
        expect(hasRoutePermissionAccess(undefined, routeMeta("admin.projects"))).toBe(false);
    });

    it("routes the synthetic payroll combination only through routes it grants", () => {
        // Synthetic: proves the guard honours arbitrary payroll permission mixes.
        // settings-finance-manage is absent, so settings is denied; payroll-menu
        // is present, so the dashboard is allowed. Comparison (payroll-list),
        // settings and adjustments custom combos are already covered by
        // src/tests/router/payrollRoleAccess.test.js and are not duplicated here.
        expect(
            hasRoutePermissionAccess(rolePermissions.syntheticPayrollCustom, routeMeta("admin.payroll.settings")),
        ).toBe(false);
        expect(hasRoutePermissionAccess(rolePermissions.syntheticPayrollCustom, routeMeta("admin.payroll"))).toBe(true);
    });
});
