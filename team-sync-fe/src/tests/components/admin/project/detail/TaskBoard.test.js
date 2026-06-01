import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { ref } from "vue";

const mockTasks = ref([]);
const mockUser = ref({ roles: [] });

const mockUpdateTaskStatus = vi.fn().mockResolvedValue({});
const mockFetchProjectTasks = vi.fn().mockResolvedValue([]);

vi.mock("@/stores/task", () => ({
    useTaskStore: () => ({
        fetchProjectTasks: mockFetchProjectTasks,
        updateTaskStatus: mockUpdateTaskStatus,
        createTask: vi.fn().mockResolvedValue({}),
        deleteTask: vi.fn().mockResolvedValue({}),
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

vi.mock("@/helpers/permissionHelper", () => ({
    can: (permission) => {
        if (!mockUser.value?.permissions) return false;
        return mockUser.value.permissions.includes(permission);
    },
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

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: vi.fn(),
        warning: vi.fn(),
        error: vi.fn(),
    }),
}));

import TaskBoard from "@/components/admin/project/detail/TaskBoard.vue";

const factory = () =>
    mount(TaskBoard, {
        global: {
            stubs: {
                VueDraggableNext: {
                    template: '<div class="draggable-stub"><slot /></div>',
                },
            },
        },
    });

describe("TaskBoard - canMoveTask", () => {
    beforeEach(() => {
        mockTasks.value = [];
        mockUser.value = { roles: [] };
        mockUpdateTaskStatus.mockClear();
        mockFetchProjectTasks.mockClear();
    });

    // --- Reviewer transitions (manager/hr/project_leader) ---
    describe("reviewer transitions", () => {
        it("allows manager to move from review to done", () => {
            mockUser.value = {
                employee_profile: { id: 300 },
                roles: [{ name: "manager" }],
                permissions: ["project-edit", "task-list"],
            };
            const wrapper = factory();
            const task = { id: 1, status: "review", project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "done")).toBe(true);
        });

        it("allows manager to move from review to rejected", () => {
            mockUser.value = {
                employee_profile: { id: 300 },
                roles: [{ name: "manager" }],
                permissions: ["project-edit", "task-list"],
            };
            const wrapper = factory();
            const task = { id: 1, status: "review", project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "rejected")).toBe(true);
        });

        it("denies HR from moving tasks because HR has task oversight only", () => {
            mockUser.value = {
                employee_profile: { id: 300 },
                roles: [{ name: "hr" }],
                permissions: ["task-list"],
            };
            const wrapper = factory();
            const task = { id: 1, status: "done", project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "rejected")).toBe(false);
        });

        it("allows project leader to move from review to done", () => {
            mockUser.value = {
                employee_profile: { id: 500 },
                roles: [{ name: "staff" }],
            };
            const wrapper = factory();
            const task = { id: 1, status: "review", project: { leader: { id: 500 } } };
            expect(wrapper.vm.canMoveTask(task, "done")).toBe(true);
        });

        it("denies manager from moving from review to in_progress", () => {
            mockUser.value = {
                employee_profile: { id: 300 },
                roles: [{ name: "manager" }],
                permissions: ["project-edit", "task-list"],
            };
            const wrapper = factory();
            const task = { id: 1, status: "review", project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "in_progress")).toBe(false);
        });

        it("denies manager from moving from todo to in_progress", () => {
            mockUser.value = {
                employee_profile: { id: 300 },
                roles: [{ name: "manager" }],
                permissions: ["project-edit", "task-list"],
            };
            const wrapper = factory();
            const task = { id: 1, status: "todo", project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "in_progress")).toBe(false);
        });

        it("denies HR from moving from done to review", () => {
            mockUser.value = {
                employee_profile: { id: 300 },
                roles: [{ name: "hr" }],
                permissions: ["task-list"],
            };
            const wrapper = factory();
            const task = { id: 1, status: "done", project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "review")).toBe(false);
        });
    });

    // --- Employee transitions (staff) ---
    describe("employee transitions", () => {
        it("allows staff to move from todo to in_progress (own task)", () => {
            mockUser.value = {
                employee_profile: { id: 100 },
                roles: [{ name: "staff" }],
                permissions: ["task-edit", "task-list"],
            };
            const wrapper = factory();
            const task = { id: 1, status: "todo", assignee_id: 100, project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "in_progress")).toBe(true);
        });

        it("allows staff to move from in_progress to review (own task)", () => {
            mockUser.value = {
                employee_profile: { id: 100 },
                roles: [{ name: "staff" }],
                permissions: ["task-edit", "task-list"],
            };
            const wrapper = factory();
            const task = { id: 1, status: "in_progress", assignee_id: 100, project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "review")).toBe(true);
        });

        it("allows staff to move from rejected to in_progress (own task)", () => {
            mockUser.value = {
                employee_profile: { id: 100 },
                roles: [{ name: "staff" }],
                permissions: ["task-edit", "task-list"],
            };
            const wrapper = factory();
            const task = { id: 1, status: "rejected", assignee_id: 100, project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "in_progress")).toBe(true);
        });

        it("denies staff from moving to done", () => {
            mockUser.value = {
                employee_profile: { id: 100 },
                roles: [{ name: "staff" }],
                permissions: ["task-edit", "task-list"],
            };
            const wrapper = factory();
            const task = { id: 1, status: "review", assignee_id: 100, project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "done")).toBe(false);
        });

        it("denies staff from moving to rejected", () => {
            mockUser.value = {
                employee_profile: { id: 100 },
                roles: [{ name: "staff" }],
            };
            const wrapper = factory();
            const task = { id: 1, status: "review", assignee_id: 100, project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "rejected")).toBe(false);
        });

        it("denies staff from moving someone else's task", () => {
            mockUser.value = {
                employee_profile: { id: 100 },
                roles: [{ name: "staff" }],
            };
            const wrapper = factory();
            const task = { id: 1, status: "todo", assignee_id: 200, project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "in_progress")).toBe(false);
        });

        it("denies staff from moving to same status (no-op returns true)", () => {
            mockUser.value = {
                employee_profile: { id: 100 },
                roles: [{ name: "staff" }],
            };
            const wrapper = factory();
            const task = { id: 1, status: "todo", assignee_id: 100, project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "todo")).toBe(true);
        });
    });

    // --- Edge cases ---
    describe("edge cases", () => {
        it("returns false for null task", () => {
            mockUser.value = {
                employee_profile: { id: 100 },
                roles: [{ name: "staff" }],
            };
            const wrapper = factory();
            expect(wrapper.vm.canMoveTask(null, "done")).toBe(false);
        });

        it("returns true when moving to same status", () => {
            mockUser.value = {
                employee_profile: { id: 300 },
                roles: [{ name: "manager" }],
            };
            const wrapper = factory();
            const task = { id: 1, status: "done", project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "done")).toBe(true);
        });

        it("normalizes pending status to todo", () => {
            mockUser.value = {
                employee_profile: { id: 100 },
                roles: [{ name: "staff" }],
                permissions: ["task-edit", "task-list"],
            };
            const wrapper = factory();
            const task = { id: 1, status: "pending", assignee_id: 100, project: { leader: { id: 999 } } };
            expect(wrapper.vm.canMoveTask(task, "in_progress")).toBe(true);
        });
    });
});

