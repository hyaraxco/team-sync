import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
    routeState,
    routerPushMock,
    toastSuccessMock,
    toastErrorMock,
    staffMemberStoreMock,
    staffMemberStoreRefs,
} = vi.hoisted(() => ({
    routeState: {
        params: {
            id: "12",
        },
    },
    routerPushMock: vi.fn(),
    toastSuccessMock: vi.fn(),
    toastErrorMock: vi.fn(),
    staffMemberStoreMock: {
        fetchStaffMember: vi.fn(),
        fetchPerformanceStatistics: vi.fn(),
        deleteStaffMember: vi.fn(),
        error: null,
    },
    staffMemberStoreRefs: {
        loading: {
            __v_isRef: true,
            value: false,
        },
        performanceStatistics: {
            __v_isRef: true,
            value: {
                tasks_completed: 8,
                attendance_rate: 92,
                projects_count: 3,
                performance_score: 88,
            },
        },
        success: {
            __v_isRef: true,
            value: null,
        },
    },
}));

vi.mock("@/stores/staffMember", () => ({
    useStaffMemberStore: () => staffMemberStoreMock,
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: toastSuccessMock,
        error: toastErrorMock,
    }),
}));

vi.mock("@/helpers/permissionHelper", () => ({
    can: () => true,
}));

vi.mock("vue-router", () => ({
    useRoute: () => routeState,
    useRouter: () => ({
        push: routerPushMock,
    }),
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

import StaffMemberDetail from "@/views/admin/staff-member/StaffMemberDetail.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const staffMemberPayload = {
    id: 12,
    code: "EMP-0012",
    user: {
        name: "Ayu Pratama",
        email: "ayu@example.com",
        profile_photo: "",
    },
    job_information: {
        job_title: "Backend Engineer",
        status: "active",
        work_location: "jakarta",
        start_date: "2025-01-15",
        employment_type: "full_time",
        monthly_salary: 12000000,
        review_template: { name: "Default Template" },
        team: {
            name: "Platform",
            members_count: 6,
            status: "active",
        },
    },
    phone: "08123456789",
    identity_number: "3175010101010001",
    date_of_birth: "1998-01-10",
    place_of_birth: "Bandung",
    gender: "female",
    religion: "islam",
    marital_status: "single",
    blood_type: "O",
    emergency_contacts: [
        {
            full_name: "Budi Pratama",
            relationship: "brother",
            phone: "08129876543",
            email: "budi@example.com",
        },
    ],
    address: "Jl. Kebon Jeruk 10",
    city: "Jakarta",
    postal_code: "11530",
    npwp: "12.345.678.9-012.345",
    bpjs_ketenagakerjaan: "KPJ001122",
    bpjs_kesehatan: "BJS001122",
    ptkp_status: "TK/0",
    bank_information: {
        bank_name: "bca",
        account_number: "1234567890",
        account_holder_name: "Ayu Pratama",
    },
};

const factory = () =>
    mount(StaffMemberDetail, {
        global: {
            stubs: {
                StatusBadge: { template: '<div class="status-badge-stub"></div>' },
                ConfirmationModal: { template: '<div class="confirm-modal-stub"></div>' },
                AnimatedValue: {
                    props: ["value"],
                    template: '<span class="animated-value-stub">{{ value }}</span>',
                },
            },
        },
    });

describe("StaffMemberDetail smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        routeState.params.id = "12";
        staffMemberStoreMock.error = null;
        staffMemberStoreRefs.loading.value = false;
        staffMemberStoreRefs.success.value = null;
        staffMemberStoreRefs.performanceStatistics.value = {
            tasks_completed: 8,
            attendance_rate: 92,
            projects_count: 3,
            performance_score: 88,
        };
        staffMemberStoreMock.fetchStaffMember.mockResolvedValue(staffMemberPayload);
        staffMemberStoreMock.fetchPerformanceStatistics.mockResolvedValue(undefined);
        staffMemberStoreMock.deleteStaffMember.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetch on mount", async () => {
        factory();
        await flushAsync();

        expect(staffMemberStoreMock.fetchStaffMember).toHaveBeenCalledWith("12");
        expect(staffMemberStoreMock.fetchPerformanceStatistics).toHaveBeenCalledWith("12");
    });

    it("navigates to edit page when edit profile clicked", async () => {
        const wrapper = factory();
        await flushAsync();

        const editButton = wrapper.findAll("button").find((button) => {
            return button.text().includes("Edit Profile");
        });
        await editButton.trigger("click");

        expect(routerPushMock).toHaveBeenCalledWith({
            name: "admin.staffMembers.edit",
            params: { id: "12" },
        });
    });
});
