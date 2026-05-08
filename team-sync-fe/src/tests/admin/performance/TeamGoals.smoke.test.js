import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const { routerPushMock, performanceGoalStoreMock, performanceGoalRefs } = vi.hoisted(() => ({
    routerPushMock: vi.fn(),
    performanceGoalStoreMock: {
        fetchTeamGoals: vi.fn(),
    },
    performanceGoalRefs: {
        teamGoals: {
            __v_isRef: true,
            value: [],
        },
        goalsLoading: {
            __v_isRef: true,
            value: false,
        },
    },
}));

vi.mock("@/stores/performanceGoal", () => ({
    usePerformanceGoalStore: () => performanceGoalStoreMock,
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: () => performanceGoalRefs,
    };
});

vi.mock("vue-router", () => ({
    useRouter: () => ({
        push: routerPushMock,
    }),
}));

import TeamGoals from "@/views/admin/performance/TeamGoals.vue";

const factory = () => mount(TeamGoals);

describe("TeamGoals smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        performanceGoalRefs.teamGoals.value = [];
        performanceGoalRefs.goalsLoading.value = false;
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchTeamGoals on mount", () => {
        factory();
        expect(performanceGoalStoreMock.fetchTeamGoals).toHaveBeenCalled();
    });

    it("displays loading state", () => {
        performanceGoalRefs.goalsLoading.value = true;
        const wrapper = factory();
        expect(wrapper.find(".animate-spin").exists()).toBe(true);
    });

    it("displays empty state when no goals", () => {
        performanceGoalRefs.teamGoals.value = [];
        const wrapper = factory();
        expect(wrapper.text()).toMatch(/no team goals found/i);
    });

    it("displays goal cards when data available", () => {
        performanceGoalRefs.teamGoals.value = [
            {
                id: 7,
                title: "Increase sprint throughput",
                description: "Improve delivery output by 15%",
                goal_type: "kpi",
                status: "in_progress",
                completion_percentage: 45,
                due_date: "2026-12-31",
                category: "Engineering",
                assignee: {
                    full_name: "Jane Manager",
                },
            },
        ];

        const wrapper = factory();
        expect(wrapper.text()).toContain("Increase sprint throughput");
        expect(wrapper.text()).toContain("Jane Manager");
    });
});