describe("TaskBoard - getMoveDeniedReason", () => {
    beforeEach(() => {
        mockTasks.value = [];
        mockUser.value = { roles: [] };
    });

    it("returns 'Task cannot be moved.' for null task", () => {
        mockUser.value = {
            employee_profile: { id: 100 },
            roles: [{ name: "staff" }],
            permissions: ["task-edit", "task-list"],
        };
        const wrapper = factory();
        expect(wrapper.vm.getMoveDeniedReason(null, "done")).toBe("Task cannot be moved.");
    });

    it("returns 'Task is already in this status.' for same status", () => {
        mockUser.value = {
            employee_profile: { id: 100 },
            roles: [{ name: "staff" }],
            permissions: ["task-edit", "task-list"],
        };
        const wrapper = factory();
        const task = { id: 1, status: "todo", assignee_id: 100, project: { leader: { id: 999 } } };
        expect(wrapper.vm.getMoveDeniedReason(task, "todo")).toBe("Task is already in this status.");
    });

    it("returns 'You can only move your own assigned tasks.' for staff on others task", () => {
        mockUser.value = {
            employee_profile: { id: 100 },
            roles: [{ name: "staff" }],
            permissions: ["task-edit", "task-list"],
        };
        const wrapper = factory();
        const task = { id: 1, status: "todo", assignee_id: 200, project: { leader: { id: 999 } } };
        expect(wrapper.vm.getMoveDeniedReason(task, "in_progress")).toBe("You can only move your own assigned tasks.");
    });

    it("returns 'Invalid status transition for employee workflow.' for invalid staff transition", () => {
        mockUser.value = {
            employee_profile: { id: 100 },
            roles: [{ name: "staff" }],
            permissions: ["task-edit", "task-list"],
        };
        const wrapper = factory();
        const task = { id: 1, status: "todo", assignee_id: 100, project: { leader: { id: 999 } } };
        expect(wrapper.vm.getMoveDeniedReason(task, "done")).toBe("Invalid status transition for employee workflow.");
    });

    it("returns reviewer transition message for manager with invalid transition", () => {
        mockUser.value = {
            employee_profile: { id: 300 },
            roles: [{ name: "manager" }],
            permissions: ["project-edit", "task-list"],
        };
        const wrapper = factory();
        const task = { id: 1, status: "review", project: { leader: { id: 999 } } };
        expect(wrapper.vm.getMoveDeniedReason(task, "in_progress")).toBe(
            "Invalid reviewer transition. Allowed: review -> done/rejected and done -> rejected.",
        );
    });

    it("returns fallback message for non-staff non-reviewer role", () => {
        mockUser.value = {
            employee_profile: { id: 400 },
            roles: [{ name: "finance" }],
        };
        const wrapper = factory();
        const task = { id: 1, status: "review", project: { leader: { id: 999 } } };
        expect(wrapper.vm.getMoveDeniedReason(task, "in_progress")).toBe("You are not allowed to move this task.");
    });
});

