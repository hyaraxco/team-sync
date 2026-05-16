import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
    routeState,
    routerPushMock,
    staffMemberStoreMock,
    staffMemberStoreRefs,
    optionStoreMock,
    currentStep,
    totalSteps,
    nextStepMock,
    previousStepMock,
} = vi.hoisted(() => ({
    routeState: {
        params: {
            id: "21",
        },
    },
    routerPushMock: vi.fn(),
    staffMemberStoreMock: {
        fetchStaffMember: vi.fn(),
        updateStaffMember: vi.fn(),
        error: null,
    },
    staffMemberStoreRefs: {
        loading: {
            __v_isRef: true,
            value: false,
        },
        error: {
            __v_isRef: true,
            value: null,
        },
    },
    optionStoreMock: {
        fetchTeams: vi.fn(),
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

vi.mock("@/stores/option", () => ({
    useOptionStore: () => optionStoreMock,
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: vi.fn(),
        error: vi.fn(),
    }),
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
            if (store === staffMemberStoreMock) {
                return staffMemberStoreRefs;
            }
            return {};
        },
    };
});

import StaffMemberEdit from "@/views/admin/staff-member/StaffMemberEdit.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const staffMemberPayload = {
    user: {
        name: "Rina",
        email: "rina@example.com",
        profile_photo: null,
        roles: ["staff"],
    },
    identity_number: "1234567890123456",
    phone: "081122334455",
    date_of_birth: "2000-01-01",
    last_education: "s1",
    seniority_level: "mid",
    religion: "islam",
    marital_status: "single",
    blood_type: "A",
    place_of_birth: "Jakarta",
    gender: "female",
    address: "Address",
    city: "Jakarta",
    postal_code: "11530",
    npwp: "",
    bpjs_ketenagakerjaan: "",
    bpjs_kesehatan: "",
    ptkp_status: "",
    emergency_contacts: [],
    job_information: {
        job_title: "Engineer",
        team: { id: 1 },
        status: "active",
        employment_type: "full_time",
        work_location: "office",
        start_date: "2024-01-01",
        monthly_salary: 10000000,
    },
    bank_information: {
        bank_name: "bca",
        account_number: "1234567890",
        account_holder_name: "Rina",
    },
};

const factory = () =>
    mount(StaffMemberEdit, {
        global: {
            provide: {
                currentStep,
                totalSteps,
                nextStep: nextStepMock,
                previousStep: previousStepMock,
            },
            stubs: {
                Step1PersonalInfo: { template: '<div class="step1-stub"></div>' },
                Step2JobInfo: { template: '<div class="step2-stub"></div>' },
                Step3EmergencyContact: { template: '<div class="step3-stub"></div>' },
                Step4Preview: { template: '<div class="step4-stub"></div>' },
                ErrorModal: { template: '<div class="error-modal-stub"></div>' },
            },
        },
    });

describe("StaffMemberEdit smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        routeState.params.id = "21";
        currentStep.value = 1;
        totalSteps.value = 4;
        staffMemberStoreMock.error = null;
        staffMemberStoreRefs.loading.value = false;
        staffMemberStoreRefs.error.value = null;
        staffMemberStoreMock.fetchStaffMember.mockResolvedValue(staffMemberPayload);
        staffMemberStoreMock.updateStaffMember.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetch on mount", async () => {
        factory();
        await flushAsync();

        expect(staffMemberStoreMock.fetchStaffMember).toHaveBeenCalledWith("21");
    });

    it("navigates back to list when cancel clicked on first step", async () => {
        const wrapper = factory();

        const cancelButton = wrapper.findAll("button").find((button) => {
            return button.text().trim() === "Cancel";
        });
        await cancelButton.trigger("click");

        expect(routerPushMock).toHaveBeenCalledWith({ name: "admin.staffMembers" });
    });
});
