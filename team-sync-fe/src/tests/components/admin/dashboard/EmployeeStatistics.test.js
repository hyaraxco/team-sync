import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { ref } from "vue";

const authStoreMock = {
    user: {
        name: "Test Employee",
        employee_profile: { id: 101 },
    },
};
const taskStoreMock = {
    tasks: [],
    fetchProjectTasks: vi.fn().mockResolvedValue(undefined),
};
const staffMemberStoreMock = {
    fetchMyTeamProjects: vi.fn().mockResolvedValue([]),
};
const notificationStoreMock = {
    notifications: [],
    loading: false,
    error: null,
    fetchLatestNotifications: vi.fn().mockResolvedValue([]),
};
const dashboardStoreMock = {
    fetchMyStatistics: vi.fn().mockResolvedValue({
        attendance: { rate: 95, present_days: 20, absent_days: 1, late_days: 2 },
        tasks: { done: 5, done_yesterday: 1, in_progress: 3, todo: 2, review: 1 },
        projects: { assigned_active: 2, led_active: 1 },
    }),
};

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
vi.mock("@/stores/dashboard", () => ({
    useDashboardStore: () => dashboardStoreMock,
}));
vi.mock("vue-router", () => ({
    useRouter: () => ({
        push: vi.fn(),
    }),
    RouterLink: {
        name: "RouterLink",
        props: ["to"],
        template: '<a><slot /></a>',
    },
}));
vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: vi.fn(),
        error: vi.fn(),
    }),
}));

import EmployeeStatistics from "@/components/admin/dashboard/EmployeeStatistics.vue";

const flushUi = async () => {
    await Promise.resolve();
    await Promise.resolve();
    await new Promise((r) => setTimeout(r, 0));
    await Promise.resolve();
};

const factory = () =>
    mount(EmployeeStatistics, {
        global: {
            stubs: {
                StatsCard: {
                    template: '<div class="stats-card-stub"><slot /></div>',
                },
                MainCard: {
                    template: '<div class="main-card-stub"><slot /><slot name="footer" /></div>',
                },
                QuickActions: {
                    template: '<div class="quick-actions-stub"></div>',
                },
                RouterLink: {
                    name: "RouterLink",
                    props: ["to"],
                    template: '<a><slot /></a>',
                },
            },
        },
    });

describe("EmployeeStatistics - normalizeTaskStatus", () => {
    beforeEach(() => {
        taskStoreMock.tasks = [];
        notificationStoreMock.notifications = [];
        notificationStoreMock.loading = false;
        notificationStoreMock.error = null;
    });

    it("normalizes 'pending' to 'todo'", () => {
        taskStoreMock.tasks = [
            { id: 1, name: "Pending task", status: "pending", assignee_id: 101, project: { name: "P1" } },
        ];
        const wrapper = factory();
        expect(wrapper.vm.upcomingTasks[0].status).toBe("todo");
    });

    it("passes through 'in_progress' unchanged", () => {
        taskStoreMock.tasks = [
            { id: 1, name: "IP task", status: "in_progress", assignee_id: 101, project: { name: "P1" } },
        ];
        const wrapper = factory();
        expect(wrapper.vm.upcomingTasks[0].status).toBe("in_progress");
    });

    it("filters out task with null status (normalize produces empty string, not in upcoming set)", () => {
        taskStoreMock.tasks = [
            { id: 1, name: "No status", status: null, assignee_id: 101, project: { name: "P1" } },
        ];
        const wrapper = factory();
        expect(wrapper.vm.upcomingTasks).toHaveLength(0);
    });

    it("lowercases uppercase status", () => {
        taskStoreMock.tasks = [
            { id: 1, name: "Upper task", status: "TODO", assignee_id: 101, project: { name: "P1" } },
        ];
        const wrapper = factory();
        expect(wrapper.vm.upcomingTasks[0].status).toBe("todo");
    });
});

describe("EmployeeStatistics - upcomingTasks filtering and sorting", () => {
    beforeEach(() => {
        taskStoreMock.tasks = [];
        notificationStoreMock.notifications = [];
    });

    it("filters out done/cancelled tasks", () => {
        taskStoreMock.tasks = [
            { id: 1, name: "Done task", status: "done", assignee_id: 101 },
            { id: 2, name: "Active task", status: "in_progress", assignee_id: 101 },
            { id: 3, name: "Cancelled", status: "cancelled", assignee_id: 101 },
        ];
        const wrapper = factory();
        expect(wrapper.vm.upcomingTasks).toHaveLength(1);
        expect(wrapper.vm.upcomingTasks[0].id).toBe(2);
    });

    it("filters tasks not assigned to current employee", () => {
        taskStoreMock.tasks = [
            { id: 1, name: "My task", status: "todo", assignee_id: 101 },
            { id: 2, name: "Other task", status: "todo", assignee_id: 200 },
        ];
        const wrapper = factory();
        expect(wrapper.vm.upcomingTasks).toHaveLength(1);
        expect(wrapper.vm.upcomingTasks[0].id).toBe(1);
    });

    it("sorts tasks by due date ascending", () => {
        taskStoreMock.tasks = [
            { id: 1, name: "Later", status: "todo", assignee_id: 101, due_date: "2026-12-31" },
            { id: 2, name: "Sooner", status: "todo", assignee_id: 101, due_date: "2026-01-15" },
            { id: 3, name: "No date", status: "todo", assignee_id: 101, due_date: "" },
        ];
        const wrapper = factory();
        expect(wrapper.vm.upcomingTasks[0].dueDate).toBe("2026-01-15");
        expect(wrapper.vm.upcomingTasks[1].dueDate).toBe("2026-12-31");
    });

    it("includes rejected and review statuses", () => {
        taskStoreMock.tasks = [
            { id: 1, name: "Review task", status: "review", assignee_id: 101 },
            { id: 2, name: "Rejected task", status: "rejected", assignee_id: 101 },
        ];
        const wrapper = factory();
        expect(wrapper.vm.upcomingTasks).toHaveLength(2);
    });

    it("returns empty array when tasks is not an array", () => {
        taskStoreMock.tasks = null;
        const wrapper = factory();
        expect(wrapper.vm.upcomingTasks).toEqual([]);
    });

    it("matches assignee via nested assignee.id", () => {
        taskStoreMock.tasks = [
            { id: 1, name: "Nested", status: "todo", assignee: { id: 101 } },
        ];
        const wrapper = factory();
        expect(wrapper.vm.upcomingTasks).toHaveLength(1);
    });
});

