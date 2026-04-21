import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
  authStoreMock,
  staffMemberStoreMock,
  taskStoreMock,
  notificationStoreMock,
  axiosGetMock,
  routerPushMock,
  routerLinkStub,
} = vi.hoisted(() => ({
  authStoreMock: {
    user: {
      name: "Taylor Employee",
      employee_profile: {
        id: 101,
      },
    },
  },
  staffMemberStoreMock: {
    fetchMyTeamProjects: vi.fn(),
  },
  taskStoreMock: {
    tasks: [],
    fetchProjectTasks: vi.fn(),
  },
  notificationStoreMock: {
    notifications: [],
    loading: false,
    error: null,
    fetchLatestNotifications: vi.fn(),
  },
  axiosGetMock: vi.fn(),
  routerPushMock: vi.fn(),
  routerLinkStub: {
    name: "RouterLink",
    props: ["to"],
    template:
      '<a :data-route-name="typeof to === \'object\' ? to.name : \'\'"><slot /></a>',
  },
}));

vi.mock("@/stores/auth", () => ({
  useAuthStore: () => authStoreMock,
}));

vi.mock("@/stores/staffMember", () => ({
  useStaffMemberStore: () => staffMemberStoreMock,
}));

vi.mock("@/stores/task", () => ({
  useTaskStore: () => taskStoreMock,
}));

vi.mock("@/stores/notifications", () => ({
  useNotificationStore: () => notificationStoreMock,
}));

vi.mock("@/plugins/axios", () => ({
  axiosInstance: {
    get: axiosGetMock,
  },
}));

vi.mock("vue-router", () => ({
  useRouter: () => ({
    push: routerPushMock,
  }),
  RouterLink: routerLinkStub,
}));

import EmployeeStatistics from "@/components/admin/dashboard/EmployeeStatistics.vue";

const flushPromises = () => new Promise((resolve) => setTimeout(resolve, 0));
const flushUi = async () => {
  await flushPromises();
  await nextTick();
  await flushPromises();
};

const factory = () =>
  mount(EmployeeStatistics, {
    global: {
      stubs: {
        RouterLink: routerLinkStub,
        StatsCard: {
          template: '<div class="stats-card-stub"><slot /></div>',
        },
        MainCard: {
          template:
            '<div class="main-card-stub"><slot /><slot name="footer" /></div>',
        },
        QuickActions: {
          template: '<div class="quick-actions-stub"></div>',
        },
      },
    },
  });

describe("EmployeeStatistics smoke", () => {
  beforeEach(() => {
    authStoreMock.user = {
      name: "Taylor Employee",
      employee_profile: {
        id: 101,
      },
    };
    taskStoreMock.tasks = [];
    taskStoreMock.fetchProjectTasks.mockReset().mockResolvedValue(undefined);
    staffMemberStoreMock.fetchMyTeamProjects.mockReset().mockResolvedValue([]);
    notificationStoreMock.notifications = [];
    notificationStoreMock.loading = false;
    notificationStoreMock.error = null;
    notificationStoreMock.fetchLatestNotifications.mockReset().mockResolvedValue([]);
    routerPushMock.mockReset().mockResolvedValue(undefined);
    axiosGetMock.mockReset().mockResolvedValue({
      data: {
        data: {
          attendance: {},
          tasks: {},
          projects: {},
        },
      },
    });
  });

  it("uses notification feed for recent activities and keeps View All route wired", async () => {
    notificationStoreMock.notifications = [
      {
        id: "n-1",
        title: "Payroll processed",
        body: "Your payslip has been generated.",
        action_url: "/admin/my-payroll/12",
        is_read: false,
        created_at: "2026-04-14T09:00:00Z",
      },
      {
        id: "n-2",
        title: "Attendance approved",
        body: "Your check-in request is approved.",
        action_url: "/admin/attendance/my-attendances",
        is_read: true,
        created_at: "2026-04-14T08:30:00Z",
      },
    ];

    const wrapper = factory();
    await flushUi();

    expect(notificationStoreMock.fetchLatestNotifications).toHaveBeenCalledWith(20);
    expect(wrapper.text()).toContain("Recent Activities");
    expect(wrapper.text()).toContain("Payroll processed");

    const viewAll = wrapper.get('[data-testid="recent-activities-view-all"]');
    expect(viewAll.attributes("data-route-name")).toBe("admin.notifications");
  });

  it("navigates to action_url when an activity is selected", async () => {
    notificationStoreMock.notifications = [
      {
        id: "task-1",
        title: "Open task detail",
        body: "Go to project task",
        action_url: "/admin/projects/77",
        is_read: false,
        created_at: "2026-04-14T10:00:00Z",
      },
    ];

    const wrapper = factory();
    await flushUi();

    const activityButton = wrapper
      .findAll("button")
      .find((button) => button.text().includes("Open task detail"));

    expect(activityButton).toBeDefined();
    await activityButton.trigger("click");

    expect(routerPushMock).toHaveBeenCalledWith("/admin/projects/77");
  });

  it("keeps only active own tasks in Upcoming Tasks and treats rejected as needs revision", async () => {
    taskStoreMock.tasks = [
      {
        id: 1,
        name: "Done task",
        status: "done",
        assignee_id: 101,
      },
      {
        id: 2,
        name: "Cancelled task",
        status: "cancelled",
        assignee_id: 101,
      },
      {
        id: 3,
        name: "Other employee review",
        status: "review",
        assignee_id: 202,
      },
      {
        id: 4,
        name: "Need API revision",
        status: "rejected",
        assignee_id: 101,
      },
      {
        id: 5,
        name: "Pending docs",
        status: "pending",
        assignee_id: 101,
      },
      {
        id: 6,
        name: "In progress QA",
        status: "in_progress",
        assignee_id: 101,
      },
    ];

    const wrapper = factory();
    await flushUi();

    expect(wrapper.text()).toContain("Need API revision");
    expect(wrapper.text()).toContain("Pending docs");
    expect(wrapper.text()).toContain("In progress QA");
    expect(wrapper.text()).toContain("Needs Revision");

    expect(wrapper.text()).not.toContain("Done task");
    expect(wrapper.text()).not.toContain("Cancelled task");
    expect(wrapper.text()).not.toContain("Other employee review");
  });

  it("shows retry UI when recent activities request fails", async () => {
    notificationStoreMock.error = "Network error";

    const wrapper = factory();
    await flushUi();

    expect(wrapper.text()).toContain("Failed to load activities.");

    await wrapper.get("button.text-red-700").trigger("click");

    expect(notificationStoreMock.fetchLatestNotifications).toHaveBeenCalledTimes(2);
  });
});