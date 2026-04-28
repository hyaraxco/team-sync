import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const {
    routeState,
    routerBackMock,
    performanceGoalStoreMock,
    performanceGoalRefs,
    authStoreMock,
    authRefs,
} = vi.hoisted(() => ({
    routeState: {
        params: { id: "42" },
        name: "admin.performance.goal.detail",
    },
    routerBackMock: vi.fn(),
    performanceGoalStoreMock: {
        fetchGoalById: vi.fn(),
        fetchProgressUpdates: vi.fn(),
    },
    performanceGoalRefs: {
        currentGoal: {
            __v_isRef: true,
            value: null,
        },
        goalsLoading: {
            __v_isRef: true,
            value: false,
        },
        goalUpdates: {
            __v_isRef: true,
            value: [],
        },
        updatesLoading: {
            __v_isRef: true,
            value: false,
        },
    },
    authStoreMock: {},
    authRefs: {
        user: {
            __v_isRef: true,
            value: {
                id: 1,
                name: "John Doe",
            },
        },
    },
}));

vi.mock("@/stores/performanceGoal", () => ({
    usePerformanceGoalStore: () => performanceGoalStoreMock,
}));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => authStoreMock,
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: (store) => {
            if (store === performanceGoalStoreMock) {
                return performanceGoalRefs;
            }
            return authRefs;
        },
    };
});

vi.mock("vue-router", () => ({
    useRoute: () => routeState,
    useRouter: () => ({
        back: routerBackMock,
    }),
}));

import GoalDetail from "@/views/admin/performance/GoalDetail.vue";

const factory = () => mount(GoalDetail);

describe("GoalDetail smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        performanceGoalRefs.currentGoal.value = null;
        performanceGoalRefs.goalsLoading.value = false;
        performanceGoalRefs.goalUpdates.value = [];
        performanceGoalRefs.updatesLoading.value = false;
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchGoalById on mount with route param", () => {
        factory();
        expect(performanceGoalStoreMock.fetchGoalById).toHaveBeenCalledWith("42");
    });

    it("displays loading state", () => {
        performanceGoalRefs.goalsLoading.value = true;
        const wrapper = factory();
        expect(wrapper.find(".animate-spin").exists()).toBe(true);
    });

    it("displays goal data when available", () => {
        performanceGoalRefs.currentGoal.value = {
            id: 42,
            title: "Increase team delivery velocity",
            description: "Reduce cycle time by 20%",
            goal_type: "kpi",
            category: "Engineering",
            status: "in_progress",
            completion_percentage: 60,
            start_date: "2026-01-01",
            due_date: "2026-12-31",
            target_value: 20,
            current_value: 12,
            unit: "%",
        };

        const wrapper = factory();
        expect(wrapper.text()).toContain("Increase team delivery velocity");
        expect(wrapper.text()).toContain("Engineering");
    });

    it("displays empty state for progress updates", () => {
        performanceGoalRefs.currentGoal.value = {
            id: 42,
            title: "Goal without updates",
            goal_type: "okr",
            status: "not_started",
            completion_percentage: 0,
        };
        performanceGoalRefs.goalUpdates.value = [];

        const wrapper = factory();
        expect(wrapper.text()).toMatch(/no progress updates yet/i);
    });
});
