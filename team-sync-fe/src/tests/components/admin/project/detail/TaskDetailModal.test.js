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

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: vi.fn(),
        warning: vi.fn(),
        error: vi.fn(),
    }),
}));

vi.mock("@/helpers/permissionHelper", () => ({
    can: (permission) => {
        const permissions = mockAuthUser.value?.permissions || [];
        return permissions.includes(permission);
    },
    canOneOf: (permissions) => {
        const userPermissions = mockAuthUser.value?.permissions || [];
        return permissions.some((p) => userPermissions.includes(p));
    },
}));

import TaskDetailModal from "@/components/admin/project/detail/TaskDetailModal.vue";

// Subset of backend RolePermissionSeeder — only the permissions exercised by
// TaskDetailModal's permission gates. Not an exhaustive mirror of the seeder.
const ROLE_PERMISSIONS = {
    manager: ["project-edit", "project-delete", "project-statistic", "task-list"],
    hr: ["task-list", "staff-member-list"],
    staff: ["task-edit", "task-list"],
    "project-leader": ["task-create", "task-edit", "task-delete", "task-list"],
};

const buildUser = (roleName, overrides = {}) => ({
    employee_profile: { id: 300 },
    roles: [{ name: roleName }],
    permissions: ROLE_PERMISSIONS[roleName] || [],
    ...overrides,
});

const makeTask = (overrides = {}) => ({
    id: 10,
    name: "Test Task",
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
                    props: ["type", "value"],
                    template: "<span>{{ value }}</span>",
                },
            },
        },
    });
};

