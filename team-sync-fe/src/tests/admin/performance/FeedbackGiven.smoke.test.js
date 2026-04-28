import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const {
    performanceFeedbackStoreMock,
    performanceFeedbackRefs,
    authRefs,
    routerLinkStub,
} = vi.hoisted(() => ({
    performanceFeedbackStoreMock: {
        fetchGivenFeedback: vi.fn(),
    },
    performanceFeedbackRefs: {
        givenFeedback: {
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

vi.mock("@/stores/performanceFeedback", () => ({
    usePerformanceFeedbackStore: () => performanceFeedbackStoreMock,
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
    useRoute: () => ({ name: "admin.performance.feedback.given" }),
    useRouter: () => ({
        push: vi.fn(),
    }),
    RouterLink: routerLinkStub,
}));

import FeedbackGiven from "@/views/admin/performance/FeedbackGiven.vue";

const factory = () =>
    mount(FeedbackGiven, {
        global: {
            stubs: {
                RouterLink: routerLinkStub,
            },
        },
    });

describe("FeedbackGiven smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        performanceFeedbackRefs.givenFeedback.value = [];
        performanceFeedbackRefs.feedbackLoading.value = false;
        performanceFeedbackRefs.error.value = null;
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchGivenFeedback on mount", () => {
        factory();
        expect(performanceFeedbackStoreMock.fetchGivenFeedback).toHaveBeenCalled();
    });

    it("displays loading state", () => {
        performanceFeedbackRefs.feedbackLoading.value = true;
        const wrapper = factory();
        expect(wrapper.find(".animate-spin").exists()).toBe(true);
    });

    it("displays empty state when no feedback", () => {
        performanceFeedbackRefs.givenFeedback.value = [];
        const wrapper = factory();
        expect(wrapper.text()).toMatch(/no feedback given/i);
    });

    it("displays feedback data when available", () => {
        performanceFeedbackRefs.givenFeedback.value = [
            {
                id: 1,
                receiver: { full_name: "Alex Employee" },
                feedback_type: "constructive",
                category: "Communication",
                content: "Please provide updates earlier.",
                created_at: "2026-04-15",
                is_private: false,
            },
        ];
        const wrapper = factory();
        expect(wrapper.text()).toContain("Alex Employee");
        expect(wrapper.text()).toContain("Please provide updates earlier.");
    });
});
