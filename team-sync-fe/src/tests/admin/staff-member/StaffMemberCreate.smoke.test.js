import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const {
    routeState,
    routerPushMock,
    routerBackMock,
    staffMemberStoreMock,
    staffMemberRefs,
    currentStep,
    totalSteps,
    nextStepMock,
    previousStepMock,
} = vi.hoisted(() => ({
    routeState: {
        name: "admin.staffMembers.create",
        params: {},
        query: {},
    },
    routerPushMock: vi.fn(),
    routerBackMock: vi.fn(),
    staffMemberStoreMock: {
        checkAvailability: vi.fn(),
        createStaffMember: vi.fn(),
    },
    staffMemberRefs: {
        loading: {
            __v_isRef: true,
            value: false,
        },
        error: {
            __v_isRef: true,
            value: null,
        },
    },
    currentStep: {
        __v_isRef: true,
        value: 1,
    },
    totalSteps: {
        __v_isRef: true,
        value: 4,
    },
    nextStepMock: vi.fn(),
    previousStepMock: vi.fn(),
}));

vi.mock("@/stores/staffMember", () => ({
    useStaffMemberStore: () => staffMemberStoreMock,
}));

vi.mock("vue-router", () => ({
    useRoute: () => routeState,
    useRouter: () => ({
        push: routerPushMock,
        back: routerBackMock,
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: (store) => {
            if (store === staffMemberStoreMock) {
                return staffMemberRefs;
            }
            return {};
        },
    };
});

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}));

import StaffMemberCreate from "@/views/admin/staff-member/StaffMemberCreate.vue";

const factory = () =>
    mount(StaffMemberCreate, {
        global: {
            provide: {
                currentStep,
                totalSteps,
                nextStep: nextStepMock,
                previousStep: previousStepMock,
            },
            stubs: {
                Step1PersonalInfo: {
                    template: '<div class="step-1-stub"></div>',
                },
                Step2JobInfo: {
                    template: '<div class="step-2-stub"></div>',
                },
                Step3EmergencyContact: {
                    template: '<div class="step-3-stub"></div>',
                },
                Step4Preview: {
                    template: '<div class="step-4-stub"></div>',
                },
                ErrorModal: {
                    template: '<div class="error-modal-stub"></div>',
                },
            },
        },
    });

describe("StaffMemberCreate smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        currentStep.value = 1;
        totalSteps.value = 4;
        staffMemberRefs.loading.value = false;
        staffMemberRefs.error.value = null;
        staffMemberStoreMock.checkAvailability.mockResolvedValue(undefined);
        staffMemberStoreMock.createStaffMember.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("renders step indicator for current step", () => {
        const wrapper = factory();
        expect(wrapper.text()).toContain("Step 1 of 4");
    });

    it("calls previousStep when cancel button clicked", async () => {
        const wrapper = factory();
        const cancelButton = wrapper.findAll("button").find((button) => {
            return button.text().trim() === "Cancel";
        });

        await cancelButton.trigger("click");
        expect(previousStepMock).toHaveBeenCalled();
    });
});
