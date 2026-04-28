import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const {
  routeState,
  routerPushMock,
  performanceFeedbackStoreMock,
  performanceFeedbackRefs,
  authRefs,
  routerLinkStub,
  toastMocks,
} = vi.hoisted(() => ({
  routeState: {
    name: "admin.performance.feedback-received",
  },
  routerPushMock: vi.fn(),
  performanceFeedbackStoreMock: {
    fetchReceivedFeedback: vi.fn(),
  },
  performanceFeedbackRefs: {
    receivedFeedback: {
      __v_isRef: true,
      value: [],
    },
    feedbackLoading: {
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
        name: "John Doe",
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
  toastMocks: {
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
  },
}));

vi.mock("@/stores/performanceFeedback", () => ({
  usePerformanceFeedbackStore: () => performanceFeedbackStoreMock,
}));

vi.mock("@/composables/useToast", () => ({
  useToast: () => toastMocks,
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
      if (store === performanceFeedbackStoreMock) {
        return performanceFeedbackRefs;
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

import FeedbackReceived from "@/views/admin/performance/FeedbackReceived.vue";

const factory = () =>
  mount(FeedbackReceived, {
    global: {
      stubs: {
        RouterLink: routerLinkStub,
      },
    },
  });

describe("FeedbackReceived smoke", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    performanceFeedbackRefs.receivedFeedback.value = [];
    performanceFeedbackRefs.feedbackLoading.value = false;
    performanceFeedbackRefs.error.value = null;
  });

  it("renders without crashing", () => {
    const wrapper = factory();
    expect(wrapper.exists()).toBe(true);
  });

  it("calls fetchReceivedFeedback on mount", () => {
    factory();
    expect(
      performanceFeedbackStoreMock.fetchReceivedFeedback,
    ).toHaveBeenCalled();
  });

  it("displays loading state", () => {
    performanceFeedbackRefs.feedbackLoading.value = true;
    const wrapper = factory();
    expect(wrapper.find(".animate-spin").exists()).toBe(true);
  });

  it("displays empty state when no feedback", () => {
    performanceFeedbackRefs.receivedFeedback.value = [];
    const wrapper = factory();
    expect(wrapper.text()).toMatch(/no.*feedback/i);
  });

  it("displays feedback when available", () => {
    performanceFeedbackRefs.receivedFeedback.value = [
      {
        id: 1,
        giver: { full_name: "Jane Manager" },
        feedback_type: "positive",
        category: "teamwork",
        content: "Great work on the project!",
        created_at: "2026-04-15",
        is_private: false,
      },
    ];
    const wrapper = factory();
    expect(wrapper.text()).toContain("Jane Manager");
    expect(wrapper.text()).toContain("Great work on the project!");
  });
});
