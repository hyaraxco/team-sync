import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const { routeState, routerPushMock, performanceReviewStoreMock, performanceReviewRefs, authRefs, routerLinkStub } =
    vi.hoisted(() => ({
        routeState: {
            name: "admin.performance.my-reviews",
        },
        routerPushMock: vi.fn(),
        performanceReviewStoreMock: {
            fetchMyReviews: vi.fn(),
        },
        performanceReviewRefs: {
            myReviews: {
                __v_isRef: true,
                value: [],
            },
            reviewsLoading: {
                __v_isRef: true,
                value: false,
            },
            pagination: {
                __v_isRef: true,
                value: {},
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

vi.mock("@/stores/performanceReview", () => ({
    usePerformanceReviewStore: () => performanceReviewStoreMock,
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
            if (store === performanceReviewStoreMock) {
                return performanceReviewRefs;
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

import MyReviews from "@/views/admin/performance/MyReviews.vue";

const factory = () =>
    mount(MyReviews, {
        global: {
            stubs: {
                RouterLink: routerLinkStub,
            },
        },
    });

describe("MyReviews smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        performanceReviewRefs.myReviews.value = [];
        performanceReviewRefs.reviewsLoading.value = false;
        performanceReviewRefs.error.value = null;
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchMyReviews on mount", () => {
        factory();
        expect(performanceReviewStoreMock.fetchMyReviews).toHaveBeenCalled();
    });

    it("displays loading state", () => {
        performanceReviewRefs.reviewsLoading.value = true;
        const wrapper = factory();
        // View uses a spinner, not text
        expect(wrapper.find(".animate-spin").exists()).toBe(true);
    });

    it("displays empty state when no reviews", () => {
        performanceReviewRefs.myReviews.value = [];
        const wrapper = factory();
        expect(wrapper.text()).toMatch(/no.*reviews/i);
    });

    it("displays reviews when available", () => {
        performanceReviewRefs.myReviews.value = [
            {
                id: 1,
                cycle: { name: "Q1 2026 Review" },
                cycle_id: 1,
                status: "pending_self",
                staff_member: { full_name: "Ahmad Fauzi" },
            },
        ];
        const wrapper = factory();
        expect(wrapper.text()).toContain("Q1 2026 Review");
    });
});
