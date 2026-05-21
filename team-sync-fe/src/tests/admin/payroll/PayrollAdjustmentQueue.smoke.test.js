import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, reactive } from "vue";

const toastSuccess = vi.fn();
const toastError = vi.fn();

const payrollStore = reactive({
    payrollAdjustments: [],
    meta: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    },
    loading: false,
    error: null,
    fetchPayrollAdjustments: vi.fn(),
    approvePayrollAdjustment: vi.fn(),
});

vi.mock("@/stores/payroll", () => ({
    usePayrollStore: () => payrollStore,
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: toastSuccess,
        error: toastError,
    }),
}));

vi.mock("@/components/common/EmptyState.vue", () => ({
    default: {
        name: "EmptyState",
        props: ["title", "subtitle"],
        template: '<div data-testid="empty-state"><h2>{{ title }}</h2><p>{{ subtitle }}</p></div>',
    },
}));

import PayrollAdjustmentQueue from "@/views/admin/payroll/PayrollAdjustmentQueue.vue";

const adjustments = [
    {
        id: 10,
        staff_member_id: 3,
        staff_member: {
            employee_code: "EMP003",
            user: {
                name: "Dewi Finance",
            },
        },
        source_period: {
            id: 1,
            start_date: "2026-04-01",
            end_date: "2026-04-30",
        },
        target_period: {
            id: 2,
            start_date: "2026-05-01",
            end_date: "2026-05-31",
        },
        adjustment_kind: "absence_correction_credit",
        days_delta: "1.00",
        amount_delta: "250000.00",
        reason: "Approved sick leave proof after period lock",
        status: "pending",
    },
    {
        id: 11,
        staff_member_id: 4,
        staff_member: {
            employee_code: "EMP004",
            user: {
                name: "Rafi Payroll",
            },
        },
        source_period: null,
        target_period: null,
        adjustment_kind: "absence_correction_deduction",
        days_delta: "-1.00",
        amount_delta: "-175000.00",
        reason: "Manual correction",
        status: "approved",
    },
];

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () => mount(PayrollAdjustmentQueue);

describe("PayrollAdjustmentQueue smoke", () => {
    beforeEach(() => {
        payrollStore.payrollAdjustments = [...adjustments];
        payrollStore.meta = {
            current_page: 1,
            last_page: 1,
            per_page: 15,
            total: adjustments.length,
        };
        payrollStore.loading = false;
        payrollStore.error = null;
        payrollStore.fetchPayrollAdjustments = vi.fn(async () => ({ data: adjustments }));
        payrollStore.approvePayrollAdjustment = vi.fn(async () => ({ ...adjustments[0], status: "approved" }));
        toastSuccess.mockClear();
        toastError.mockClear();
    });

    it("loads and renders adjustment records with approve-only action for pending items", async () => {
        const wrapper = factory();
        await flushAsync();

        expect(payrollStore.fetchPayrollAdjustments).toHaveBeenCalledWith({
            page: 1,
            per_page: 15,
        });
        expect(wrapper.text()).toContain("Antrian Penyesuaian Payroll");
        expect(wrapper.text()).toContain("Dewi Finance");
        expect(wrapper.text()).toContain("Absence Correction Credit");
        expect(wrapper.text()).toContain("Rafi Payroll");
        expect(wrapper.text()).toContain("antrian tertunda");
        expect(wrapper.findAll("button").some((button) => button.text() === "Approve")).toBe(true);
    });

    it("approves pending adjustments through the existing store action", async () => {
        const wrapper = factory();
        await flushAsync();

        const approveButton = wrapper.findAll("button").find((button) => button.text() === "Approve");
        expect(approveButton).toBeTruthy();
        await approveButton.trigger("click");
        await flushAsync();

        expect(payrollStore.approvePayrollAdjustment).toHaveBeenCalledWith(10);
        expect(toastSuccess).toHaveBeenCalledWith(
            "Payroll adjustment approved",
            "The adjustment is now ready to be applied in the target payroll period.",
        );
        expect(payrollStore.fetchPayrollAdjustments).toHaveBeenCalledTimes(2);
    });

    it("filters adjustments by status from the existing index endpoint", async () => {
        const wrapper = factory();
        await flushAsync();

        await wrapper.find("select").setValue("approved");
        await flushAsync();

        expect(payrollStore.fetchPayrollAdjustments).toHaveBeenLastCalledWith({
            page: 1,
            per_page: 15,
            status: "approved",
        });
    });
});
