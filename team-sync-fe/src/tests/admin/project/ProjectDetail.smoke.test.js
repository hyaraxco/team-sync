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
            id: "81",
        },
    },
    projectStoreMock: {
        fetchProject: vi.fn(),
        fetchProjectSquadSummary: vi.fn(),
    },
    routerPushMock: vi.fn(),
    formatDateMock: vi.fn((value) => value || "N/A"),
    calculateDurationMock: vi.fn(() => "4 months"),
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
                    template: '<div class="task-board-stub"></div>',
                },
                EmptyState: {
                    template: '<div class="empty-state-stub"></div>',
                },
                AnimatedValue: {
                    props: ["value", "suffix"],
                    template:
                        '<span class="animated-value-stub">{{ value }}{{ suffix || "" }}</span>',
                },
                RouterLink: {
                    props: ["to"],
                    template: '<a class="router-link-stub"><slot /></a>',
                },
            },
        },
    });

describe("ProjectDetail smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
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
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchProject on mount", async () => {
        factory();
        await flushAsync();

        expect(projectStoreMock.fetchProject).toHaveBeenCalledWith("81");
        expect(projectStoreMock.fetchProjectSquadSummary).toHaveBeenCalledWith(
            "81",
        );
    });

    it("handles profile link interaction", async () => {
        const wrapper = factory();
        await flushAsync();

        const profileLink = wrapper.find(".router-link-stub");
        await profileLink.trigger("click");

        expect(profileLink.exists()).toBe(true);
    });
});
