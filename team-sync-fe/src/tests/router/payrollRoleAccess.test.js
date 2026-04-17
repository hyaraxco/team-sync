import { describe, expect, it, vi } from "vitest";
import { hasRoutePermissionAccess } from "@/router/permissionAccess";

vi.mock("@/views/admin/payroll/PayrollDashboard.vue", () => ({
  default: {},
}));

vi.mock("@/views/admin/payroll/PayrollCreate.vue", () => ({
  default: {},
}));

vi.mock("@/views/admin/payroll/PayrollDetail.vue", () => ({
  default: {},
}));

vi.mock("@/views/admin/payroll/PayrollSettings.vue", () => ({
  default: {},
}));

vi.mock("@/views/employee/MyPayslips.vue", () => ({
  default: {},
}));

vi.mock("@/views/employee/PayslipDetail.vue", () => ({
  default: {},
}));

import payrollRoutes from "@/router/payroll";

const routeMeta = (name) =>
  payrollRoutes.find((route) => route.name === name)?.meta ?? {};

const rolePermissions = {
  manager: [
    "dashboard-menu",
    "dashboard-view",
    "team-menu",
    "employee-menu",
    "attendance-menu",
  ],
  hr: ["payroll-menu", "payroll-list", "payroll-create"],
  finance: [
    "payroll-menu",
    "payroll-list",
    "payroll-edit",
    "payroll-process",
    "payroll-statistics",
  ],
  employee: [
    "dashboard-menu",
    "dashboard-view",
    "attendance-my-attendances",
    "attendance-check-in",
    "attendance-check-out",
    "leave-request-create",
    "payslip-view",
  ],
};

describe("payroll route access matrix", () => {
  it("blocks manager and employee from admin payroll routes", () => {
    expect(
      hasRoutePermissionAccess(
        rolePermissions.manager,
        routeMeta("admin.payroll.dashboard")
      )
    ).toBe(false);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.manager,
        routeMeta("admin.payroll.create")
      )
    ).toBe(false);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.manager,
        routeMeta("admin.payroll.detail")
      )
    ).toBe(false);

    expect(
      hasRoutePermissionAccess(
        rolePermissions.employee,
        routeMeta("admin.payroll.dashboard")
      )
    ).toBe(false);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.employee,
        routeMeta("admin.payroll.create")
      )
    ).toBe(false);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.employee,
        routeMeta("admin.payroll.detail")
      )
    ).toBe(false);
  });

  it("allows HR to enter dashboard, create, and detail routes", () => {
    expect(
      hasRoutePermissionAccess(
        rolePermissions.hr,
        routeMeta("admin.payroll.dashboard")
      )
    ).toBe(true);
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
  });

  it("allows Finance to enter dashboard and detail but not create", () => {
    expect(
      hasRoutePermissionAccess(
        rolePermissions.finance,
        routeMeta("admin.payroll.dashboard")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.finance,
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
        routeMeta("admin.payroll.settings")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.hr,
        routeMeta("admin.payroll.settings")
      )
    ).toBe(false);
  });

  it("allows employee payroll routes only when payslip-view is granted", () => {
    expect(
      hasRoutePermissionAccess(
        rolePermissions.employee,
        routeMeta("employee.payroll")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        rolePermissions.employee,
        routeMeta("employee.payroll.detail")
      )
    ).toBe(true);
    expect(
      hasRoutePermissionAccess(
        ["dashboard-menu", "dashboard-view"],
        routeMeta("employee.payroll")
      )
    ).toBe(false);
  });
});
