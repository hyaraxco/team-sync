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
    canMock,
} = vi.hoisted(() => ({
    routeState: {
        params: {
            id: "81",
        },
    },
    projectStoreMock: {
        fetchProject: vi.fn(),
        fetchProjectSquadSummary: vi.fn(),
        fetchEligibleLeaders: vi.fn(),
        updateProjectLeader: vi.fn(),
        deleteProject: vi.fn(),
    },
    routerPushMock: vi.fn(),
    formatDateMock: vi.fn((value) => value || "N/A"),
    calculateDurationMock: vi.fn(() => "4 months"),
    formatRupiahMock: vi.fn((value) => `Rp ${value}`),
    canMock: vi.fn(() => true),
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

vi.mock("@/helpers/permissionHelper", () => ({
    can: (permission) => canMock(permission),
    canOneOf: (permissions) => permissions.some((p) => canMock(p)),
}));

vi.mock("@/utils/badgeUtils", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        getPriorityColor: () => "bg-yellow-100 text-yellow-700",
        getProjectStatusColor: () => "bg-green-100 text-green-700",
        getProgressColor: () => "bg-green-500",
    };
});

import ProjectDetail from "@/views/admin/project/ProjectDetail.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(ProjectDetail, {
        global: {
            stubs: {
                TaskBoard: {
                    props: ["canCreateTask"],
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
                ModalWrapper: {
                    props: ["show", "title", "maxWidth"],
                    template:
                        '<div v-if="show" class="modal-wrapper-stub"><slot name="header" /><slot /><slot name="footer" /></div>',
                },
                ConfirmationModal: {
                    props: ["show", "title", "message", "confirmText", "cancelText", "type", "loading"],
                    template: '<div v-if="show" class="confirmation-modal-stub"></div>',
                },
            },
        },
    });

describe("ProjectDetail smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        canMock.mockImplementation(() => true);
        routeState.params.id = "81";

        projectStoreMock.fetchProject.mockResolvedValue({
            id: 81,
            name: "Mobile Revamp",
            description: "Improve delivery velocity.",
            status: "active",
            priority: "high",
            photo: "https://example.com/photo.png",
            budget: 120000000,
            start_date: "2026-05-01",
            end_date: "2026-08-31",
            can_create_task: true,
            is_project_leader: false,
            teams: [
                {
                    id: 2,
                    name: "Platform Team",
                    members_count: 6,
                },
            ],
            leader: {
                id: 11,
                user: {
                    name: "Ari Leader",
                    profile_photo: null,
                },
                job_information: {
                    job_title: "Engineering Manager",
                },
            },
            tasks: [
                { id: 1, status: "done" },
                { id: 2, status: "in_progress" },
            ],
        });
        projectStoreMock.fetchProjectSquadSummary.mockResolvedValue({
            headcount: {
                total: 6,
                by_stream: {
                    frontend: 2,
                    backend: 3,
                    qa: 1,
                },
            },
            tasks: {
                total: 2,
                by_status: {
                    done: 1,
                    in_progress: 1,
                },
            },
        });
        projectStoreMock.fetchEligibleLeaders.mockResolvedValue([]);
        projectStoreMock.updateProjectLeader.mockResolvedValue(null);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchProject on mount", async () => {
        factory();
        await flushAsync();

        expect(projectStoreMock.fetchProject).toHaveBeenCalledWith("81");
        expect(projectStoreMock.fetchProjectSquadSummary).toHaveBeenCalledWith("81");
    });

    it("skips fetchProjectSquadSummary when user lacks project-statistic permission", async () => {
        canMock.mockImplementation((perm) => perm !== "project-statistic");

        factory();
        await flushAsync();

        expect(projectStoreMock.fetchProject).toHaveBeenCalledWith("81");
        expect(projectStoreMock.fetchProjectSquadSummary).not.toHaveBeenCalled();
    });

    it("fetches squad summary when user is project leader despite lacking project-statistic", async () => {
        canMock.mockImplementation((perm) => perm !== "project-statistic");
        projectStoreMock.fetchProject.mockResolvedValue({
            id: 81,
            name: "Leader Project",
            status: "active",
            priority: "high",
            budget: 0,
            start_date: "2026-05-01",
            end_date: "2026-08-31",
            can_create_task: true,
            is_project_leader: true,
            teams: [],
            leader: { id: 11, user: { name: "Leader" }, job_information: { job_title: "Lead" } },
            tasks: [],
        });

        factory();
        await flushAsync();

        expect(projectStoreMock.fetchProject).toHaveBeenCalledWith("81");
        expect(projectStoreMock.fetchProjectSquadSummary).toHaveBeenCalledWith("81");
    });

    it("hides Danger Zone when user lacks project-delete", async () => {
        canMock.mockImplementation((perm) => perm !== "project-delete");

        const wrapper = factory();
        await flushAsync();

        expect(wrapper.text()).not.toContain("Delete Project");
    });

    it("shows Change Project Leader button when user has project-edit", async () => {
        canMock.mockImplementation(() => true);

        const wrapper = factory();
        await flushAsync();

        expect(wrapper.text()).toContain("Change Project Leader");
    });

    it("hides Change Project Leader button when user lacks project-edit", async () => {
        canMock.mockImplementation((perm) => perm !== "project-edit");

        const wrapper = factory();
        await flushAsync();

        expect(wrapper.text()).not.toContain("Change Project Leader");
    });

    it("hides Profile link when user lacks staff-member-list", async () => {
        canMock.mockImplementation((perm) => perm !== "staff-member-list");

        const wrapper = factory();
        await flushAsync();

        const profileLink = wrapper.find(".router-link-stub");
        expect(profileLink.exists()).toBe(false);
    });

    it("handles profile link interaction", async () => {
        const wrapper = factory();
        await flushAsync();

        const profileLink = wrapper.find(".router-link-stub");
        await profileLink.trigger("click");

        expect(profileLink.exists()).toBe(true);
    });
});
