import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const defaultPayrollPayload = {
  id: 1,
  status: "pending",
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

const fetchPayroll = vi.fn().mockResolvedValue(defaultPayrollPayload);
const fetchPayrollStatistics = vi.fn().mockResolvedValue({
  total_employees: 12,
  total_amount: 125000000,
  average_salary: 10400000,
});
const fetchPayrollDetails = vi.fn().mockResolvedValue({
  data: [],
  meta: {
    current_page: 1,
    last_page: 1,
    per_page: 50,
    total: 0,
    from: 0,
    to: 0,
  },
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
  summary: {
    total_employees: 0,
    critical_count: 0,
    warning_count: 0,
  },
  exceptions: [],
});
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
          template: '<div v-if="show"><slot /><slot name="footer" /></div>',
        },
        AnimatedValue: {
          props: ["value"],
          template: '<span>{{ value }}</span>',
        },
      },
    },
  });

describe("PayrollDetail smoke", () => {
  beforeEach(() => {
    setPermissions([]);
    fetchPayroll.mockResolvedValue(defaultPayrollPayload);
    fetchPayroll.mockClear();
    fetchPayrollStatistics.mockClear();
    fetchPayrollDetails.mockClear();
    fetchPayrollActivityLogs.mockClear();
    fetchPayrollNotificationDeliveries.mockClear();
    fetchPayrollReconciliation.mockClear();
    approvePayroll.mockClear();
    markAsPaid.mockClear();
    reopenPayroll.mockClear();
    resendNotifications.mockClear();
    exportExcel.mockClear();
    routerBack.mockClear();
  });

  it("keeps HR away from sensitive statistics and payment controls", async () => {
    setPermissions(["payroll-list", "payroll-create"]);

    const wrapper = factory();
    await flushView();
    await vi.waitFor(() => expect(fetchPayrollActivityLogs).toHaveBeenCalledWith("1"));

    expect(fetchPayroll).toHaveBeenCalled();
    expect(fetchPayrollDetails).toHaveBeenCalled();
    expect(fetchPayrollStatistics).not.toHaveBeenCalled();
    expect(fetchPayrollNotificationDeliveries).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain("Payroll Draft Review");
    expect(wrapper.text()).not.toContain("Mark as Paid");
    expect(wrapper.text()).not.toContain("Total Employees");
    expect(wrapper.text()).toContain("Export Excel");
  });

  it("shows Finance statistics and approval controls for pending payroll", async () => {
    setPermissions(["payroll-list", "payroll-edit", "payroll-process", "payroll-statistics"]);

    const wrapper = factory();
    await flushView();
    await vi.waitFor(() => expect(fetchPayrollActivityLogs).toHaveBeenCalledWith("1"));

    expect(fetchPayrollStatistics).toHaveBeenCalledWith("1");
    expect(wrapper.text()).toContain("Total Employees");
    expect(wrapper.text()).toContain("Approve Payroll");
    expect(wrapper.text()).not.toContain("Mark as Paid");
    expect(wrapper.text()).not.toContain("Payroll Draft Review");
  });

  it("shows mark as paid only after payroll has been approved", async () => {
    setPermissions(["payroll-list", "payroll-edit", "payroll-process", "payroll-statistics"]);
    fetchPayroll.mockResolvedValueOnce({
      ...defaultPayrollPayload,
      status: "approved",
    });

    const wrapper = factory();
    await flushView();

    expect(wrapper.text()).toContain("Payroll approved");
    expect(wrapper.text()).toContain("Mark as Paid");
    expect(wrapper.text()).toContain("Reopen for Correction");
    expect(wrapper.text()).not.toContain("Approve Payroll");
  });

  it("shows automatic notification info after payroll is paid", async () => {
    setPermissions(["payroll-list", "payroll-process", "payroll-statistics"]);
    fetchPayrollNotificationDeliveries.mockResolvedValueOnce({
      summary: {
        total_recipients: 1,
        total_attempts: 2,
        sent_count: 2,
        failed_count: 0,
        skipped_count: 0,
        auto_attempt_count: 1,
        manual_attempt_count: 1,
        last_attempt_at: "2026-04-12T09:20:00.000Z",
        last_sent_at: "2026-04-12T09:20:00.000Z",
      },
      latest_by_employee: [
        {
          payroll_detail_id: 55,
          employee_id: 77,
          employee_name: "Delivery User",
          employee_code: "EMP077",
          recipient_email: "delivery@teamsync.com",
          delivery_status: "sent",
          trigger_type: "manual_resend",
          failure_reason: null,
          attempted_at: "2026-04-12T09:20:00.000Z",
          sent_at: "2026-04-12T09:20:00.000Z",
          attempt_count: 2,
          payslip_path: "/admin/my-payroll/55",
        },
      ],
    });
    fetchPayroll.mockResolvedValueOnce({
      ...defaultPayrollPayload,
      status: "paid",
    });

    const wrapper = factory();
    await flushView();

    expect(fetchPayrollNotificationDeliveries).toHaveBeenCalledWith("1");

    expect(wrapper.text()).toContain("Notifications sent automatically");
    expect(wrapper.text()).toContain("Reopen for Correction");
    expect(wrapper.text()).toContain("Resend Notifications");
    expect(wrapper.text()).toContain("Notification Delivery Summary");

    await vi.waitFor(() => {
      expect(wrapper.find('[data-testid="payroll-notification-last-attempt"]').exists()).toBe(true);
      expect(wrapper.find('[data-testid="payroll-notification-last-sent"]').exists()).toBe(true);
    });

    expect(wrapper.text()).toContain("Payslip deep-link: /admin/my-payroll/55");
  });

  it("locks mark as paid when reconciliation has critical issues", async () => {
    setPermissions(["payroll-list", "payroll-process", "payroll-statistics"]);
    fetchPayroll.mockResolvedValueOnce({
      ...defaultPayrollPayload,
      status: "approved",
    });
    fetchPayrollReconciliation.mockResolvedValueOnce({
      summary: {
        total_employees: 1,
        critical_count: 1,
        warning_count: 0,
      },
      exceptions: [
        {
          employee_id: 99,
          employee_name: "Adjustment User",
          employee_code: "EMP099",
          severity: "critical",
          type: "missing_bank_account",
          message: "Employee bank account information is incomplete.",
        },
      ],
    });

    const wrapper = factory();
    await flushView();

    await vi.waitFor(() => {
      expect(fetchPayrollReconciliation).toHaveBeenCalledWith("1");
      expect(wrapper.find('[data-testid="payroll-reconciliation-critical-count"]').text()).toContain("1");
    });

    const markAsPaidButton = wrapper.get('[data-testid="payroll-mark-as-paid"]');
    expect(markAsPaidButton.attributes("disabled")).toBeDefined();
    expect(wrapper.find('[data-testid="payroll-reconciliation-list"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="payroll-reconciliation-blocked"]').exists()).toBe(true);
  });

  it("lets finance filter reconciliation issues by severity and type", async () => {
    setPermissions(["payroll-list", "payroll-process", "payroll-statistics"]);
    fetchPayroll.mockResolvedValueOnce({
      ...defaultPayrollPayload,
      status: "approved",
    });
    fetchPayrollReconciliation
      .mockResolvedValueOnce({
        summary: {
          total_employees: 2,
          total_exception_count: 2,
          filtered_exception_count: 2,
          critical_count: 1,
          warning_count: 1,
        },
        available_types: ["missing_bank_account", "excessive_deduction"],
        applied_filters: {
          severity: null,
          type: null,
        },
        exceptions: [
          {
            employee_id: 101,
            employee_name: "Critical Employee",
            employee_code: "EMP101",
            severity: "critical",
            type: "missing_bank_account",
            message: "Employee bank account information is incomplete.",
          },
          {
            employee_id: 202,
            employee_name: "Warning Employee",
            employee_code: "EMP202",
            severity: "warning",
            type: "excessive_deduction",
            message: "Deduction ratio exceeds threshold.",
          },
        ],
      })
      .mockResolvedValueOnce({
        summary: {
          total_employees: 2,
          total_exception_count: 2,
          filtered_exception_count: 1,
          critical_count: 1,
          warning_count: 1,
        },
        available_types: ["missing_bank_account", "excessive_deduction"],
        applied_filters: {
          severity: "warning",
          type: null,
        },
        exceptions: [
          {
            employee_id: 202,
            employee_name: "Warning Employee",
            employee_code: "EMP202",
            severity: "warning",
            type: "excessive_deduction",
            message: "Deduction ratio exceeds threshold.",
          },
        ],
      })
      .mockResolvedValueOnce({
        summary: {
          total_employees: 2,
          total_exception_count: 2,
          filtered_exception_count: 0,
          critical_count: 1,
          warning_count: 1,
        },
        available_types: ["missing_bank_account", "excessive_deduction"],
        applied_filters: {
          severity: "warning",
          type: "missing_bank_account",
        },
        exceptions: [],
      });

    const wrapper = factory();
    await flushView();

    await vi.waitFor(() => {
      expect(fetchPayrollReconciliation).toHaveBeenCalledWith("1");
      expect(wrapper.find('[data-testid="payroll-reconciliation-list"]').exists()).toBe(
        true
      );
    });

    expect(wrapper.text()).toContain("Showing 2 of 2 issue(s)");
    expect(wrapper.text()).toContain("Critical Employee");
    expect(wrapper.text()).toContain("Warning Employee");

    await wrapper
      .get('[data-testid="payroll-reconciliation-filter-severity"]')
      .setValue("warning");

    await vi.waitFor(() => {
      expect(fetchPayrollReconciliation).toHaveBeenCalledWith("1", {
        severity: "warning",
      });
      expect(wrapper.text()).toContain("Showing 1 of 2 issue(s)");
    });

    expect(wrapper.text()).toContain("Warning Employee");
    expect(wrapper.text()).not.toContain("Critical Employee");

    await wrapper
      .get('[data-testid="payroll-reconciliation-filter-type"]')
      .setValue("missing_bank_account");

    await vi.waitFor(() => {
      expect(fetchPayrollReconciliation).toHaveBeenCalledWith("1", {
        severity: "warning",
        type: "missing_bank_account",
      });
      expect(wrapper.find('[data-testid="payroll-reconciliation-filter-empty"]').exists()).toBe(
        true
      );
    });
  });

  it("renders payroll activity timeline entries", async () => {
    setPermissions(["payroll-list", "payroll-statistics"]);
    fetchPayrollActivityLogs.mockResolvedValueOnce([
      {
        id: 10,
        event_type: "generated",
        title: "Payroll draft generated",
        description: "Payroll draft was generated from validated attendance data.",
        occurred_at: "2026-04-07T10:15:00.000Z",
        actor: {
          id: 7,
          name: "Dwimeta",
          email: "dwimeta@teamsync.com",
        },
        metadata: {
          settings_version_number: 3,
          settings_snapshot: {
            attendance_cutoff_day: 25,
            working_days_mode: "auto_business_days",
            rounding_mode: "nearest",
          },
        },
      },
    ]);

    const wrapper = factory();
    await flushView();
    await vi.waitFor(() =>
      expect(wrapper.find('[data-testid="payroll-activity-list"]').exists()).toBe(true)
    );

    expect(wrapper.get('[data-testid="payroll-activity-list"]').text()).toContain(
      "Payroll draft generated"
    );
    expect(wrapper.text()).toContain("Dwimeta");
    expect(wrapper.find('[data-testid="payroll-activity-settings-snapshot"]').exists()).toBe(true);
    expect(wrapper.text()).toContain("Settings v3");
  });

  it("shows finance resend notifications action for paid payroll", async () => {
    setPermissions(["payroll-list", "payroll-process", "payroll-statistics"]);
    fetchPayroll.mockResolvedValueOnce({
      ...defaultPayrollPayload,
      status: "paid",
    });

    const wrapper = factory();
    await flushView();

    expect(wrapper.find('[data-testid="payroll-resend-notifications"]').exists()).toBe(true);
    expect(resendNotifications).not.toHaveBeenCalled();
  });

  it("renders settings used section for versioned payroll", async () => {
    setPermissions(["payroll-list", "payroll-statistics"]);

    const wrapper = factory();
    await flushView();

    expect(wrapper.find('[data-testid="payroll-settings-used-grid"]').exists()).toBe(true);
    expect(wrapper.text()).toContain("Settings Used");
    expect(wrapper.text()).toContain("Auto business days");
  });

  it("shows legacy fallback when payroll has no settings version reference", async () => {
    setPermissions(["payroll-list", "payroll-statistics"]);
    fetchPayroll.mockResolvedValueOnce({
      ...defaultPayrollPayload,
      payroll_setting_version: null,
      is_legacy_settings_version: true,
    });

    const wrapper = factory();
    await flushView();

    expect(wrapper.find('[data-testid="payroll-settings-used-legacy"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="payroll-settings-used-legacy-warning"]').exists()).toBe(true);
  });

  it("renders adjustment totals and count in employee details", async () => {
    setPermissions(["payroll-list", "payroll-statistics"]);
    fetchPayrollDetails.mockResolvedValue({
      data: [
        {
          employee: {
            id: 99,
            code: "EMP099",
            user: {
              name: "Adjustment User",
              profile_photo: null,
            },
            job_information: {
              job_title: "Engineer",
              team: { name: "Engineering" },
            },
            bank_information: {
              bank_name: "BCA",
              account_number: "123",
              account_holder_name: "Adjustment User",
            },
          },
          original_salary: 10000000,
          final_salary: 10250000,
          deduction_amount: 500000,
          attended_days: 20,
          sick_days: 0,
          absent_days: 1,
          adjustment_total_amount: 750000,
          adjustments: [
            {
              id: 1,
              amount_delta: 750000,
              adjustment_kind: "absence_correction_credit",
            },
          ],
        },
      ],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 50,
        total: 1,
        from: 1,
        to: 1,
      },
    });

    const wrapper = factory();
    await flushView();
    await vi.waitFor(() => expect(wrapper.text()).toContain("Adjustment User"));

    expect(wrapper.text()).toContain("Adjustments");
    expect(wrapper.text()).toContain("1 item");

    const adjustmentDetailButton = wrapper.find('[data-testid="payroll-adjustment-open-99"]');
    expect(adjustmentDetailButton.exists()).toBe(true);
    await adjustmentDetailButton.trigger("click");

    expect(wrapper.find('[data-testid="payroll-adjustment-detail-modal"]').exists()).toBe(true);
    expect(wrapper.text()).toContain("Detailed adjustment history for");
    expect(wrapper.text()).toContain("Source Period");
  });
});
