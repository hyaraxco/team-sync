import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const { routeState, routerPushMock, performanceGoalStoreMock, performanceGoalRefs, authRefs, routerLinkStub } =
    vi.hoisted(() => ({
        routeState: {
            name: "admin.performance.my-goals",
        },
        routerPushMock: vi.fn(),
        performanceGoalStoreMock: {
            fetchMyGoals: vi.fn(),
            createGoal: vi.fn(),
        },
        performanceGoalRefs: {
            myGoals: {
                __v_isRef: true,
                value: [],
            },
            goalsLoading: {
                __v_isRef: true,
                value: false,
            },
            error: {
                __v_isRef: true,
                value: null,
            },
        },
        authRefs: {
            user: {
                __v_isRef: true,
                value: {
                    id: 1,
                    name: "Ahmad Fauzi",
                    email: "john@example.com",
                    roles: ["staff"],
                },
            },
        },
        routerLinkStub: {
            name: "RouterLink",
            props: ["to"],
            template: '<a class="router-link-stub"><slot /></a>',
        },
    }));

vi.mock("@/stores/performanceGoal", () => ({
    usePerformanceGoalStore: () => performanceGoalStoreMock,
}));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => ({
        user: authRefs.user.value,
    }),
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
        push: routerPushMock,
    }),
    RouterLink: routerLinkStub,
}));

import MyGoals from "@/views/admin/performance/MyGoals.vue";

const factory = () =>
    mount(MyGoals, {
        global: {
            stubs: {
                RouterLink: routerLinkStub,
            },
        },
    });

describe("MyGoals smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        performanceGoalRefs.myGoals.value = [];
        performanceGoalRefs.goalsLoading.value = false;
        performanceGoalRefs.error.value = null;
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchMyGoals on mount", () => {
        factory();
        expect(performanceGoalStoreMock.fetchMyGoals).toHaveBeenCalled();
    });

    it("displays loading state", () => {
        performanceGoalRefs.goalsLoading.value = true;
        const wrapper = factory();
        // View uses a spinner, not text
        expect(wrapper.find(".animate-spin").exists()).toBe(true);
    });

    it("displays empty state when no goals", () => {
        performanceGoalRefs.myGoals.value = [];
        const wrapper = factory();
        expect(wrapper.text()).toMatch(/no.*goals/i);
    });

    it("displays goals when available", () => {
        performanceGoalRefs.myGoals.value = [
            {
                id: 1,
                title: "Complete Laravel certification",
                description: "Get certified in Laravel",
                status: "in_progress",
                completion_percentage: 50,
                goal_type: "development",
                target_value: 100,
                unit: "hours",
                start_date: "2026-01-01",
                due_date: "2026-12-31",
            },
        ];
        const wrapper = factory();
        expect(wrapper.text()).toContain("Complete Laravel certification");
    });

    it("displays goal progress", () => {
        performanceGoalRefs.myGoals.value = [
            {
                id: 1,
                title: "Test Goal",
                description: "Test description",
                status: "in_progress",
                completion_percentage: 75,
                goal_type: "kpi",
                target_value: 100,
                unit: "%",
                start_date: "2026-01-01",
                due_date: "2026-12-31",
            },
        ];
        const wrapper = factory();
        expect(wrapper.text()).toContain("75");
    });
});
