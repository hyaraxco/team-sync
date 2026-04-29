import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
    reviewStoreMock,
    reviewStoreRefs,
    routerPushMock,
} = vi.hoisted(() => ({
    reviewStoreMock: {
        fetchTeamReviews: vi.fn(),
    },
    reviewStoreRefs: {
        teamReviews: {
            __v_isRef: true,
            value: [],
        },
        reviewsLoading: {
            __v_isRef: true,
            value: false,
        },
        pagination: {
            __v_isRef: true,
            value: {
                current_page: 1,
                last_page: 1,
                total: 0,
            },
        },
    },
    routerPushMock: vi.fn(),
}));

vi.mock("@/stores/performanceReview", () => ({
    usePerformanceReviewStore: () => reviewStoreMock,
}));

vi.mock("vue-router", () => ({
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

import TeamReviews from "@/views/admin/performance/TeamReviews.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(TeamReviews, {
        global: {
            stubs: {
                MainCard: { template: '<div class="main-card-stub"><slot /></div>' },
                EmptyState: { template: '<div class="empty-state-stub"><slot /></div>' },
                StatusBadge: { template: '<div class="status-badge-stub"></div>' },
            },
        },
    });

describe("TeamReviews smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        reviewStoreRefs.reviewsLoading.value = false;
        reviewStoreRefs.teamReviews.value = [
            {
                id: 21,
                cycle_id: 5,
                status: "pending_manager",
                final_rating: null,
                self_assessment_submitted_at: null,
                cycle: {
                    id: 5,
                    name: "Q1 2026",
                },
                staff_member: {
                    full_name: "Rina Aulia",
                    email: "rina@example.com",
                },
            },
        ];
        reviewStoreMock.fetchTeamReviews.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchTeamReviews on mount", async () => {
        factory();
        await flushAsync();

        expect(reviewStoreMock.fetchTeamReviews).toHaveBeenCalled();
    });

    it("navigates to review detail when row clicked", async () => {
        const wrapper = factory();
        await flushAsync();

        const row = wrapper.find("tbody tr");
        await row.trigger("click");

        expect(routerPushMock).toHaveBeenCalledWith({
            name: "admin.performance.review.detail",
            params: { id: 21 },
        });
    });
});
