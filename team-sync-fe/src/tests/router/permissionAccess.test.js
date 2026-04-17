import { describe, expect, it } from "vitest";
import {
  hasRoutePermissionAccess,
  normalizePermissions,
} from "@/router/permissionAccess";

describe("permissionAccess", () => {
  it("normalizes string and object permission payloads", () => {
    expect(
      normalizePermissions(["payroll-list", { name: "payroll-process" }, null])
    ).toEqual(["payroll-list", "payroll-process"]);
  });

  it("allows public routes without permission meta", () => {
    expect(hasRoutePermissionAccess(["payroll-list"], {})).toBe(true);
  });

  it("denies authenticated routes without explicit permission guard", () => {
    expect(
      hasRoutePermissionAccess(["dashboard-menu"], {
        requiresAuth: true,
      })
    ).toBe(false);
  });

  it("allows explicit auth-only routes when allowAuthenticated flag is set", () => {
    expect(
      hasRoutePermissionAccess(["dashboard-menu"], {
        requiresAuth: true,
        allowAuthenticated: true,
      })
    ).toBe(true);
  });

  it("requires a specific permission when configured", () => {
    expect(
      hasRoutePermissionAccess(["payroll-list"], {
        requiredPermission: "payroll-list",
      })
    ).toBe(true);

    expect(
      hasRoutePermissionAccess(["payroll-list"], {
        requiredPermission: "payroll-create",
      })
    ).toBe(false);
  });

  it("supports any-of permission access", () => {
    expect(
      hasRoutePermissionAccess(["attendance-check-out"], {
        requiredAnyPermissions: ["attendance-check-in", "attendance-check-out"],
      })
    ).toBe(true);

    expect(
      hasRoutePermissionAccess(["attendance-list"], {
        requiredAnyPermissions: ["attendance-check-in", "attendance-check-out"],
      })
    ).toBe(false);
  });
});