describe("TaskDetailModal - Permission Matrix", () => {
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

    // --- canManageAssignee (task-edit + (project-edit OR isProjectLeader) + !locked) ---
    describe("canManageAssignee", () => {
        it("returns true when user has task-edit and project-edit and status is todo", () => {
            const wrapper = factory(
                makeTask({ status: "todo" }),
                {
                    employee_profile: { id: 300 },
                    roles: [{ name: "manager" }],
                    permissions: ["task-edit", "project-edit"],
                },
            );
            expect(wrapper.vm.canManageAssignee).toBe(true);
        });

        it("returns true when user has task-edit and is project leader", () => {
            const wrapper = factory(
                makeTask({ status: "todo", project: { leader: { id: 500 } } }),
                {
                    employee_profile: { id: 500 },
                    roles: [{ name: "staff" }],
                    permissions: ["task-edit"],
                },
            );
            expect(wrapper.vm.canManageAssignee).toBe(true);
        });

        it("returns false when user lacks task-edit even with project-edit", () => {
            const wrapper = factory(
                makeTask({ status: "todo" }),
                {
                    employee_profile: { id: 300 },
                    roles: [{ name: "manager" }],
                    permissions: ["project-edit"],
                },
            );
            expect(wrapper.vm.canManageAssignee).toBe(false);
        });

        it("returns false when user has task-edit but no project-edit and is not project leader", () => {
            const wrapper = factory(
                makeTask({ status: "todo", project: { leader: { id: 999 } } }),
                {
                    employee_profile: { id: 100 },
                    roles: [{ name: "staff" }],
                    permissions: ["task-edit"],
                },
            );
            expect(wrapper.vm.canManageAssignee).toBe(false);
        });

        it("returns false for staff with no permissions", () => {
            const wrapper = factory(
                makeTask({ status: "todo" }),
                buildUser("staff", { permissions: [] }),
            );
            expect(wrapper.vm.canManageAssignee).toBe(false);
        });

        it("returns false when status is review (review phase locked)", () => {
            const wrapper = factory(
                makeTask({ status: "review" }),
                {
                    employee_profile: { id: 300 },
                    roles: [{ name: "manager" }],
                    permissions: ["task-edit", "project-edit"],
                },
            );
            expect(wrapper.vm.canManageAssignee).toBe(false);
        });

        it("returns false when status is done (review phase locked)", () => {
            const wrapper = factory(
                makeTask({ status: "done" }),
                {
                    employee_profile: { id: 300 },
                    roles: [{ name: "manager" }],
                    permissions: ["task-edit", "project-edit"],
                },
            );
            expect(wrapper.vm.canManageAssignee).toBe(false);
        });
    });

    // --- canEditDueDate (task-edit + project-edit + !locked) ---
    describe("canEditDueDate", () => {
        it("returns true when user has both task-edit and project-edit and task is todo", () => {
            const wrapper = factory(
                makeTask({ status: "todo" }),
                {
                    employee_profile: { id: 300 },
                    roles: [{ name: "manager" }],
                    permissions: ["task-edit", "project-edit"],
                },
            );
            expect(wrapper.vm.canEditDueDate).toBe(true);
        });

        it("returns true when user has both permissions and task is in_progress", () => {
            const wrapper = factory(
                makeTask({ status: "in_progress" }),
                {
                    employee_profile: { id: 300 },
                    roles: [{ name: "manager" }],
                    permissions: ["task-edit", "project-edit"],
                },
            );
            expect(wrapper.vm.canEditDueDate).toBe(true);
        });

        it("returns false when user lacks project-edit", () => {
            const wrapper = factory(
                makeTask({ status: "todo" }),
                {
                    employee_profile: { id: 300 },
                    roles: [{ name: "staff" }],
                    permissions: ["task-edit"],
                },
            );
            expect(wrapper.vm.canEditDueDate).toBe(false);
        });

        it("returns false when user lacks task-edit", () => {
            const wrapper = factory(
                makeTask({ status: "todo" }),
                {
                    employee_profile: { id: 300 },
                    roles: [{ name: "manager" }],
                    permissions: ["project-edit"],
                },
            );
            expect(wrapper.vm.canEditDueDate).toBe(false);
        });

        it("returns false when status is review (locked)", () => {
            const wrapper = factory(
                makeTask({ status: "review" }),
                {
                    employee_profile: { id: 300 },
                    roles: [{ name: "manager" }],
                    permissions: ["task-edit", "project-edit"],
                },
            );
            expect(wrapper.vm.canEditDueDate).toBe(false);
        });

        it("returns false when status is done (locked)", () => {
            const wrapper = factory(
                makeTask({ status: "done" }),
                {
                    employee_profile: { id: 300 },
                    roles: [{ name: "manager" }],
                    permissions: ["task-edit", "project-edit"],
                },
            );
            expect(wrapper.vm.canEditDueDate).toBe(false);
        });
    });

    // --- canDeleteTask (task-delete) ---
    describe("canDeleteTask", () => {
        it("returns true when user has task-delete permission", () => {
            const wrapper = factory(
                makeTask({ status: "todo" }),
                {
                    employee_profile: { id: 300 },
                    roles: [{ name: "staff" }],
                    permissions: ["task-delete"],
                },
            );
            expect(wrapper.vm.canDeleteTask).toBe(true);
        });

        it("returns false when user lacks task-delete", () => {
            const wrapper = factory(
                makeTask({ status: "todo" }),
                buildUser("manager"),
            );
            expect(wrapper.vm.canDeleteTask).toBe(false);
        });

        it("returns false for staff", () => {
            const wrapper = factory(
                makeTask({ status: "todo" }),
                buildUser("staff"),
            );
            expect(wrapper.vm.canDeleteTask).toBe(false);
        });

        it("returns false for HR", () => {
            const wrapper = factory(
                makeTask({ status: "todo" }),
                buildUser("hr"),
            );
            expect(wrapper.vm.canDeleteTask).toBe(false);
        });
    });

    // --- canReviewTask ---
    describe("canReviewTask", () => {
        it("returns true for manager", () => {
            const wrapper = factory(
                makeTask({ status: "review" }),
                { employee_profile: { id: 300 }, roles: [{ name: "manager" }] },
            );
            expect(wrapper.vm.canReviewTask).toBe(true);
        });

        it("returns true for HR", () => {
            const wrapper = factory(
                makeTask({ status: "review" }),
                { employee_profile: { id: 300 }, roles: [{ name: "hr" }] },
            );
            expect(wrapper.vm.canReviewTask).toBe(true);
        });

        it("returns true for project leader", () => {
            const wrapper = factory(
                makeTask({ status: "review", project: { leader: { id: 300 } } }),
                { employee_profile: { id: 300 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canReviewTask).toBe(true);
        });

        it("returns false for staff (non-leader)", () => {
            const wrapper = factory(
                makeTask({ status: "review" }),
                { employee_profile: { id: 300 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canReviewTask).toBe(false);
        });
    });

    // --- canStartTask (employee workflow) ---
    describe("canStartTask", () => {
        it("returns true for staff when task is todo and assigned to them", () => {
            const wrapper = factory(
                makeTask({ status: "todo", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canStartTask).toBe(true);
        });

        it("returns false for staff when task is todo but not assigned to them", () => {
            const wrapper = factory(
                makeTask({ status: "todo", assignee_id: 200 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canStartTask).toBe(false);
        });

        it("returns false for staff when status is in_progress", () => {
            const wrapper = factory(
                makeTask({ status: "in_progress", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canStartTask).toBe(false);
        });

        it("returns false for manager (not staff role)", () => {
            const wrapper = factory(
                makeTask({ status: "todo", assignee_id: 300 }),
                { employee_profile: { id: 300 }, roles: [{ name: "manager" }] },
            );
            expect(wrapper.vm.canStartTask).toBe(false);
        });

        it("returns true for pending status (normalized to todo-like)", () => {
            const wrapper = factory(
                makeTask({ status: "pending", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canStartTask).toBe(true);
        });
    });

    // --- canSubmitForReview ---
    describe("canSubmitForReview", () => {
        it("returns true for staff when task is in_progress and assigned to them", () => {
            const wrapper = factory(
                makeTask({ status: "in_progress", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canSubmitForReview).toBe(true);
        });

        it("returns false when task is todo", () => {
            const wrapper = factory(
                makeTask({ status: "todo", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canSubmitForReview).toBe(false);
        });

        it("returns false when not own task", () => {
            const wrapper = factory(
                makeTask({ status: "in_progress", assignee_id: 200 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canSubmitForReview).toBe(false);
        });
    });

    // --- canApproveReview / canRejectReview ---
    describe("canApproveReview", () => {
        it("returns true for manager when status is review", () => {
            const wrapper = factory(
                makeTask({ status: "review" }),
                { employee_profile: { id: 300 }, roles: [{ name: "manager" }] },
            );
            expect(wrapper.vm.canApproveReview).toBe(true);
        });

        it("returns false when status is not review", () => {
            const wrapper = factory(
                makeTask({ status: "in_progress" }),
                { employee_profile: { id: 300 }, roles: [{ name: "manager" }] },
            );
            expect(wrapper.vm.canApproveReview).toBe(false);
        });

        it("returns false for staff when status is review", () => {
            const wrapper = factory(
                makeTask({ status: "review", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canApproveReview).toBe(false);
        });
    });

    describe("canRejectReview", () => {
        it("returns true for HR when status is review", () => {
            const wrapper = factory(
                makeTask({ status: "review" }),
                { employee_profile: { id: 300 }, roles: [{ name: "hr" }] },
            );
            expect(wrapper.vm.canRejectReview).toBe(true);
        });

        it("returns false when status is done", () => {
            const wrapper = factory(
                makeTask({ status: "done" }),
                { employee_profile: { id: 300 }, roles: [{ name: "hr" }] },
            );
            expect(wrapper.vm.canRejectReview).toBe(false);
        });
    });

    // --- canReopenDoneAsRejected ---
    describe("canReopenDoneAsRejected", () => {
        it("returns true for manager when status is done", () => {
            const wrapper = factory(
                makeTask({ status: "done" }),
                { employee_profile: { id: 300 }, roles: [{ name: "manager" }] },
            );
            expect(wrapper.vm.canReopenDoneAsRejected).toBe(true);
        });

        it("returns false when status is not done", () => {
            const wrapper = factory(
                makeTask({ status: "review" }),
                { employee_profile: { id: 300 }, roles: [{ name: "manager" }] },
            );
            expect(wrapper.vm.canReopenDoneAsRejected).toBe(false);
        });
    });

    // --- canStartRework ---
    describe("canStartRework", () => {
        it("returns true for staff when status is rejected and assigned to them", () => {
            const wrapper = factory(
                makeTask({ status: "rejected", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canStartRework).toBe(true);
        });

        it("returns false when status is review", () => {
            const wrapper = factory(
                makeTask({ status: "review", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canStartRework).toBe(false);
        });
    });

    // --- canCollaborateTask ---
    describe("canCollaborateTask", () => {
        it("returns true for manager when status is todo", () => {
            const wrapper = factory(
                makeTask({ status: "todo" }),
                { employee_profile: { id: 300 }, roles: [{ name: "manager" }] },
            );
            expect(wrapper.vm.canCollaborateTask).toBe(true);
        });

        it("returns true for manager when status is review", () => {
            const wrapper = factory(
                makeTask({ status: "review" }),
                { employee_profile: { id: 300 }, roles: [{ name: "manager" }] },
            );
            expect(wrapper.vm.canCollaborateTask).toBe(true);
        });

        it("returns false for manager when status is done (terminal)", () => {
            const wrapper = factory(
                makeTask({ status: "done" }),
                { employee_profile: { id: 300 }, roles: [{ name: "manager" }] },
            );
            expect(wrapper.vm.canCollaborateTask).toBe(false);
        });

        it("returns false for manager when status is cancelled (terminal)", () => {
            const wrapper = factory(
                makeTask({ status: "cancelled" }),
                { employee_profile: { id: 300 }, roles: [{ name: "manager" }] },
            );
            expect(wrapper.vm.canCollaborateTask).toBe(false);
        });

        it("returns true for staff when status is in_progress and own task", () => {
            const wrapper = factory(
                makeTask({ status: "in_progress", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canCollaborateTask).toBe(true);
        });

        it("returns false for staff when status is in_progress but not own task", () => {
            const wrapper = factory(
                makeTask({ status: "in_progress", assignee_id: 200 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canCollaborateTask).toBe(false);
        });

        it("returns true for staff when status is rejected, own task, and needs_revision", () => {
            const wrapper = factory(
                makeTask({ status: "rejected", assignee_id: 100, needs_revision: true }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canCollaborateTask).toBe(true);
        });

        it("returns false for staff when status is rejected, own task, but no needs_revision", () => {
            const wrapper = factory(
                makeTask({ status: "rejected", assignee_id: 100, needs_revision: false }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canCollaborateTask).toBe(false);
        });

        it("returns false for staff when status is todo", () => {
            const wrapper = factory(
                makeTask({ status: "todo", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canCollaborateTask).toBe(false);
        });
    });

    // --- canMutateEntityOwner ---
    describe("canMutateEntityOwner", () => {
        it("returns true when current user owns entity and task is not terminal", () => {
            const wrapper = factory(
                makeTask({ status: "in_progress", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canMutateEntityOwner(100)).toBe(true);
        });

        it("returns false when current user does not own entity", () => {
            const wrapper = factory(
                makeTask({ status: "in_progress", assignee_id: 200 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canMutateEntityOwner(200)).toBe(false);
        });

        it("returns false when task is terminal (done)", () => {
            const wrapper = factory(
                makeTask({ status: "done", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canMutateEntityOwner(100)).toBe(false);
        });

        it("returns false when task is terminal (cancelled)", () => {
            const wrapper = factory(
                makeTask({ status: "cancelled", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.vm.canMutateEntityOwner(100)).toBe(false);
        });
    });

    // --- Integration: role + status matrix rendering ---
    describe("UI action rendering", () => {
        it("renders reviewer actions (approve/reject) for manager on review status", () => {
            const wrapper = factory(
                makeTask({ status: "review" }),
                { employee_profile: { id: 300 }, roles: [{ name: "manager" }] },
            );
            expect(wrapper.text()).toContain("Approve and Mark Done");
            expect(wrapper.text()).toContain("Reject Task");
            expect(wrapper.text()).not.toContain("Start Task");
        });

        it("renders employee submit action for own in_progress task", () => {
            const wrapper = factory(
                makeTask({ status: "in_progress", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.text()).toContain("Task Selesai - Submit for Review");
            expect(wrapper.text()).not.toContain("Approve and Mark Done");
        });

        it("locks comment textarea on done status", () => {
            const wrapper = factory(
                makeTask({ status: "done" }),
                { employee_profile: { id: 300 }, roles: [{ name: "manager" }] },
            );
            const commentTextarea = wrapper.find(
                'textarea[placeholder="Comments are locked on completed/cancelled tasks"]',
            );
            expect(commentTextarea.exists()).toBe(true);
            expect(commentTextarea.attributes("disabled")).toBeDefined();
        });

        it("shows 'No task actions' for staff with no own tasks on todo status", () => {
            const wrapper = factory(
                makeTask({ status: "todo", assignee_id: 200 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.text()).toContain("No task actions available");
        });

        it("shows Start Task button for staff on own todo task", () => {
            const wrapper = factory(
                makeTask({ status: "todo", assignee_id: 100 }),
                { employee_profile: { id: 100 }, roles: [{ name: "staff" }] },
            );
            expect(wrapper.text()).toContain("Start Task");
        });
    });
});
