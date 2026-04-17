import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
  routeState,
  projectStoreMock,
  routerPushMock,
  formatDateMock,
  calculateDurationMock,
  formatRupiahMock,
} = vi.hoisted(() => ({
  routeState: {
    params: {
      id: "77",
    },
  },
  projectStoreMock: {
    fetchProject: vi.fn(),
    fetchProjectSquadSummary: vi.fn(),
  },
  routerPushMock: vi.fn(),
  formatDateMock: vi.fn((value) => value || "N/A"),
  calculateDurationMock: vi.fn(() => "6 months"),
  formatRupiahMock: vi.fn((value) => `Rp ${value}`),
}));

vi.mock("@/stores/project", () => ({
  useProjectStore: () => projectStoreMock,
}));

vi.mock("vue-router", () => ({
  useRoute: () => routeState,
}));

vi.mock("@/router", () => ({
  default: {
    push: routerPushMock,
  },
}));

vi.mock("@/utils/dateUtils", () => ({
  formatDate: formatDateMock,
  calculateDuration: calculateDurationMock,
}));

vi.mock("@/utils/formatUtils", () => ({
  formatRupiah: formatRupiahMock,
}));

vi.mock("@/utils/badgeUtils", () => ({
  getPriorityColor: () => "bg-yellow-100 text-yellow-600",
  getProjectStatusColor: () => "bg-green-100 text-green-600",
  getProgressColor: () => "bg-green-500",
}));

import ProjectDetail from "@/views/admin/project/ProjectDetail.vue";

const flushPromises = () => new Promise((resolve) => setTimeout(resolve, 0));
const flushUi = async () => {
  await flushPromises();
  await nextTick();
  await flushPromises();
};

const factory = () =>
  mount(ProjectDetail, {
    global: {
      stubs: {
        TaskBoard: {
          template: '<div class="task-board-stub"></div>',
        },
        EmptyState: {
          template: '<div class="empty-state-stub"></div>',
        },
        AnimatedValue: {
          props: ["value", "suffix"],
          template: '<span class="animated-value-stub">{{ value }}{{ suffix || "" }}</span>',
        },
        RouterLink: {
          props: ["to"],
          template: '<a class="router-link-stub"><slot /></a>',
        },
      },
    },
  });

describe("ProjectDetail squad summary smoke", () => {
  beforeEach(() => {
    routeState.params.id = "77";
    projectStoreMock.fetchProject.mockReset().mockResolvedValue({
      id: 77,
      name: "TeamSync Mobile Development 20 Squad",
      description: "Main project description",
      status: "active",
      priority: "high",
      photo: "https://example.com/project.png",
      budget: 2500000000,
      start_date: "2026-04-01",
      end_date: "2026-10-31",
      leader: {
        id: 7,
        user: {
          name: "Raka PM",
          profile_photo: "https://example.com/leader.png",
        },
        job_information: {
          job_title: "Product Manager",
        },
      },
      teams: [],
      tasks: [
        { id: 1, status: "done" },
        { id: 2, status: "in_progress" },
      ],
    });

    projectStoreMock.fetchProjectSquadSummary.mockReset().mockResolvedValue({
      project: {
        id: 77,
      },
      headcount: {
        total: 20,
        by_stream: {
          frontend: 5,
          backend: 6,
          uiux: 3,
          qa: 4,
          pm: 2,
          other: 0,
        },
      },
      tasks: {
        total: 30,
        by_status: {
          todo: 10,
          in_progress: 8,
          review: 6,
          done: 4,
          rejected: 2,
          cancelled: 0,
        },
      },
    });

    routerPushMock.mockReset();
  });

  it("fetches and displays squad snapshot section", async () => {
    const wrapper = factory();
    await flushUi();

    expect(projectStoreMock.fetchProject).toHaveBeenCalledWith("77");
    expect(projectStoreMock.fetchProjectSquadSummary).toHaveBeenCalledWith("77");

    expect(wrapper.text()).toContain("Squad Snapshot");
    expect(wrapper.text()).toContain("Members");
    expect(wrapper.text()).toContain("Tasks");
    expect(wrapper.text()).toContain("20");
    expect(wrapper.text()).toContain("30");

    expect(wrapper.text()).toContain("Frontend");
    expect(wrapper.text()).toContain("Backend");
    expect(wrapper.text()).toContain("UI/UX");
    expect(wrapper.text()).toContain("QA");
    expect(wrapper.text()).toContain("PM");

    expect(wrapper.text()).toContain("Task Status");
    expect(wrapper.text()).toContain("To Do");
    expect(wrapper.text()).toContain("In Progress");
    expect(wrapper.text()).toContain("Review");
    expect(wrapper.text()).toContain("Done");
    expect(wrapper.text()).toContain("Rejected");
  });

  it("redirects to projects list when project is missing", async () => {
    projectStoreMock.fetchProject.mockResolvedValueOnce(null);

    factory();
    await flushUi();

    expect(routerPushMock).toHaveBeenCalledWith({ name: "admin.projects" });
  });
});
