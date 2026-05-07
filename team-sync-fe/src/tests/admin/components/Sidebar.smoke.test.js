import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const grantedPermissions = new Set();
const currentRoute = {
  name: "admin.dashboard",
};

const setPermissions = (permissions) => {
  grantedPermissions.clear();
  permissions.forEach((permission) => grantedPermissions.add(permission));
};

vi.mock("@/helpers/permissionHelper", () => ({
  can: (permission) => grantedPermissions.has(permission),
  canOneOf: (permissions) =>
    permissions.some((permission) => grantedPermissions.has(permission)),
}));

import Sidebar from "@/components/admin/Sidebar.vue";

const RouterLinkStub = {
  name: "RouterLink",
  props: ["to"],
  template: '<a><slot /></a>',
};

const factory = () =>
  mount(Sidebar, {
    props: {
      isOpen: true,
    },
    global: {
      mocks: {
        $route: currentRoute,
      },
      stubs: {
        RouterLink: RouterLinkStub,
      },
    },
  });

describe("Sidebar smoke", () => {
  beforeEach(() => {
    setPermissions([]);
  });

  it("hides payroll menu for manager-style permissions", () => {
    setPermissions([
      "dashboard-menu",
      "dashboard-view",
      "team-menu",
      "staff-member-menu",
      "attendance-menu",
    ]);

    const wrapper = factory();

    expect(wrapper.text()).not.toContain("Payroll");
  });

  it("shows payroll menu for HR or Finance when payroll-menu is granted", () => {
    setPermissions(["payroll-menu"]);

    const wrapper = factory();

    expect(wrapper.text()).toContain("Payroll");
    expect(wrapper.text()).toContain("Payroll Adjustments");
  });

  it("shows My Payroll when payslip-view is granted", () => {
    setPermissions(["payslip-view"]);

    const wrapper = factory();

    expect(wrapper.text()).toContain("My Payroll");
    expect(wrapper.text()).not.toContain("Clock In/Out");
  });

  it("shows mixed admin and self-service navigation for finance, hr, and manager", () => {
    setPermissions([
      "dashboard-menu",
      "profile-menu",
      "attendance-my-attendances",
      "attendance-check-in",
      "attendance-check-out",
      "leave-request-menu",
      "leave-request-create",
      "leave-request-my-requests",
      "payslip-view",
      "payroll-menu",
    ]);

    const financeWrapper = factory();

    expect(financeWrapper.get('[data-testid="sidebar-section-general"]').text()).toContain(
      "GENERAL"
    );
    expect(financeWrapper.get('[data-testid="sidebar-section-personal"]').text()).toContain(
      "PERSONAL"
    );
    expect(financeWrapper.text()).toContain("Payroll");
    expect(financeWrapper.text()).toContain("My Payroll");
    expect(financeWrapper.text()).toContain("My Attendance");
    expect(financeWrapper.text()).toContain("My Profile");
  });

  it("hides Personal section when user has no personal workspace permissions", () => {
    setPermissions(["dashboard-menu", "team-menu", "staff-member-menu"]);

    const wrapper = factory();

    expect(wrapper.find('[data-testid="sidebar-section-personal"]').exists()).toBe(false);
  });

  it("keeps payroll menu hidden for employee-style permissions", () => {
    setPermissions([
      "dashboard-menu",
      "dashboard-view",
      "attendance-my-attendances",
      "attendance-check-in",
      "attendance-check-out",
      "leave-request-create",
    ]);

    const wrapper = factory();

    expect(wrapper.text()).not.toContain("Payroll");
  });

  it("does not render placeholder menu entries", () => {
    setPermissions(["dashboard-menu", "profile-menu"]);

    const wrapper = factory();

    expect(wrapper.text()).not.toContain("Reports");
    expect(wrapper.find('a[href="#"]').exists()).toBe(false);
  });
});
