import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const { reviewStoreMock, reviewStoreRefs, routerPushMock, routeState } = vi.hoisted(() => ({
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
    createRouter: vi.fn(() => ({ push: vi.fn(), beforeEach: vi.fn() })),
    createWebHistory: vi.fn(),
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

    it("renders TOPSIS headers C6 and C7 when ranking is available", async () => {
        reviewStoreRefs.currentCycle.value.status = "completed";
        reviewStoreRefs.topsisResult.value = {
            weights: {
                avg_manager_rating: 0.3,
                final_rating: 0.3,
                avg_goal_completion: 0.2,
                goal_completion_ratio: 0.05,
                positive_feedback_count: 0.05,
                attendance_quality: 0.05,
                task_completion_quality: 0.05,
            },
            ideal_positive: {
                avg_manager_rating: 1,
                final_rating: 1,
                avg_goal_completion: 1,
                goal_completion_ratio: 1,
                positive_feedback_count: 1,
                attendance_quality: 1,
                task_completion_quality: 1,
            },
            ideal_negative: {
                avg_manager_rating: 0,
                final_rating: 0,
                avg_goal_completion: 0,
                goal_completion_ratio: 0,
                positive_feedback_count: 0,
                attendance_quality: 0,
                task_completion_quality: 0,
            },
            ranking: [
                {
                    staff_member_id: "1",
                    employee_name: "A",
                    rank: 1,
                    department: "Engineering",
                    raw_scores: {
                        avg_manager_rating: 4,
                        final_rating: 4,
                        avg_goal_completion: 80,
                        goal_completion_ratio: 0.8,
                        positive_feedback_count: 5,
                        attendance_quality: 90,
                        task_completion_quality: 85,
                    },
                    normalized_scores: {
                        avg_manager_rating: 0.5,
                        final_rating: 0.5,
                        avg_goal_completion: 0.5,
                        goal_completion_ratio: 0.5,
                        positive_feedback_count: 0.5,
                        attendance_quality: 0.5,
                        task_completion_quality: 0.5,
                    },
                    weighted_scores: {
                        avg_manager_rating: 0.175,
                        final_rating: 0.15,
                        avg_goal_completion: 0.1,
                        goal_completion_ratio: 0.025,
                        positive_feedback_count: 0.025,
                        attendance_quality: 0.015,
                        task_completion_quality: 0.01,
                    },
                    distance_positive: 0,
                    distance_negative: 1,
                    closeness_coefficient: 1,
                    label: "Outstanding",
                },
            ],
        };

        const wrapper = factory();
        await flushAsync();

        expect(wrapper.text()).toContain("C6");
        expect(wrapper.text()).toContain("C7");
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
