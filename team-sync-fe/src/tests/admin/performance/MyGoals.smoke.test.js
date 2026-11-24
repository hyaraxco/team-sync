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
        authRefs.user.value = {
            id: 1,
            name: "Ahmad Fauzi",
            email: "john@example.com",
            roles: ["staff"],
        };
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
        expect(wrapper.text()).toMatch(/belum ada sasaran/i);
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

    it("passes staff_member_id from auth store when creating a goal", async () => {
        authRefs.user.value = {
            id: 1,
            name: "Ahmad Fauzi",
            email: "john@example.com",
            roles: ["staff"],
            permissions: ["goal-create-own"],
            employee_profile: { id: 42 },
        };
        performanceGoalStoreMock.createGoal.mockResolvedValue({ id: 99 });
        performanceGoalStoreMock.fetchMyGoals.mockResolvedValue({ data: [] });

        const wrapper = factory();

        const createBtn = wrapper.findAll("button").find((b) => b.text().includes("Create Goal"));
        expect(createBtn.exists()).toBe(true);
        await createBtn.trigger("click");
        await wrapper.vm.$nextTick();

        const modal = wrapper.find(".fixed");
        expect(modal.exists()).toBe(true);

        await modal.find("input[type='text']").setValue("Test Goal");

        const dateInputs = modal.findAll("input[type='date']");
        await dateInputs[0].setValue("2026-01-01");
        await dateInputs[1].setValue("2026-12-01");

        await modal.find("form").trigger("submit");
        await wrapper.vm.$nextTick();

        expect(performanceGoalStoreMock.createGoal).toHaveBeenCalledOnce();
        expect(performanceGoalStoreMock.createGoal.mock.calls[0][0]).toHaveProperty("staff_member_id", 42);
    });
});
