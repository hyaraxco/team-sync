import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, ref } from "vue";

// 1. Action mocks
const fetchApprovalPolicies = vi.fn().mockResolvedValue([]);
const createApprovalPolicy = vi.fn().mockResolvedValue({});
const updateApprovalPolicy = vi.fn().mockResolvedValue({});
const deleteApprovalPolicy = vi.fn().mockResolvedValue(undefined);
const push = vi.fn();

// 2. Mocks
vi.mock("@/stores/payroll", () => ({
    usePayrollStore: () => ({
        fetchApprovalPolicies,
        createApprovalPolicy,
        updateApprovalPolicy,
        deleteApprovalPolicy,
        error: null,
    }),
}));

const toastSuccess = vi.fn();
const toastError = vi.fn();

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: toastSuccess,
        error: toastError,
    }),
}));

vi.mock("@/utils/formatUtils", () => ({
    formatRupiah: (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`,
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();

    return {
        ...actual,
        storeToRefs: () => ({}),
    };
});

vi.mock("vue-router", () => ({
    useRouter: () => ({
        push,
    }),
    useRoute: () => ({
        params: {},
        query: {},
        name: "admin.payroll.approval-matrix",
    }),
    createRouter: vi.fn(() => ({
        push,
    })),
    createWebHistory: vi.fn(),
}));

// 3. Import view AFTER mocks
import PayrollApprovalMatrix from "@/views/admin/payroll/PayrollApprovalMatrix.vue";

// 4. Factory
const factory = () =>
    mount(PayrollApprovalMatrix, {
        global: {
            stubs: {
                RouterLink: {
                    props: ["to"],
                    template: "<a><slot /></a>",
                },
                ModalWrapper: {
                    props: ["show", "title", "maxWidth"],
                    template: '<div v-if="show" class="modal-stub"><p>{{ title }}</p><slot /><slot name="footer" /></div>',
                },
                EmptyState: {
                    props: ["icon", "title", "subtitle"],
                    template: '<div class="empty-state-stub">{{ title }}</div>',
                },
            },
        },
    });

describe("PayrollApprovalMatrix smoke", () => {
    beforeEach(() => {
        fetchApprovalPolicies.mockClear();
        createApprovalPolicy.mockClear();
        updateApprovalPolicy.mockClear();
        deleteApprovalPolicy.mockClear();
        toastSuccess.mockClear();
        toastError.mockClear();
        push.mockClear();
    });

    it("renders the page title and add button", async () => {
        fetchApprovalPolicies.mockResolvedValue([]);
        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(wrapper.text()).toContain("Payroll Approval Matrix");
        expect(wrapper.text()).toContain("Add Policy");
        expect(wrapper.text()).toContain(
            "Configure threshold-based approval steps for payroll batches before payment",
        );
    });

    it("fetches approval policies on mount", async () => {
        fetchApprovalPolicies.mockResolvedValue([]);
        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(fetchApprovalPolicies).toHaveBeenCalledTimes(1);
    });

    it("shows empty state when no policies exist", async () => {
        fetchApprovalPolicies.mockResolvedValue([]);
        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(wrapper.text()).toContain("No approval policies configured");
    });

    it("renders policy table when policies are loaded", async () => {
        fetchApprovalPolicies.mockResolvedValue([
            {
                id: 1,
                name: "Finance Manager Approval",
                min_amount: 50000000,
                max_amount: 200000000,
                required_role: "finance-manager",
                approval_order: 1,
                is_active: true,
            },
            {
                id: 2,
                name: "Director Approval",
                min_amount: 200000000,
                max_amount: null,
                required_role: "director",
                approval_order: 2,
                is_active: false,
            },
        ]);
        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(wrapper.find("table").exists()).toBe(true);
        expect(wrapper.text()).toContain("Finance Manager Approval");
        expect(wrapper.text()).toContain("Director Approval");
        expect(wrapper.text()).toContain("finance-manager");
        expect(wrapper.text()).toContain("director");
        expect(wrapper.text()).toContain("Active");
        expect(wrapper.text()).toContain("Inactive");
        expect(wrapper.findAll("tr").length).toBeGreaterThanOrEqual(3); // header + 2 rows
    });
});
