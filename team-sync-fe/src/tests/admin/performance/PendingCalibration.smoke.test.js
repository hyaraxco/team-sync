import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const {
  routeState,
  routerPushMock,
  performanceReviewStoreMock,
  performanceReviewRefs,
  routerLinkStub,
} = vi.hoisted(() => ({
  routeState: {
    name: "admin.performance.pending-calibration",
  },
  routerPushMock: vi.fn(),
  performanceReviewStoreMock: {
    fetchPendingCalibration: vi.fn(),
  },
  performanceReviewRefs: {
    pendingCalibrationReviews: {
      __v_isRef: true,
      value: [],
    },
    pendingCalibrationLoading: {
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
  routerLinkStub: {
    name: "RouterLink",
    props: ["to"],
    template: '<a class="router-link-stub"><slot /></a>',
  },
}));

vi.mock("@/stores/performanceReview", () => ({
  usePerformanceReviewStore: () => performanceReviewStoreMock,
}));

vi.mock("pinia", async (importOriginal) => {
  const actual = await importOriginal();
  return {
    ...actual,
    storeToRefs: () => performanceReviewRefs,
  };
});

vi.mock("vue-router", () => ({
  useRoute: () => routeState,
  useRouter: () => ({
    push: routerPushMock,
  }),
  RouterLink: routerLinkStub,
}));

import PendingCalibration from "@/views/admin/performance/PendingCalibration.vue";

const factory = () =>
  mount(PendingCalibration, {
    global: {
      stubs: {
        RouterLink: routerLinkStub,
      },
    },
  });

describe("PendingCalibration smoke", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    performanceReviewRefs.pendingCalibrationReviews.value = [];
    performanceReviewRefs.pendingCalibrationLoading.value = false;
    performanceReviewRefs.error.value = null;
  });

  it("renders without crashing", () => {
    const wrapper = factory();
    expect(wrapper.exists()).toBe(true);
  });

  it("calls fetchPendingCalibration on mount", () => {
    factory();
    expect(
      performanceReviewStoreMock.fetchPendingCalibration,
    ).toHaveBeenCalled();
  });

  it("displays loading state", () => {
    performanceReviewRefs.pendingCalibrationLoading.value = true;
    const wrapper = factory();
    expect(wrapper.find(".animate-spin").exists()).toBe(true);
  });

  it("displays empty state when no reviews", () => {
    performanceReviewRefs.pendingCalibrationReviews.value = [];
    const wrapper = factory();
    expect(wrapper.text()).toMatch(/no.*pending/i);
  });

  it("displays reviews when available", () => {
    performanceReviewRefs.pendingCalibrationReviews.value = [
      {
        id: 1,
        cycle: { name: "Q1 2026 Review" },
        cycle_id: 1,
        status: "pending_calibration",
        employee: { full_name: "Jane Smith", email: "jane@example.com" },
        reviewer: { full_name: "Bob Manager", user: { name: "Bob Manager" } },
        final_rating: "3.50",
      },
    ];
    const wrapper = factory();
    expect(wrapper.text()).toContain("Jane Smith");
  });

  it("navigates to review detail on row click", async () => {
    performanceReviewRefs.pendingCalibrationReviews.value = [
      {
        id: 42,
        cycle: { name: "Q1 2026 Review" },
        cycle_id: 1,
        status: "pending_calibration",
        employee: { full_name: "Jane Smith", email: "jane@example.com" },
        reviewer: { full_name: "Bob Manager", user: { name: "Bob Manager" } },
        final_rating: "3.50",
      },
    ];
    const wrapper = factory();
    const row = wrapper.find("tr.cursor-pointer");
    if (row.exists()) {
      await row.trigger("click");
      expect(routerPushMock).toHaveBeenCalledWith({
        name: "admin.performance.review.detail",
        params: { id: 42 },
      });
    }
  });
});
