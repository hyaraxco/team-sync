import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { ref } from "vue";

const mockEmployees = ref([]);
const mockAuthUser = ref({ roles: [] });

const mockTaskStore = {
  updateTask: vi.fn().mockResolvedValue({}),
  fetchProjectTasks: vi.fn().mockResolvedValue([]),
  fetchTaskComments: vi.fn().mockResolvedValue([]),
  createTaskComment: vi.fn().mockResolvedValue({}),
  updateTaskComment: vi.fn().mockResolvedValue({}),
  deleteTaskComment: vi.fn().mockResolvedValue({}),
  fetchTaskAttachments: vi.fn().mockResolvedValue([]),
  fetchTaskStatusLogs: vi.fn().mockResolvedValue([]),
  uploadTaskAttachment: vi.fn().mockResolvedValue({}),
  deleteTaskAttachment: vi.fn().mockResolvedValue({}),
};

const mockEmployeeStore = {
  fetchStaffMembers: vi.fn().mockResolvedValue([]),
};

vi.mock("@/stores/task", () => ({
  useTaskStore: () => mockTaskStore,
}));

vi.mock("@/stores/staffMember", () => ({
  useStaffMemberStore: () => mockEmployeeStore,
}));

vi.mock("@/stores/auth", () => ({
  useAuthStore: () => ({
    get user() {
      return mockAuthUser.value;
    },
  }),
}));

vi.mock("pinia", async (importOriginal) => {
  const actual = await importOriginal();

  return {
    ...actual,
    storeToRefs: () => ({
      employees: mockEmployees,
    }),
  };
});

import TaskDetailModal from "@/components/admin/project/detail/TaskDetailModal.vue";

const makeTask = (overrides = {}) => ({
  id: 10,
  name: "RBAC Task",
  description: "Task description",
  assignee_id: 100,
  priority: "medium",
  status: "todo",
  due_date: "2026-03-31",
  project: {
    leader: {
      id: 999,
    },
  },
  ...overrides,
});

const factory = (task, authUser) => {
  mockAuthUser.value = authUser;

  return mount(TaskDetailModal, {
    props: {
      task,
      isOpen: true,
      projectId: 1,
    },
    global: {
      stubs: {
        ModalWrapper: {
          props: ["show", "title", "maxWidth"],
          template: '<div v-if="show"><slot name="header" /><slot /><slot name="footer" /></div>',
        },
        StatusBadge: {
          props: ["value"],
          template: "<span>{{ value }}</span>",
        },
      },
    },
  });
};

describe("TaskDetailModal smoke", () => {
  beforeEach(() => {
    mockEmployees.value = [];
    mockAuthUser.value = { roles: [] };
    Object.values(mockTaskStore).forEach((fn) => {
      if (typeof fn === "function" && "mockClear" in fn) {
        fn.mockClear();
      }
    });
    mockEmployeeStore.fetchStaffMembers.mockClear();
  });

  it("shows reviewer actions on review status", async () => {
    const wrapper = factory(makeTask({ status: "review" }), {
      name: "Manager User",
      employee_profile: { id: 300 },
      roles: [{ name: "manager" }],
    });

    await wrapper.vm.$nextTick();

    expect(wrapper.text()).toContain("Approve and Mark Done");
    expect(wrapper.text()).toContain("Reject Task");
    expect(wrapper.text()).not.toContain("Start Task");
  });

  it("shows employee submit action for own in progress task", async () => {
    const wrapper = factory(
      makeTask({
        status: "in_progress",
        assignee_id: 100,
      }),
      {
        name: "Employee User",
        employee_profile: { id: 100 },
        roles: [{ name: "staff" }],
      }
    );

    await wrapper.vm.$nextTick();

    expect(wrapper.text()).toContain("Task Selesai - Submit for Review");
    expect(wrapper.text()).not.toContain("Approve and Mark Done");
    expect(wrapper.text()).not.toContain("Delete Task");
  });

  it("locks employee collaboration on review status", async () => {
    const wrapper = factory(
      makeTask({
        status: "review",
        assignee_id: 100,
      }),
      {
        name: "Employee User",
        employee_profile: { id: 100 },
        roles: [{ name: "staff" }],
      }
    );

    await wrapper.vm.$nextTick();

    const commentTextarea = wrapper.find('textarea[placeholder="Comment is locked for this task status"]');
    expect(commentTextarea.exists()).toBe(true);
    expect(commentTextarea.attributes("disabled")).toBeDefined();
  });
});
