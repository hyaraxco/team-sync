import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const { routeState, routerBackMock, reviewStoreMock, reviewStoreRefs, authStoreMock } = vi.hoisted(() => ({
    routeState: {
        params: {
            id: "55",
        },
    },
    routerBackMock: vi.fn(),
    reviewStoreMock: {
        resetState: vi.fn(),
        fetchReviewById: vi.fn(),
        fetchActiveSections: vi.fn(),
        fetchCalibrationContext: vi.fn(),
        fetchValidateReadiness: vi.fn(),
        submitSelfAssessment: vi.fn(),
        submitManagerAssessment: vi.fn(),
        calibrateReview: vi.fn(),
    },
    reviewStoreRefs: {
        currentReview: {
            __v_isRef: true,
            value: null,
        },
        sections: {
            __v_isRef: true,
            value: [],
        },
        reviewsLoading: {
            __v_isRef: true,
            value: false,
        },
        sectionsLoading: {
            __v_isRef: true,
            value: false,
        },
        error: {
            __v_isRef: true,
            value: null,
        },
        success: {
            __v_isRef: true,
            value: null,
        },
        calibrationContext: {
            __v_isRef: true,
            value: null,
        },
        calibrationContextLoading: {
            __v_isRef: true,
            value: false,
        },
        readinessResult: {
            __v_isRef: true,
            value: null,
        },
        readinessLoading: {
            __v_isRef: true,
            value: false,
        },
    },
    authStoreMock: {
        user: {
            roles: [{ name: "staff" }],
            employee_profile: { id: 10 },
        },
    },
}));

vi.mock("@/stores/performanceReview", () => ({
    usePerformanceReviewStore: () => reviewStoreMock,
}));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => authStoreMock,
}));

vi.mock("vue-router", () => ({
    useRoute: () => routeState,
    useRouter: () => ({
        back: routerBackMock,
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

import ReviewDetail from "@/views/admin/performance/ReviewDetail.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(ReviewDetail, {
        global: {
            stubs: {
                MainCard: {
                    template: '<div class="main-card-stub"><slot /></div>',
                },
                EmptyState: {
                    template: '<div class="empty-state-stub"></div>',
                },
                Alert: {
                    template: '<div class="alert-stub"></div>',
                },
                ConfirmationModal: {
                    template: '<div class="confirmation-modal-stub"></div>',
                },
            },
        },
    });

describe("ReviewDetail smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        routeState.params.id = "55";

        reviewStoreRefs.reviewsLoading.value = false;
        reviewStoreRefs.sectionsLoading.value = false;
        reviewStoreRefs.error.value = null;
        reviewStoreRefs.success.value = null;
        reviewStoreRefs.calibrationContext.value = null;
        reviewStoreRefs.calibrationContextLoading.value = false;
        reviewStoreRefs.readinessResult.value = null;
        reviewStoreRefs.readinessLoading.value = false;
        reviewStoreRefs.sections.value = [
            {
                id: 1,
                name: "Communication",
                weight: 50,
            },
            {
                id: 2,
                name: "Delivery",
                weight: 50,
            },
        ];
        reviewStoreRefs.currentReview.value = {
            id: 55,
            status: "pending_self",
            staff_member_id: 10,
            reviewer_id: 30,
            cycle: {
                name: "Q2 2026",
            },
            staff_member: {
                full_name: "Nadia Employee",
                email: "nadia@example.com",
            },
            reviewer: {
                full_name: "Ardi Manager",
                email: "ardi@example.com",
            },
            responses: [],
        };

        reviewStoreMock.fetchReviewById.mockResolvedValue(reviewStoreRefs.currentReview.value);
        reviewStoreMock.fetchActiveSections.mockResolvedValue(undefined);
        reviewStoreMock.fetchCalibrationContext.mockResolvedValue(undefined);
        reviewStoreMock.fetchValidateReadiness.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchReviewById and fetchActiveSections on mount", async () => {
        factory();
        await flushAsync();

        expect(reviewStoreMock.resetState).toHaveBeenCalled();
        expect(reviewStoreMock.fetchReviewById).toHaveBeenCalledWith("55");
        expect(reviewStoreMock.fetchActiveSections).toHaveBeenCalled();
    });

    it("goes back when back button is clicked", async () => {
        const wrapper = factory();
        await flushAsync();

        const firstButton = wrapper.findAll("button")[0];
        await firstButton.trigger("click");

        expect(routerBackMock).toHaveBeenCalled();
    });

    describe("canCalibrate guard", () => {
        it("does not show calibration action when HR user is the reviewee", async () => {
            // HR user with employee_profile.id = 10, same as review's staff_member_id
            authStoreMock.user = {
                roles: [{ name: "hr" }],
                employee_profile: { id: 10 },
            };
            reviewStoreRefs.currentReview.value = {
                id: 55,
                status: "pending_calibration",
                staff_member_id: 10,
                reviewer_id: 30,
                cycle: { name: "Q2 2026", calibration_deadline: "2026-06-30" },
                staff_member: { full_name: "Nadia Employee", email: "nadia@example.com" },
                reviewer: { full_name: "Ardi Manager", email: "ardi@example.com" },
                responses: [],
            };

            const wrapper = factory();
            await flushAsync();

            // Should NOT show calibration action banner (HR is the reviewee)
            expect(wrapper.text()).not.toContain("Action Required: Calibration");
        });

        it("shows calibration action when HR user is NOT the reviewee", async () => {
            // HR user with employee_profile.id = 99, different from review's staff_member_id = 10
            authStoreMock.user = {
                roles: [{ name: "hr" }],
                employee_profile: { id: 99 },
            };
            reviewStoreRefs.currentReview.value = {
                id: 55,
                status: "pending_calibration",
                staff_member_id: 10,
                reviewer_id: 30,
                cycle: { name: "Q2 2026", calibration_deadline: "2026-06-30" },
                staff_member: { full_name: "Nadia Employee", email: "nadia@example.com" },
                reviewer: { full_name: "Ardi Manager", email: "ardi@example.com" },
                responses: [],
            };

            const wrapper = factory();
            await flushAsync();

            // Should show calibration action banner
            expect(wrapper.text()).toContain("Action Required: Calibration");
        });
    });
});