describe("TaskBoard - Empty State", () => {
    beforeEach(() => {
        mockTasks.value = [];
        mockUser.value = { roles: [{ name: "staff" }] };
    });

    it("shows empty state when no tasks exist", () => {
        const wrapper = factory();
        expect(wrapper.text()).toContain("No tasks yet");
        expect(wrapper.text()).toContain("Create your first task to get started.");
    });

    it("shows Create Task button in empty state when canCreateTask is true", () => {
        const wrapper = mount(TaskBoard, {
            props: { canCreateTask: true },
            global: {
                stubs: {
                    VueDraggableNext: {
                        template: '<div class="draggable-stub"><slot /></div>',
                    },
                },
            },
        });
        const buttons = wrapper.findAll("button");
        const emptyCreateBtn = buttons.find((b) => b.text().includes("Create Task"));
        expect(emptyCreateBtn).toBeTruthy();
    });

    it("hides Create Task button in empty state when canCreateTask is false", () => {
        const wrapper = mount(TaskBoard, {
            props: { canCreateTask: false },
            global: {
                stubs: {
                    VueDraggableNext: {
                        template: '<div class="draggable-stub"><slot /></div>',
                    },
                },
            },
        });
        const emptyState = wrapper.find(".py-12");
        expect(emptyState.exists()).toBe(true);
        expect(emptyState.find("button").exists()).toBe(false);
    });

    it("shows kanban columns when tasks exist", () => {
        mockTasks.value = [{ id: 1, status: "todo", name: "Test Task", project: { leader: { id: 999 } } }];
        const wrapper = factory();
        expect(wrapper.text()).not.toContain("No tasks yet");
        expect(wrapper.text()).toContain("To Do");
    });
});
