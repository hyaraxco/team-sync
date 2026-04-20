import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const {
  routeState,
  routerPushMock,
  performanceReviewStoreMock,
  performanceReviewRefs,
  authRefs,
  routerLinkStub,
} = vi.hoisted(() => ({
  routeState: {
    name: "admin.performance.review-cycles",
  },
  routerPushMock: vi.fn(),
  performanceReviewStoreMock: {
    fetchCycles: vi.fn(),
  },
  performanceReviewRefs: {
    cycles: {
      __v_isRef: true,
      value: [],
    },
    cyclesLoading: {
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
        name: "HR Admin",
        email: "hr@example.com",
        roles: ["HR"],
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

import ReviewCycleList from "@/views/admin/performance/ReviewCycleList.vue";

const factory = () =>
  mount(ReviewCycleList, {
    global: {
      stubs: {
        RouterLink: routerLinkStub,
      },
    },
  });

describe("ReviewCycleList smoke", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    performanceReviewRefs.cycles.value = [];
    performanceReviewRefs.cyclesLoading.value = false;
    performanceReviewRefs.error.value = null;
  });

  it("renders without crashing", () => {
    const wrapper = factory();
    expect(wrapper.exists()).toBe(true);
  });

  it("calls fetchCycles on mount", () => {
    factory();
    expect(performanceReviewStoreMock.fetchCycles).toHaveBeenCalled();
  });

  it("displays loading state", () => {
    performanceReviewRefs.loading.value = true;
    const wrapper = factory();
    // View uses a spinner, not text
    expect(wrapper.find(".animate-spin").exists()).toBe(true);
  });

  it("displays empty state when no cycles", () => {
    performanceReviewRefs.cycles.value = [];
    const wrapper = factory();
    expect(wrapper.text()).toMatch(/no.*cycles/i);
  });

  it("displays review cycles when available", () => {
    performanceReviewRefs.cycles.value = [
      {
        id: 1,
        name: "Q1 2026 Performance Review",
        cycle_type: "quarterly",
        status: "active",
        start_date: "2026-01-01",
        end_date: "2026-03-31",
        review_period_start: "2026-01-01",
        review_period_end: "2026-03-31",
      },
      {
        id: 2,
        name: "Annual Review 2026",
        cycle_type: "annual",
        status: "draft",
        start_date: "2026-01-01",
        end_date: "2026-12-31",
        review_period_start: "2026-01-01",
        review_period_end: "2026-12-31",
      },
    ];
    const wrapper = factory();
    expect(wrapper.text()).toContain("Q1 2026 Performance Review");
    expect(wrapper.text()).toContain("Annual Review 2026");
  });

  it("displays cycle status badges", () => {
    performanceReviewRefs.cycles.value = [
      {
        id: 1,
        name: "Test Cycle",
        cycle_type: "quarterly",
        status: "active",
        start_date: "2026-01-01",
        end_date: "2026-12-31",
        review_period_start: "2026-01-01",
        review_period_end: "2026-12-31",
      },
    ];
    const wrapper = factory();
    expect(wrapper.text()).toContain("active");
  });
});
