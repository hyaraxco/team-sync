import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { ref } from "vue";

const mockTasks = ref([]);
const mockUser = ref({ roles: [] });

vi.mock("@/stores/task", () => ({
  useTaskStore: () => ({
    fetchProjectTasks: vi.fn(),
    updateTaskStatus: vi.fn(),
    createTask: vi.fn(),
    deleteTask: vi.fn(),
  }),
}));

vi.mock("@/stores/auth", () => ({
  useAuthStore: () => ({
    get user() {
      return mockUser.value;
    },
  }),
}));

vi.mock("pinia", async (importOriginal) => {
  const actual = await importOriginal();

  return {
    ...actual,
    storeToRefs: () => ({
      tasks: mockTasks,
      loading: ref(false),
    }),
  };
});

vi.mock("vue-router", () => ({
  useRoute: () => ({
    params: { id: "1" },
  }),
}));

vi.mock("@/components/admin/project/detail/TaskCard.vue", () => ({
  default: {
    name: "TaskCardStub",
    template: '<div class="task-card-stub" />',
  },
}));

vi.mock("@/components/admin/project/detail/TaskDetailModal.vue", () => ({
  default: {
    name: "TaskDetailModalStub",
    template: '<div class="task-detail-modal-stub" />',
  },
}));

vi.mock("@/components/admin/project/detail/TaskCreateModal.vue", () => ({
  default: {
    name: "TaskCreateModalStub",
    template: '<div class="task-create-modal-stub" />',
  },
}));

import TaskBoard from "@/components/admin/project/detail/TaskBoard.vue";

const factory = () =>
  mount(TaskBoard, {
    global: {
      stubs: {
        VueDraggableNext: { template: '<div class="draggable-stub"><slot /></div>' },
      },
    },
  });

describe("TaskBoard smoke", () => {
  beforeEach(() => {
    mockTasks.value = [];
    mockUser.value = { roles: [] };
  });

  it("shows create button for manager", () => {
    mockUser.value = { roles: [{ name: "manager" }] };

    const wrapper = factory();

    expect(wrapper.text()).toContain("Create New Task");
  });

  it("hides create button for employee", () => {
    mockUser.value = { roles: [{ name: "staff" }] };

    const wrapper = factory();

    expect(wrapper.text()).not.toContain("Create New Task");
  });
});