describe("EmployeeStatistics - onTimePercentage", () => {
    beforeEach(() => {
        taskStoreMock.tasks = [];
        notificationStoreMock.notifications = [];
    });

    it("rounds to 1 decimal place", async () => {
        dashboardStoreMock.fetchMyStatistics.mockResolvedValueOnce({
            attendance: { rate: 0, present_days: 10, late_days: 1 },
            tasks: {},
            projects: {},
        });
        const wrapper = factory();
        await flushUi();
        // 9/10 = 0.9 -> 90.0%
        expect(wrapper.vm.onTimePercentage).toBe(90);
    });

    it("returns 0 when no present days", () => {
        taskStoreMock.tasks = [];
        const wrapper = factory();
        wrapper.vm.statistics.present_days = 0;
        wrapper.vm.statistics.late_days = 0;
        expect(wrapper.vm.onTimePercentage).toBe(0);
    });

    it("handles all days late", () => {
        taskStoreMock.tasks = [];
        const wrapper = factory();
        wrapper.vm.statistics.present_days = 10;
        wrapper.vm.statistics.late_days = 10;
        expect(wrapper.vm.onTimePercentage).toBe(0);
    });

    it("handles zero late days", () => {
        taskStoreMock.tasks = [];
        const wrapper = factory();
        wrapper.vm.statistics.present_days = 20;
        wrapper.vm.statistics.late_days = 0;
        expect(wrapper.vm.onTimePercentage).toBe(100);
    });
});

describe("EmployeeStatistics - Notification category icon/color resolution", () => {
    beforeEach(() => {
        taskStoreMock.tasks = [];
        notificationStoreMock.notifications = [];
    });

    it("resolves task category bg class to green bg", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconBgClass({ category: "task-assigned" });
        expect(cls).toContain("green-50");
    });

    it("resolves attendance category bg class to purple bg", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconBgClass({ category: "attendance-approved" });
        expect(cls).toContain("purple-50");
    });

    it("resolves payroll bg class to orange bg", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconBgClass({ category: "payroll-processed" });
        expect(cls).toContain("orange-50");
    });

    it("resolves comment bg class to blue bg", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconBgClass({ category: "comment-added" });
        expect(cls).toContain("blue-50");
    });

    it("resolves meeting bg class to orange bg", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconBgClass({ category: "meeting-scheduled" });
        expect(cls).toContain("orange-50");
    });

    it("resolves unknown category bg class to default blue bg", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconBgClass({ category: "unknown-type" });
        expect(cls).toContain("primary-50");
    });

    it("resolves task icon class to green text", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconClass({ category: "task-assigned" });
        expect(cls).toContain("green-600");
    });

    it("resolves payroll icon class to orange text", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconClass({ category: "salary-slip" });
        expect(cls).toContain("orange-600");
    });

    it("resolves attendance icon class to purple text", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconClass({ category: "check-in" });
        expect(cls).toContain("purple-600");
    });

    it("resolves comment icon class to blue text", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconClass({ category: "message-sent" });
        expect(cls).toContain("blue-600");
    });

    it("resolves meeting icon class to orange text", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconClass({ category: "team-update" });
        expect(cls).toContain("orange-500");
    });

    it("resolves unknown category icon class to default blue text", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconClass({ category: "unknown-type" });
        expect(cls).toContain("primary-500");
    });

    it("resolves category from data.category fallback", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconBgClass({ data: { category: "check-in" } });
        expect(cls).toContain("purple-50");
    });

    it("resolves category from type fallback", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconBgClass({ type: "payroll-slip" });
        expect(cls).toContain("orange-50");
    });

    it("returns default bg class when notification is null", () => {
        const wrapper = factory();
        const cls = wrapper.vm.getActivityIconBgClass(null);
        expect(cls).toContain("primary-50");
    });

    it("getActivityIcon returns a function component", () => {
        const wrapper = factory();
        const icon = wrapper.vm.getActivityIcon({ category: "task-assigned" });
        expect(icon).toBeDefined();
        expect(typeof icon).toBe("function");
    });
});
