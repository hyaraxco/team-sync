import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, ref } from "vue";

const reopenPayroll = vi.fn().mockResolvedValue(undefined);
const fetchPayroll = vi.fn();
const fetchPayrollStatistics = vi.fn().mockResolvedValue({
    total_employees: 5,
    total_amount: 50000000,
    average_salary: 10000000,
});
const fetchPayrollDetails = vi.fn().mockResolvedValue({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 50, total: 0, from: 0, to: 0 },
});
const fetchPayrollActivityLogs = vi.fn().mockResolvedValue([]);
const fetchPayrollNotificationDeliveries = vi.fn().mockResolvedValue({
    summary: {
        total_recipients: 0,
        total_attempts: 0,
        sent_count: 0,
        failed_count: 0,
        skipped_count: 0,
        auto_attempt_count: 0,
        manual_attempt_count: 0,
    },
    latest_by_employee: [],
});
const fetchPayrollReconciliation = vi.fn().mockResolvedValue({
    summary: { total_employees: 0, critical_count: 0, warning_count: 0 },
    exceptions: [],
});
const approvePayroll = vi.fn().mockResolvedValue(undefined);
const markAsPaid = vi.fn().mockResolvedValue(undefined);
const resendNotifications = vi.fn().mockResolvedValue(undefined);
const exportExcel = vi.fn().mockResolvedValue(undefined);

const grantedPermissions = new Set();
const setPermissions = (permissions) => {
    grantedPermissions.clear();
    permissions.forEach((p) => grantedPermissions.add(p));
};

vi.mock("@/helpers/permissionHelper", () => ({
    can: (permission) => grantedPermissions.has(permission),
}));

vi.mock("@/stores/payroll", () => ({
    usePayrollStore: () => ({
        fetchPayroll,
        fetchPayrollStatistics,
        fetchPayrollDetails,
        fetchPayrollActivityLogs,
        fetchPayrollNotificationDeliveries,
        fetchPayrollReconciliation,
        approvePayroll,
        markAsPaid,
        reopenPayroll,
        resendNotifications,
        exportExcel,
    }),
}));

vi.mock("vue-router", () => ({
    useRoute: () => ({ params: { id: "1" } }),
    useRouter: () => ({ back: vi.fn() }),
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
    }),
}));

vi.mock("@/composables/useConfirmAction", () => ({
    useConfirmAction: (opts) => {
        const isModalOpen = ref(false);
        const isProcessing = ref(false);
        return {
            isModalOpen,
            isProcessing,
            openModal: () => {
                if (opts?.onOpen) opts.onOpen();
                isModalOpen.value = true;
            },
            closeModal: () => { isModalOpen.value = false; },
            confirmAction: vi.fn(),
        };
    },
}));

import PayrollDetail from "@/views/admin/payroll/PayrollDetail.vue";

const flushView = async () => {
    await nextTick();
    await Promise.resolve();
    await Promise.resolve();
    await nextTick();
};

const approvedPayroll = {
    id: 1,
    status: "approved",
    correction_count: 0,
    created_at: "2026-04-07T00:00:00.000Z",
    is_legacy_settings_version: false,
    payroll_setting_version: {
        version_number: 3,
        payday_day: 25,
        attendance_cutoff_day: 25,
        working_days_mode: "auto_business_days",
        default_working_days: 22,
        absent_deduction_rate: 1,
        rounding_mode: "nearest",
        rounding_unit: 1000,
    },
};

const factory = () =>
    mount(PayrollDetail, {
        global: {
            stubs: {
                Pagination: { template: '<div class="pagination-stub" />' },
                ModalWrapper: {
                    props: ["show", "title"],
                    template: '<div v-if="show"><slot /><slot name="footer" /></div>',
                },
                AnimatedValue: {
                    props: ["value"],
                    template: "<span>{{ value }}</span>",
                },
            },
        },
    });

describe("PayrollDetail — Reopen Confirmation Modal", () => {
    beforeEach(() => {
        setPermissions(["payroll-list", "payroll-edit", "payroll-process", "payroll-statistics"]);
        fetchPayroll.mockResolvedValue(approvedPayroll);
        reopenPayroll.mockClear();
        fetchPayroll.mockClear();
        fetchPayroll.mockResolvedValue(approvedPayroll);
    });

    it("shows re-approval warning banner inside reopen modal", async () => {
        const wrapper = factory();
        await flushView();

        // Open the reopen modal
        const reopenBtn = wrapper.find('[data-testid="payroll-reopen"]');
        expect(reopenBtn.exists()).toBe(true);
        await reopenBtn.trigger("click");
        await nextTick();

        // Warning banner content should be visible
        expect(wrapper.text()).toContain("Re-approval required");
        expect(wrapper.text()).toContain("approval process again before payment");
    });

    it("disables confirm button when reason is fewer than 10 characters", async () => {
        const wrapper = factory();
        await flushView();

        const reopenBtn = wrapper.find('[data-testid="payroll-reopen"]');
        await reopenBtn.trigger("click");
        await nextTick();

        const textarea = wrapper.find('[data-testid="payroll-reopen-reason"]');
        await textarea.setValue("Too short");

        const confirmBtn = wrapper.find('[data-testid="payroll-confirm-reopen"]');
        expect(confirmBtn.attributes("disabled")).toBeDefined();
    });

    it("enables confirm button when reason meets minimum length", async () => {
        const wrapper = factory();
        await flushView();

        const reopenBtn = wrapper.find('[data-testid="payroll-reopen"]');
        await reopenBtn.trigger("click");
        await nextTick();

        const textarea = wrapper.find('[data-testid="payroll-reopen-reason"]');
        await textarea.setValue("Overtime calculation needs correction for March.");

        const confirmBtn = wrapper.find('[data-testid="payroll-confirm-reopen"]');
        expect(confirmBtn.attributes("disabled")).toBeUndefined();
    });

    it("shows previous correction count when correction_count > 0", async () => {
        fetchPayroll.mockResolvedValue({ ...approvedPayroll, correction_count: 2 });

        const wrapper = factory();
        await flushView();

        const reopenBtn = wrapper.find('[data-testid="payroll-reopen"]');
        await reopenBtn.trigger("click");
        await nextTick();

        expect(wrapper.text()).toContain("corrected 2 times previously");
    });

    it("does not show correction history text when correction_count is 0", async () => {
        fetchPayroll.mockResolvedValue({ ...approvedPayroll, correction_count: 0 });

        const wrapper = factory();
        await flushView();

        const reopenBtn = wrapper.find('[data-testid="payroll-reopen"]');
        await reopenBtn.trigger("click");
        await nextTick();

        expect(wrapper.text()).not.toContain("previously");
    });
});
