import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
    reviewStoreMock,
    reviewStoreRefs,
    routerPushMock,
    routeState,
} = vi.hoisted(() => ({
    reviewStoreMock: {
        fetchCycleById: vi.fn(),
        fetchTopsisRanking: vi.fn(),
    },
    reviewStoreRefs: {
        currentCycle: {
            __v_isRef: true,
            value: null,
        },
        cyclesLoading: {
            __v_isRef: true,
            value: false,
        },
        topsisResult: {
            __v_isRef: true,
            value: null,
        },
        topsisLoading: {
            __v_isRef: true,
            value: false,
        },
    },
    routerPushMock: vi.fn(),
    routeState: {
        params: { id: "12" },
    },
}));

vi.mock("@/stores/performanceReview", () => ({
    usePerformanceReviewStore: () => reviewStoreMock,
}));

vi.mock("vue-router", () => ({
    useRoute: () => routeState,
    useRouter: () => ({
        push: routerPushMock,
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: (store) => {
            if (store === reviewStoreMock) {
                return reviewStoreRefs;
            }
            return {};
        },
    };
});

import ReviewCycleDetail from "@/views/admin/performance/ReviewCycleDetail.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(ReviewCycleDetail, {
        global: {
            stubs: {
                MainCard: { template: '<div class="main-card-stub"><slot /></div>' },
                StatusBadge: { template: '<div class="status-badge-stub"></div>' },
                GeneratedReviewsList: {
                    props: ["cycle"],
                    template: '<div class="generated-reviews-list-stub">{{ cycle?.name }}</div>',
                },
            },
        },
    });

describe("ReviewCycleDetail smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        reviewStoreRefs.currentCycle.value = {
            id: 12,
            name: "Q1 2026 Review Cycle",
            status: "active",
            cycle_type: "quarterly",
            review_period_start: "2026-01-01",
            review_period_end: "2026-03-31",
            start_date: "2026-04-01",
            end_date: "2026-04-30",
            self_assessment_deadline: null,
            manager_assessment_deadline: null,
        };
        reviewStoreRefs.cyclesLoading.value = false;
        reviewStoreRefs.topsisResult.value = null;
        reviewStoreRefs.topsisLoading.value = false;
        reviewStoreMock.fetchCycleById.mockResolvedValue(undefined);
        reviewStoreMock.fetchTopsisRanking.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchCycleById on mount", async () => {
        factory();
        await flushAsync();

        expect(reviewStoreMock.fetchCycleById).toHaveBeenCalledWith(12);
    });

    it("navigates back when back button is clicked", async () => {
        const wrapper = factory();
        const backButton = wrapper.find("button");

        await backButton.trigger("click");

        expect(routerPushMock).toHaveBeenCalledWith({
            name: "admin.performance.cycles",
        });
    });
});
