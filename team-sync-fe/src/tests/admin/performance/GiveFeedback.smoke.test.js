import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
    performanceFeedbackStoreMock,
    staffMemberStoreMock,
    performanceGoalStoreMock,
    performanceFeedbackRefs,
    staffMemberRefs,
    performanceGoalRefs,
    authStoreMock,
    toastSuccessMock,
    toastErrorMock,
} = vi.hoisted(() => ({
    performanceFeedbackStoreMock: {
        createFeedback: vi.fn(),
        error: null,
    },
    staffMemberStoreMock: {
        fetchStaffMembers: vi.fn(),
    },
    performanceGoalStoreMock: {
        fetchMyGoals: vi.fn(),
    },
    performanceFeedbackRefs: {
        feedbackLoading: {
            __v_isRef: true,
            value: false,
        },
    },
    staffMemberRefs: {
        staffMembers: {
            __v_isRef: true,
            value: [],
        },
        loading: {
            __v_isRef: true,
            value: false,
        },
    },
    performanceGoalRefs: {
        myGoals: {
            __v_isRef: true,
            value: [],
        },
    },
    authStoreMock: {
        user: {
            id: 1,
            name: "HR Admin",
        },
    },
    toastSuccessMock: vi.fn(),
    toastErrorMock: vi.fn(),
}));

vi.mock("@/stores/performanceFeedback", () => ({
    usePerformanceFeedbackStore: () => performanceFeedbackStoreMock,
}));

vi.mock("@/stores/staffMember", () => ({
    useStaffMemberStore: () => staffMemberStoreMock,
}));

vi.mock("@/stores/performanceGoal", () => ({
    usePerformanceGoalStore: () => performanceGoalStoreMock,
}));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => authStoreMock,
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: toastSuccessMock,
        error: toastErrorMock,
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
            if (store === staffMemberStoreMock) {
                return staffMemberRefs;
            }
            if (store === performanceGoalStoreMock) {
                return performanceGoalRefs;
            }
            return {};
        },
    };
});

import GiveFeedback from "@/views/admin/performance/GiveFeedback.vue";

const factory = () => mount(GiveFeedback);

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

describe("GiveFeedback smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        performanceFeedbackRefs.feedbackLoading.value = false;
        performanceFeedbackStoreMock.error = null;

        staffMemberRefs.loading.value = false;
        staffMemberRefs.staffMembers.value = [
            {
                id: 10,
                full_name: "Jane Employee",
            },
        ];

        performanceGoalRefs.myGoals.value = [
            {
                id: 99,
                title: "Improve communication",
            },
        ];

        performanceFeedbackStoreMock.createFeedback.mockResolvedValue({
            id: 111,
        });
        staffMemberStoreMock.fetchStaffMembers.mockResolvedValue(undefined);
        performanceGoalStoreMock.fetchMyGoals.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchStaffMembers on mount", async () => {
        factory();
        await flushAsync();
        expect(staffMemberStoreMock.fetchStaffMembers).toHaveBeenCalled();
    });

    it("displays form fields", () => {
        const wrapper = factory();
        expect(wrapper.find('select[name="staff_member_id"]').exists()).toBe(true);
        expect(wrapper.find('select[name="feedback_type"]').exists()).toBe(true);
        expect(wrapper.find('textarea[name="content"]').exists()).toBe(true);
    });

    it("submit button disabled when form incomplete", () => {
        const wrapper = factory();
        const submitButton = wrapper.find('button[type="submit"]');
        expect(submitButton.attributes("disabled")).toBeDefined();
    });

    it("submits feedback via store action", async () => {
        const wrapper = factory();
        await wrapper.find('select[name="staff_member_id"]').setValue("10");
        await wrapper.find('select[name="feedback_type"]').setValue("positive");
        await wrapper.find('textarea[name="content"]').setValue("Great teamwork.");
        await wrapper.find('input[name="category"]').setValue("Teamwork");
        await wrapper.find('select[name="linked_goal_id"]').setValue("99");

        await wrapper.find("form").trigger("submit");
        await flushAsync();

        expect(performanceFeedbackStoreMock.createFeedback).toHaveBeenCalledWith({
            staff_member_id: "10",
            feedback_type: "positive",
            category: "Teamwork",
            content: "Great teamwork.",
            is_private: false,
            linked_goal_id: "99",
        });
        expect(toastSuccessMock).toHaveBeenCalled();
    });
});
