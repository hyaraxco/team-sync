import { describe, expect, it } from "vitest";
import { appRoutes } from "@/router/index";
import { hasRoutePermissionAccess } from "@/router/permissionAccess";

const flattenRoutes = (routes, parentMeta = {}) =>
  routes.flatMap((route) => {
    const effectiveMeta = {
      ...parentMeta,
      ...(route.meta ?? {}),
    };

    const current = route.name
      ? [{ name: route.name, meta: effectiveMeta }]
      : [];

    if (!Array.isArray(route.children) || route.children.length === 0) {
      return current;
    }

    return [...current, ...flattenRoutes(route.children, effectiveMeta)];
  });

const routeEntries = flattenRoutes(appRoutes);
const routeMeta = (name) =>
  routeEntries.find((route) => route.name === name)?.meta ?? {};

const rolePermissions = {
  manager: [
    "dashboard-menu",
    "team-menu",
    "team-create",
    "team-edit",
    "staff-member-menu",
    "staff-member-create",
    "staff-member-edit",
    "project-menu",
    "project-list",
    "project-create",
    "project-edit",
    "attendance-menu",
    "profile-menu",
    "profile-view",
    "attendance-my-attendances",
    "attendance-last-attendance",
    "attendance-check-in",
    "attendance-check-out",
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
  ],
  hr: [
    "dashboard-menu",
    "team-menu",
    "team-create",
    "team-edit",
    "staff-member-menu",
    "staff-member-create",
    "staff-member-edit",
    "project-menu",
    "project-list",
    "project-create",
    "project-edit",
    "attendance-menu",
    "payroll-menu",
    "payroll-list",
    "payroll-create",
    "profile-menu",
    "profile-view",
    "attendance-my-attendances",
    "attendance-last-attendance",
    "attendance-check-in",
    "attendance-check-out",
    "leave-request-menu",
    "leave-request-create",
    "leave-request-my-requests",
    "payslip-view",
    "performance-menu",
    "review-cycle-manage",
    "review-calibrate",
    "review-self-submit",
    "review-manager-submit",
    "goal-create-own",
    "goal-assign-team",
    "feedback-give",
  ],
  finance: [
    "dashboard-menu",
    "staff-member-menu",
    "staff-member-list",
    "attendance-menu",
    "payroll-menu",
    "payroll-list",
    "payroll-edit",
    "payroll-process",
    "payroll-statistics",
    "profile-menu",
    "profile-view",
    "attendance-my-attendances",
    "attendance-last-attendance",
    "attendance-check-in",
    "attendance-check-out",
    "leave-request-menu",
    "leave-request-create",
    "leave-request-my-requests",
    "payslip-view",
  ],
  staff: [
    "dashboard-menu",
    "profile-menu",
    "team-view",
    "attendance-my-attendances",
    "attendance-check-in",
    "attendance-check-out",
    "payslip-view",
    "project-menu",
    "project-list",
    "performance-menu",
    "review-self-submit",
    "goal-create-own",
    "feedback-give",
  ],
};

describe("feature guard matrix", () => {
  it("requires explicit permission guard for every named authenticated route", () => {
    const protectedNamedRoutes = routeEntries.filter(
      (route) => route.meta.requiresAuth
    );

    expect(protectedNamedRoutes.length).toBeGreaterThan(0);

    for (const route of protectedNamedRoutes) {
      const hasGuard =
        Boolean(route.meta.requiredPermission) ||
        (Array.isArray(route.meta.requiredAnyPermissions) &&
          route.meta.requiredAnyPermissions.length > 0) ||
        route.meta.allowAuthenticated === true;

      expect(hasGuard).toBe(true);
    }
  });

  it("enforces role access for admin core features", () => {
    expect(
      hasRoutePermissionAccess(
        rolePermissions.manager,
        routeMeta("admin.dashboard")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(rolePermissions.finance, routeMeta("admin.teams"))
    ).toBe(false);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.staff,
        routeMeta("admin.staffMembers")
      )
    ).toBe(false);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.finance,
        routeMeta("admin.projects")
      )
    ).toBe(false);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.staff,
        routeMeta("admin.projects")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.staff,
        routeMeta("admin.attendances")
      )
    ).toBe(false);
  });

  it("enforces role access for payroll and employee self-service", () => {
    expect(
      hasRoutePermissionAccess(
        rolePermissions.hr,
        routeMeta("admin.payroll.create")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.hr,
        routeMeta("admin.payroll.detail")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.finance,
        routeMeta("admin.payroll.create")
      )
    ).toBe(false);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.finance,
        routeMeta("admin.payroll.detail")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.manager,
        routeMeta("admin.payroll.detail")
      )
    ).toBe(false);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.staff,
        routeMeta("staffMember.payroll")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.manager,
        routeMeta("staffMember.payroll")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.finance,
        routeMeta("staffMember.payroll")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.hr,
        routeMeta("staffMember.payroll")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.staff,
        routeMeta("staffMember.profile")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.finance,
        routeMeta("staffMember.profile")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.manager,
        routeMeta("staffMember.attendance.my-attendances")
      )
    ).toBe(true);
  });

  it("enforces role access for performance calibration", () => {
    expect(
      hasRoutePermissionAccess(
        rolePermissions.hr,
        routeMeta("admin.performance.pending-calibration")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.manager,
        routeMeta("admin.performance.pending-calibration")
      )
    ).toBe(false);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.employee,
        routeMeta("admin.performance.pending-calibration")
      )
    ).toBe(false);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.finance,
        routeMeta("admin.performance.pending-calibration")
      )
    ).toBe(false);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.hr,
        routeMeta("admin.performance.cycles")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.manager,
        routeMeta("admin.performance.cycles")
      )
    ).toBe(false);
  });
});
