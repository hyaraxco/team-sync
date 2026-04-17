import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter } from "vue-router";
const authStoreMock = vi.hoisted(() => ({
  token: null,
  user: null,
  checkAuth: vi.fn(),
}));

vi.mock("@/stores/auth", () => ({
  useAuthStore: () => authStoreMock,
}));

import { appRoutes, registerAuthGuard } from "@/router/index";

const createTestRouter = () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: appRoutes,
  });

  registerAuthGuard(router);

  return router;
};

const setAuth = ({
  token = "test-token",
  permissions = [],
  withUser = true,
} = {}) => {
  authStoreMock.token = token;
  authStoreMock.user = withUser ? { permissions } : null;
  authStoreMock.checkAuth = vi.fn(async () => {
    authStoreMock.user = { permissions };
  });
};

const goTo = async (router, location) => {
  await router.push(location);
  await router.isReady();
};

describe("router guard integration", () => {
  beforeEach(() => {
    authStoreMock.token = null;
    authStoreMock.user = null;
    authStoreMock.checkAuth = vi.fn();
  });

  it("redirects unauthenticated users to login for protected routes", async () => {
    const router = createTestRouter();

    await goTo(router, "/admin/payroll");

    expect(router.currentRoute.value.name).toBe("login");
  });

  it("loads user through checkAuth when token exists and user is not hydrated", async () => {
    setAuth({
      token: "test-token",
      permissions: ["dashboard-menu", "payroll-menu"],
      withUser: false,
    });
    const router = createTestRouter();

    await goTo(router, "/admin/payroll");

    expect(authStoreMock.checkAuth).toHaveBeenCalledTimes(1);
    expect(router.currentRoute.value.name).toBe("admin.payroll.dashboard");
  });

  it("denies manager-style role from payroll admin pages", async () => {
    setAuth({
      permissions: ["dashboard-menu", "team-menu", "employee-menu"],
    });
    const router = createTestRouter();

    await goTo(router, "/admin/payroll");

    expect(router.currentRoute.value.name).toBe("admin.dashboard");
  });

  it("enforces team and employee-management routes by role", async () => {
    setAuth({
      permissions: ["dashboard-menu", "team-menu", "employee-menu"],
    });
    const managerRouter = createTestRouter();

    await goTo(managerRouter, "/admin/teams");
    expect(managerRouter.currentRoute.value.name).toBe("admin.teams");

    await goTo(managerRouter, "/admin/employees");
    expect(managerRouter.currentRoute.value.name).toBe("admin.employees");

    setAuth({
      permissions: ["dashboard-menu", "employee-menu", "attendance-menu"],
    });
    const financeRouter = createTestRouter();

    await goTo(financeRouter, "/admin/teams");
    expect(financeRouter.currentRoute.value.name).toBe("admin.dashboard");

    setAuth({
      permissions: ["dashboard-menu", "profile-menu", "team-view"],
    });
    const employeeRouter = createTestRouter();

    await goTo(employeeRouter, "/admin/employees");
    expect(employeeRouter.currentRoute.value.name).toBe("admin.dashboard");
  });

  it("keeps project detail and edit routes deterministic after hardening", async () => {
    setAuth({
      permissions: [
        "dashboard-menu",
        "project-menu",
        "project-list",
        "project-create",
        "project-edit",
      ],
    });
    const router = createTestRouter();

    await goTo(router, "/admin/projects/create");
    expect(router.currentRoute.value.name).toBe("admin.projects.create");

    await goTo(router, "/admin/projects/7");
    expect(router.currentRoute.value.name).toBe("admin.projects.detail");

    await goTo(router, "/admin/projects/7/edit");
    expect(router.currentRoute.value.name).toBe("admin.projects.edit");
  });

  it("allows HR to access payroll create and denies finance on the same route", async () => {
    setAuth({
      permissions: ["dashboard-menu", "payroll-menu", "payroll-list", "payroll-create"],
    });
    const hrRouter = createTestRouter();

    await goTo(hrRouter, "/admin/payroll/create");
    expect(hrRouter.currentRoute.value.name).toBe("admin.payroll.create");

    setAuth({
      permissions: [
        "dashboard-menu",
        "payroll-menu",
        "payroll-list",
        "payroll-edit",
        "payroll-process",
      ],
    });
    const financeRouter = createTestRouter();

    await goTo(financeRouter, "/admin/payroll/create");
    expect(financeRouter.currentRoute.value.name).toBe("admin.dashboard");
  });

  it("allows finance to access payroll settings and denies HR on the same route", async () => {
    setAuth({
      permissions: [
        "dashboard-menu",
        "payroll-menu",
        "payroll-list",
        "payroll-edit",
        "payroll-process",
        "payroll-statistics",
      ],
    });
    const financeRouter = createTestRouter();

    await goTo(financeRouter, "/admin/payroll/settings");
    expect(financeRouter.currentRoute.value.name).toBe("admin.payroll.settings");

    setAuth({
      permissions: ["dashboard-menu", "payroll-menu", "payroll-list", "payroll-create"],
    });
    const hrRouter = createTestRouter();

    await goTo(hrRouter, "/admin/payroll/settings");
    expect(hrRouter.currentRoute.value.name).toBe("admin.dashboard");
  });

  it("enforces payroll detail access while allowing dual-role self-service payroll", async () => {
    setAuth({
      permissions: [
        "dashboard-menu",
        "payroll-menu",
        "payroll-list",
        "payroll-create",
        "payslip-view",
      ],
    });
    const hrRouter = createTestRouter();

    await goTo(hrRouter, "/admin/payroll/99");
    expect(hrRouter.currentRoute.value.name).toBe("admin.payroll.detail");

    await goTo(hrRouter, "/admin/my-payroll");
    expect(hrRouter.currentRoute.value.name).toBe("employee.payroll");

    setAuth({
      permissions: [
        "dashboard-menu",
        "payroll-menu",
        "payroll-list",
        "payroll-edit",
        "payroll-process",
        "payroll-statistics",
        "payslip-view",
      ],
    });
    const financeRouter = createTestRouter();

    await goTo(financeRouter, "/admin/payroll/99");
    expect(financeRouter.currentRoute.value.name).toBe("admin.payroll.detail");

    await goTo(financeRouter, "/admin/my-payslips");
    expect(financeRouter.currentRoute.value.name).toBe("employee.payroll");

    setAuth({
      permissions: ["dashboard-menu", "team-menu", "employee-menu", "payslip-view"],
    });
    const managerRouter = createTestRouter();

    await goTo(managerRouter, "/admin/payroll/99");
    expect(managerRouter.currentRoute.value.name).toBe("admin.dashboard");

    await goTo(managerRouter, "/admin/my-payslips");
    expect(managerRouter.currentRoute.value.name).toBe("employee.payroll");
  });

  it("enforces attendance split between admin attendance and employee workspace", async () => {
    setAuth({
      permissions: ["dashboard-menu", "attendance-menu"],
    });
    const adminRouter = createTestRouter();

    await goTo(adminRouter, "/admin/attendances");
    expect(adminRouter.currentRoute.value.name).toBe("admin.attendances");

    setAuth({
      permissions: ["dashboard-menu", "attendance-my-attendances"],
    });
    const employeeRouter = createTestRouter();

    await goTo(employeeRouter, "/admin/attendance/my-attendances");
    expect(employeeRouter.currentRoute.value.name).toBe(
      "employee.attendance.my-attendances"
    );

    await goTo(employeeRouter, "/admin/attendances");
    expect(employeeRouter.currentRoute.value.name).toBe("admin.dashboard");
  });

  it("enforces clock alias route with strict attendance clock permissions", async () => {
    setAuth({
      permissions: ["dashboard-menu", "attendance-my-attendances"],
    });
    const nonClockRouter = createTestRouter();

    await goTo(nonClockRouter, "/admin/attendance/clock");
    expect(nonClockRouter.currentRoute.value.name).toBe("admin.dashboard");

    setAuth({
      permissions: ["dashboard-menu", "attendance-check-in"],
    });
    const clockRouter = createTestRouter();

    await goTo(clockRouter, "/admin/attendance/clock");
    expect(clockRouter.currentRoute.value.name).toBe(
      "employee.attendance.my-attendances"
    );
    expect(clockRouter.currentRoute.value.query.action).toBe("clock");
  });

  it("allows employee payroll route but blocks employee from admin payroll route", async () => {
    setAuth({
      permissions: [
        "dashboard-menu",
        "profile-menu",
        "attendance-my-attendances",
        "payslip-view",
      ],
    });
    const router = createTestRouter();

    await goTo(router, "/admin/my-payroll");
    expect(router.currentRoute.value.name).toBe("employee.payroll");

    await goTo(router, "/admin/my-payroll/21");
    expect(router.currentRoute.value.name).toBe("employee.payroll.detail");

    await goTo(router, "/admin/payroll");
    expect(router.currentRoute.value.name).toBe("admin.dashboard");

    await goTo(router, "/admin/payroll/create");
    expect(router.currentRoute.value.name).toBe("admin.dashboard");

    await goTo(router, "/admin/attendances");
    expect(router.currentRoute.value.name).toBe("admin.dashboard");

    await goTo(router, "/admin/employees");
    expect(router.currentRoute.value.name).toBe("admin.dashboard");

    await goTo(router, "/admin/teams");
    expect(router.currentRoute.value.name).toBe("admin.dashboard");
  });

  it("keeps legacy employee payslip route guarded and redirected to My Payroll", async () => {
    setAuth({
      permissions: ["dashboard-menu", "payslip-view"],
    });
    const employeeRouter = createTestRouter();

    await goTo(employeeRouter, "/admin/my-payslips");
    expect(employeeRouter.currentRoute.value.name).toBe("employee.payroll");

    setAuth({
      permissions: ["dashboard-menu", "team-menu"],
    });
    const managerRouter = createTestRouter();

    await goTo(managerRouter, "/admin/my-payslips");
    expect(managerRouter.currentRoute.value.name).toBe("admin.dashboard");
  });

  it("enforces employee self-service routes for profile and team", async () => {
    setAuth({
      permissions: ["dashboard-menu", "profile-menu", "team-view"],
    });
    const employeeRouter = createTestRouter();

    await goTo(employeeRouter, "/admin/my-profile");
    expect(employeeRouter.currentRoute.value.name).toBe("employee.profile");

    await goTo(employeeRouter, "/admin/my-team");
    expect(employeeRouter.currentRoute.value.name).toBe("employee.team");

    setAuth({
      permissions: ["dashboard-menu", "profile-menu"],
    });
    const nonTeamRouter = createTestRouter();

    await goTo(nonTeamRouter, "/admin/my-team");
    expect(nonTeamRouter.currentRoute.value.name).toBe("admin.dashboard");
  });

  it("allows finance hr and manager into personal self-service routes while keeping admin payroll split", async () => {
    setAuth({
      permissions: [
        "dashboard-menu",
        "payroll-menu",
        "payroll-list",
        "payroll-edit",
        "payroll-process",
        "payroll-statistics",
        "profile-menu",
        "attendance-my-attendances",
        "attendance-check-in",
        "leave-request-create",
        "payslip-view",
      ],
    });
    const financeRouter = createTestRouter();

    await goTo(financeRouter, "/admin/my-payroll");
    expect(financeRouter.currentRoute.value.name).toBe("employee.payroll");

    await goTo(financeRouter, "/admin/my-profile");
    expect(financeRouter.currentRoute.value.name).toBe("employee.profile");

    await goTo(financeRouter, "/admin/attendance/my-attendances");
    expect(financeRouter.currentRoute.value.name).toBe(
      "employee.attendance.my-attendances"
    );

    await goTo(financeRouter, "/admin/payroll/create");
    expect(financeRouter.currentRoute.value.name).toBe("admin.dashboard");

    setAuth({
      permissions: [
        "dashboard-menu",
        "payroll-menu",
        "payroll-list",
        "payroll-create",
        "profile-menu",
        "attendance-my-attendances",
        "attendance-check-out",
        "leave-request-create",
        "payslip-view",
      ],
    });
    const hrRouter = createTestRouter();

    await goTo(hrRouter, "/admin/my-payroll");
    expect(hrRouter.currentRoute.value.name).toBe("employee.payroll");

    await goTo(hrRouter, "/admin/attendance/my-attendances");
    expect(hrRouter.currentRoute.value.name).toBe(
      "employee.attendance.my-attendances"
    );

    setAuth({
      permissions: [
        "dashboard-menu",
        "team-menu",
        "employee-menu",
        "profile-menu",
        "attendance-my-attendances",
        "attendance-check-in",
        "leave-request-create",
        "payslip-view",
      ],
    });
    const managerRouter = createTestRouter();

    await goTo(managerRouter, "/admin/my-payroll");
    expect(managerRouter.currentRoute.value.name).toBe("employee.payroll");

    await goTo(managerRouter, "/admin/my-profile");
    expect(managerRouter.currentRoute.value.name).toBe("employee.profile");

    await goTo(managerRouter, "/admin/payroll");
    expect(managerRouter.currentRoute.value.name).toBe("admin.dashboard");
  });

  it("applies fail-closed behavior for authenticated route without explicit permission meta", async () => {
    setAuth({
      permissions: ["dashboard-menu"],
    });
    const router = createTestRouter();

    await goTo(router, "/admin");

    expect(router.currentRoute.value.name).toBe("admin.dashboard");
  });

  it("allows authenticated users to access notifications route via allowAuthenticated meta", async () => {
    setAuth({
      permissions: [],
    });
    const router = createTestRouter();

    await goTo(router, "/admin/notifications");

    expect(router.currentRoute.value.name).toBe("admin.notifications");
  });
});
