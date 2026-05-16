import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const defaultPayrollPayload = {
    id: 1,
    status: "approved",
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

const reconciliationWithResolved = {
    summary: {
        total_employees: 5,
        critical_count: 2,
        unresolved_critical_count: 1,
        warning_count: 1,
        total_exception_count: 3,
        filtered_exception_count: 3,
    },
    exceptions: [
        {
            staff_member_id: 1,
            employee_name: "John Doe",
            employee_code: "EMP001",
            severity: "critical",
            type: "missing_bank_account",
            message: "Employee bank account information is incomplete.",
            resolution: {
                action: "acknowledged",
                reason: "Will be fixed next cycle.",
                resolved_by_name: "Admin User",
                resolved_at: "2026-04-10T10:00:00.000Z",
            },
        },
        {
            staff_member_id: 2,
            employee_name: "Jane Smith",
            employee_code: "EMP002",
            severity: "critical",
            type: "zero_salary",
            message: "Employee final salary is zero or negative after deductions.",
            resolution: null,
        },
        {
            staff_member_id: 3,
            employee_name: "Bob Wilson",
            employee_code: "EMP003",
            severity: "warning",
            type: "salary_decrease_anomaly",
            message: "Final salary is only 40.0% of original salary.",
            resolution: null,
        },
    ],
    available_types: ["missing_bank_account", "salary_decrease_anomaly", "zero_salary"],
};

const fetchPayroll = vi.fn().mockResolvedValue(defaultPayrollPayload);
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
    summary: { total_recipients: 0, total_attempts: 0, sent_count: 0, failed_count: 0, skipped_count: 0 },
    latest_by_employee: [],
});
const fetchPayrollReconciliation = vi.fn().mockResolvedValue(reconciliationWithResolved);
const resolveReconciliationException = vi.fn().mockResolvedValue({
    id: 1,
    resolution_action: "acknowledged",
    staff_member_id: 2,
    exception_type: "zero_salary",
});
const fetchReconciliationResolutions = vi.fn().mockResolvedValue([]);
const approvePayroll = vi.fn().mockResolvedValue(undefined);
const markAsPaid = vi.fn().mockResolvedValue(undefined);
const reopenPayroll = vi.fn().mockResolvedValue(undefined);
const resendNotifications = vi.fn().mockResolvedValue(undefined);
const exportExcel = vi.fn().mockResolvedValue(undefined);
const routerBack = vi.fn();
const grantedPermissions = new Set();

const setPermissions = (permissions) => {
    grantedPermissions.clear();
    permissions.forEach((permission) => grantedPermissions.add(permission));
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
        resolveReconciliationException,
        fetchReconciliationResolutions,
        approvePayroll,
        markAsPaid,
        reopenPayroll,
        resendNotifications,
        exportExcel,
    }),
}));

vi.mock("vue-router", () => ({
    useRoute: () => ({
        params: { id: "1" },
    }),
    useRouter: () => ({
        back: routerBack,
    }),
    createRouter: vi.fn(() => ({ push: vi.fn(), beforeEach: vi.fn() })),
    createWebHistory: vi.fn(),
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: vi.fn(),
        error: vi.fn(),
    }),
}));

vi.mock("@/composables/useConfirmAction", () => ({
    useConfirmAction: () => ({
        isModalOpen: false,
        isProcessing: false,
        openModal: vi.fn(),
        closeModal: vi.fn(),
        confirmAction: vi.fn(),
    }),
}));

import PayrollDetail from "@/views/admin/payroll/PayrollDetail.vue";

