import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { ref } from "vue";

const grantedPermissions = new Set();

const setPermissions = (permissions) => {
  grantedPermissions.clear();
  permissions.forEach((permission) => grantedPermissions.add(permission));
};

vi.mock("@/helpers/permissionHelper", () => ({
  can: (permission) => grantedPermissions.has(permission),
  canOneOf: (permissions) =>
    permissions.some((permission) => grantedPermissions.has(permission)),
}));

vi.mock("@/stores/auth", () => ({
  useAuthStore: () => ({
    user: {
      employee_profile: {
        job_information: {
          work_location: "office",
        },
      },
    },
  }),
}));

vi.mock("@/stores/attendance", () => ({
  useAttendanceStore: () => ({
    todayAttendance: ref(null),
    loading: ref(false),
    fetchTodayAttendance: vi.fn().mockResolvedValue(undefined),
    checkIn: vi.fn().mockResolvedValue(undefined),
    checkOut: vi.fn().mockResolvedValue(undefined),
  }),
}));

vi.mock("pinia", async (importOriginal) => {
  const actual = await importOriginal();
  return {
    ...actual,
    storeToRefs: (store) => store,
  };
});

vi.mock("@/composables/useToast", () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
  }),
}));

import QuickActions from "@/components/admin/dashboard/QuickActions.vue";

const RouterLinkStub = {
  name: "RouterLink",
  inheritAttrs: false,
  props: ["to"],
  template: `
    <a
      v-bind="$attrs"
      :data-route-name="typeof to === 'object' ? to.name : ''"
      :data-route-action="typeof to === 'object' && to.query ? to.query.action : ''"
    >
      <slot />
    </a>
  `,
};

const factory = () =>
  mount(QuickActions, {
    global: {
      stubs: {
        RouterLink: RouterLinkStub,
      },
    },
  });

describe("QuickActions smoke", () => {
  beforeEach(() => {
    setPermissions([]);
  });

  it("shows admin actions in order and promotes the first actionable item", () => {
    setPermissions(["staff-member-create", "team-create", "payroll-create"]);

    const wrapper = factory();
    const actions = wrapper.findAll("[data-action-id]").map((node) => node.text().trim());

    expect(actions).toEqual([
      "Add Staff Member",
      "Create New Team",
      "Process Payroll",
    ]);

    expect(
      wrapper.find('[data-action-id="add-employee"]').attributes("class")
    ).toContain("blue-gradient");
    expect(
      wrapper.find('[data-action-id="create-team"]').attributes("class")
    ).not.toContain("blue-gradient");
  });

  it("shows payroll quick action for HR-style draft permissions", () => {
    setPermissions(["payroll-create"]);

    const wrapper = factory();
    const actions = wrapper.findAll("[data-action-id]").map((node) => node.text().trim());

    expect(actions).toEqual([
      "Process Payroll",
    ]);
    expect(
      wrapper.find('[data-action-id="process-payroll"]').attributes("data-route-name")
    ).toBe("admin.payroll.create");
  });

  it("does not show payroll quick action for finance-style permissions", () => {
    setPermissions(["payroll-list", "payroll-edit", "payroll-process", "payroll-statistics"]);

    const wrapper = factory();

    expect(wrapper.text()).not.toContain("Process Payroll");
    expect(wrapper.findAll("[data-action-id]")).toHaveLength(0);
  });

  it("keeps payroll quick action hidden for manager-style permissions while allowing self-service leave", () => {
    setPermissions([
      "team-create",
      "staff-member-create",
      "attendance-my-attendances",
      "attendance-check-in",
      "leave-request-create",
    ]);

    const wrapper = factory();

    expect(wrapper.text()).not.toContain("Process Payroll");
    expect(wrapper.text()).toContain("Request Leave");
  });

  it("shows employee actions without admin actions", () => {
    setPermissions([
      "attendance-check-in",
      "attendance-check-out",
      "leave-request-create",
    ]);

    const wrapper = factory();
    const actions = wrapper.findAll("[data-action-id]").map((node) => node.text().trim());

    expect(actions).toContain("Request Leave");
    expect(wrapper.text()).not.toContain("Add Staff Member");
    expect(
      wrapper.find('[data-action-id="request-leave"]').attributes("data-route-name")
    ).toBe("staffMember.attendance.my-attendances");
    expect(
      wrapper.find('[data-action-id="request-leave"]').attributes("data-route-action")
    ).toBe("request-leave");
  });

  it("shows self-service leave for finance-style users without payroll-create", () => {
    setPermissions([
      "payroll-list",
      "payroll-edit",
      "payroll-process",
      "payroll-statistics",
      "attendance-my-attendances",
      "attendance-check-out",
      "leave-request-create",
    ]);

    const wrapper = factory();

    expect(wrapper.text()).toContain("Request Leave");
    expect(wrapper.text()).not.toContain("Process Payroll");
  });

  it.each(["attendance-check-in", "attendance-check-out"])(
    "shows Clock In/Out when one attendance permission is enough",
    (permission) => {
      setPermissions([permission]);

      const wrapper = factory();

      // The action renders either "Clock In" or "Clock Out" depending on attendance state
      const clockAction = wrapper.find('[data-action-id="clock-in-out"]');
      expect(clockAction.exists()).toBe(true);
    }
  );

  it("does not render any placeholder actions", () => {
    const wrapper = factory();
    const placeholder = wrapper.find('button[data-action-id="schedule-meeting"]');

    expect(placeholder.exists()).toBe(false);
    expect(wrapper.text()).not.toContain("Coming soon");
  });
});
