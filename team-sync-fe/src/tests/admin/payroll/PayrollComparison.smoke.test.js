import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, ref } from "vue";

// 1. State refs at top
const payrollComparison = ref(null);
const loadingAnalytics = ref(false);
const error = ref(null);

// 2. Action mocks
const fetchPayrollComparison = vi.fn().mockResolvedValue(undefined);
const push = vi.fn();

// 3. Mocks
vi.mock("@/stores/payroll", () => ({
    usePayrollStore: () => ({
        fetchPayrollComparison,
    }),
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: vi.fn(),
        error: vi.fn(),
    }),
}));

vi.mock("@/utils/formatUtils", () => ({
    formatRupiah: (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`,
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();

    return {
        ...actual,
        storeToRefs: () => ({
            payrollComparison,
            loadingAnalytics,
            error,
        }),
    };
});

vi.mock("vue-router", () => ({
    useRouter: () => ({
        push,
    }),
    useRoute: () => ({
        params: {},
        query: {},
        name: "admin.payroll.comparison",
    }),
    createRouter: vi.fn(() => ({
        push,
    })),
    createWebHistory: vi.fn(),
}));

// 4. Import view AFTER mocks
import PayrollComparison from "@/views/admin/payroll/PayrollComparison.vue";

// 5. Factory
const factory = () =>
    mount(PayrollComparison, {
        global: {
            stubs: {
                RouterLink: {
                    props: ["to"],
                    template: "<a><slot /></a>",
                },
                MainCard: {
                    props: ["title"],
                    template: '<div class="main-card-stub">{{ title }}<slot /><template v-if="$slots.footer"><slot name="footer" /></template></div>',
                },
                EmptyState: {
                    props: ["icon", "title", "subtitle"],
                    template: '<div class="empty-state-stub">{{ title }}</div>',
                },
            },
        },
    });

describe("PayrollComparison smoke", () => {
    beforeEach(() => {
        payrollComparison.value = null;
        loadingAnalytics.value = false;
        error.value = null;
        fetchPayrollComparison.mockClear();
        push.mockClear();
    });

    it("renders the page title and month inputs", async () => {
        const wrapper = factory();
        await nextTick();

        expect(wrapper.text()).toContain("Month-over-Month Comparison");
        expect(wrapper.text()).toContain("Compare payroll expenditures between two periods");
        expect(wrapper.find("input[type='month']").exists()).toBe(true);
    });

    it("fetches comparison data on mount", async () => {
        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(fetchPayrollComparison).toHaveBeenCalledTimes(1);
        expect(fetchPayrollComparison).toHaveBeenCalledWith(
            expect.stringMatching(/^\d{4}-\d{2}$/),
            expect.stringMatching(/^\d{4}-\d{2}$/),
        );
    });

    it("shows loading spinner when loadingAnalytics is true", async () => {
        loadingAnalytics.value = true;
        const wrapper = factory();
        await nextTick();

        expect(wrapper.find(".animate-spin").exists()).toBe(true);
        expect(wrapper.text()).toContain("Loading...");
    });

    it("shows error message when error exists", async () => {
        error.value = "Network timeout";
        const wrapper = factory();
        await nextTick();

        expect(wrapper.text()).toContain("Failed to load comparison");
        expect(wrapper.text()).toContain("Network timeout");
    });

    it("renders comparison table when data is loaded", async () => {
        payrollComparison.value = {
            month1: {
                period: "2026-03",
                found: true,
                employee_count: 10,
                gross_salary: 100000000,
                allowances: 10000000,
                deductions: 5000000,
                bpjs_deductions: 2000000,
                bpjs_employer: 4000000,
                tax_amount: 8000000,
                net_salary: 85000000,
            },
            month2: {
                period: "2026-04",
                found: true,
                employee_count: 12,
                gross_salary: 120000000,
                allowances: 12000000,
                deductions: 6000000,
                bpjs_deductions: 2400000,
                bpjs_employer: 4800000,
                tax_amount: 9600000,
                net_salary: 102000000,
            },
            variances: {
                employee_count: { difference: 2, percentage: 20 },
                gross_salary: { difference: 20000000, percentage: 20 },
                allowances: { difference: 2000000, percentage: 20 },
                deductions: { difference: 1000000, percentage: 20 },
                bpjs_deductions: { difference: 400000, percentage: 20 },
                bpjs_employer: { difference: 800000, percentage: 20 },
                tax_amount: { difference: 1600000, percentage: 20 },
                net_salary: { difference: 17000000, percentage: 20 },
            },
        };
        const wrapper = factory();
        await nextTick();

        expect(wrapper.text()).toContain("Employee Count");
        expect(wrapper.text()).toContain("Gross Salary");
        expect(wrapper.text()).toContain("Net Salary");
        expect(wrapper.text()).toContain("2026-03");
        expect(wrapper.text()).toContain("2026-04");
        expect(wrapper.find("table").exists()).toBe(true);
    });
});