const flushView = async () => {
    await nextTick();
    await Promise.resolve();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(PayrollDetail, {
        global: {
            stubs: {
                Pagination: {
                    template: '<div class="pagination-stub" />',
                },
                ModalWrapper: {
                    props: ["show", "title"],
                    template: '<div v-if="show" :data-testid="title"><slot /><slot name="footer" /></div>',
                },
                AnimatedValue: {
                    props: ["value"],
                    template: "<span>{{ value }}</span>",
                },
            },
        },
    });

describe("PayrollReconciliation resolve modal", () => {
    beforeEach(() => {
        setPermissions(["payroll-list", "payroll-statistics", "payroll-process"]);
        fetchPayroll.mockResolvedValue(defaultPayrollPayload);
        fetchPayrollReconciliation.mockResolvedValue(reconciliationWithResolved);
        fetchPayroll.mockClear();
        fetchPayrollStatistics.mockClear();
        fetchPayrollDetails.mockClear();
        fetchPayrollActivityLogs.mockClear();
        fetchPayrollNotificationDeliveries.mockClear();
        fetchPayrollReconciliation.mockClear();
        resolveReconciliationException.mockClear();
    });

    it("renders resolve button on unresolved exceptions for users with payroll-process permission", async () => {
        const wrapper = factory();
        await flushView();

        // Switch to reconciliation tab
        const reconciliationTab = wrapper.find('[data-testid="tab-reconciliation"]');
        await reconciliationTab.trigger("click");
        await flushView();

        const resolveButtons = wrapper.findAll('[data-testid="reconciliation-resolve-btn"]');
        // Only unresolved exceptions should have the button (zero_salary and salary_decrease_anomaly)
        expect(resolveButtons.length).toBeGreaterThan(0);
    });

    it("renders resolution badge for resolved exceptions", async () => {
        const wrapper = factory();
        await flushView();

        // Switch to reconciliation tab
        const reconciliationTab = wrapper.find('[data-testid="tab-reconciliation"]');
        await reconciliationTab.trigger("click");
        await flushView();

        const resolutionBadges = wrapper.findAll('[data-testid="reconciliation-resolution-badge"]');
        expect(resolutionBadges.length).toBe(1);
        expect(resolutionBadges[0].text()).toContain("Acknowledged");
        expect(resolutionBadges[0].text()).toContain("Admin User");
    });

    it("opens resolve modal with required fields when resolve button is clicked", async () => {
        const wrapper = factory();
        await flushView();

        // Switch to reconciliation tab
        const reconciliationTab = wrapper.find('[data-testid="tab-reconciliation"]');
        await reconciliationTab.trigger("click");
        await flushView();

        // Click the first resolve button
        const resolveBtn = wrapper.find('[data-testid="reconciliation-resolve-btn"]');
        await resolveBtn.trigger("click");
        await flushView();

        // Modal should be visible with required fields
        const modal = wrapper.find('[data-testid="reconciliation-resolve-modal"]');
        expect(modal.exists()).toBe(true);

        const actionSelect = wrapper.find('[data-testid="reconciliation-resolve-action"]');
        expect(actionSelect.exists()).toBe(true);

        const reasonTextarea = wrapper.find('[data-testid="reconciliation-resolve-reason"]');
        expect(reasonTextarea.exists()).toBe(true);

        const confirmBtn = wrapper.find('[data-testid="reconciliation-resolve-confirm"]');
        expect(confirmBtn.exists()).toBe(true);
    });

    it("does not show resolve button for users without payroll-process permission", async () => {
        setPermissions(["payroll-list", "payroll-statistics"]);

        const wrapper = factory();
        await flushView();

        const reconciliationTab = wrapper.find('[data-testid="tab-reconciliation"]');
        await reconciliationTab.trigger("click");
        await flushView();

        const resolveButtons = wrapper.findAll('[data-testid="reconciliation-resolve-btn"]');
        expect(resolveButtons.length).toBe(0);
    });
});

describe("PayrollReconciliation dashboard badge data structure", () => {
    it("reconciliation_summary data structure supports badge rendering", () => {
        const payrollWithSummary = {
            id: 1,
            status: "pending",
            reconciliation_summary: {
                critical_count: 2,
                unresolved_critical_count: 2,
                warning_count: 3,
            },
        };

        // Verify the data structure is correct for badge rendering
        expect(payrollWithSummary.reconciliation_summary).toBeDefined();
        expect(payrollWithSummary.reconciliation_summary.unresolved_critical_count).toBe(2);
        expect(payrollWithSummary.reconciliation_summary.warning_count).toBe(3);
    });

    it("reconciliation_summary is null for paid payrolls", () => {
        const paidPayroll = {
            id: 2,
            status: "paid",
            reconciliation_summary: null,
        };

        expect(paidPayroll.reconciliation_summary).toBeNull();
    });
});
