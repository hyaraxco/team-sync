import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const fetchMyPayslip = vi.fn();
const downloadPayslip = vi.fn().mockResolvedValue(new Blob(["pdf"], { type: "application/pdf" }));
const routerBack = vi.fn();
const routeParams = { id: "21" };

vi.mock("vue-router", () => ({
    useRoute: () => ({
        params: routeParams,
    }),
    useRouter: () => ({
        back: routerBack,
    }),
}));

vi.mock("@/stores/payroll", () => ({
    usePayrollStore: () => ({
        fetchMyPayslip,
        downloadPayslip,
    }),
}));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => ({
        user: { email: "agung@teamsync.com" },
    }),
}));

import PayslipDetail from "@/views/staff-member/PayslipDetail.vue";

const factory = () => mount(PayslipDetail);

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const findButtonByText = (wrapper, text) => wrapper.findAll("button").find((button) => button.text().includes(text));

describe("PayslipDetail smoke", () => {
    beforeEach(() => {
        fetchMyPayslip.mockClear();
        downloadPayslip.mockClear();
        routerBack.mockClear();

        fetchMyPayslip.mockResolvedValue({
            id: 21,
            period: "2026-04-01",
            employee_name: "Agung Ramadhan",
            employee_email: null,
            employee_code: "EMP001",
            department: "Engineering",
            payment_date: "2026-04-30",
            basic_salary: 9000000,
            allowances: 500000,
            bonus: 250000,
            gross_salary: 9750000,
            tax: 250000,
            insurance: 100000,
            other_deductions: 0,
            total_deductions: 350000,
            adjustments: [
                {
                    id: 77,
                    adjustment_kind: "absence_correction_credit",
                    days_delta: 1,
                    amount_delta: 250000,
                    reason: "Post-lock sick proof approved",
                    status: "applied",
                    source_period_id: 11,
                    target_period_id: 12,
                },
            ],
            adjustment_total_amount: 250000,
            net_salary: 9400000,
            notes: "Payroll tested",
        });

        window.URL.createObjectURL = vi.fn(() => "blob:detail-url");
        window.URL.revokeObjectURL = vi.fn();
        window.print = vi.fn();
        vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => {});
    });

    it("fetches payslip detail on mount and renders salary breakdown", async () => {
        const wrapper = factory();
        await flushAsync();

        expect(fetchMyPayslip).toHaveBeenCalledWith("21");
        expect(wrapper.text()).toContain("Payroll Saya");
        expect(wrapper.text()).toContain("Agung Ramadhan");
        expect(wrapper.text()).toContain("EMP001");
        expect(wrapper.text()).toContain("Engineering");
        expect(wrapper.text()).toContain("NET SALARY");
        expect(wrapper.text()).toContain("agung@teamsync.com");
        expect(wrapper.text()).toContain("Paid");
        expect(wrapper.get('[data-testid="payslip-adjustments"]').text()).toContain("Post-lock sick proof approved");
    });

    it("downloads payslip PDF from detail page", async () => {
        const wrapper = factory();
        await flushAsync();

        const downloadButton = findButtonByText(wrapper, "Download PDF");
        expect(downloadButton).toBeTruthy();
        await downloadButton.trigger("click");
        await flushAsync();

        expect(downloadPayslip).toHaveBeenCalledWith("21");
        expect(window.URL.createObjectURL).toHaveBeenCalled();
        expect(window.URL.revokeObjectURL).toHaveBeenCalledWith("blob:detail-url");
    });

    it("supports back navigation and print actions", async () => {
        const wrapper = factory();
        await flushAsync();

        const backButton = findButtonByText(wrapper, "Back");
        const printButton = findButtonByText(wrapper, "Print");
        expect(backButton).toBeTruthy();
        expect(printButton).toBeTruthy();

        await backButton.trigger("click");
        await printButton.trigger("click");

        expect(routerBack).toHaveBeenCalledTimes(1);
        expect(window.print).toHaveBeenCalledTimes(1);
    });

    it("shows expandable adjustment details", async () => {
        const wrapper = factory();
        await flushAsync();

        const toggle = wrapper.find('[data-testid="payslip-adjustment-toggle-77"]');
        expect(toggle.exists()).toBe(true);
        expect(wrapper.text()).not.toContain("Source Period:");

        await toggle.trigger("click");

        expect(wrapper.text()).toContain("Source Period:");
        expect(wrapper.text()).toContain("#11");
        expect(wrapper.text()).toContain("Target Period:");
        expect(wrapper.text()).toContain("#12");
    });
});
