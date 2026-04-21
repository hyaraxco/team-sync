import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, reactive } from "vue";

const push = vi.fn();
const back = vi.fn();
const toastSuccess = vi.fn();
const toastError = vi.fn();
const toastWarning = vi.fn();

const payrollStore = reactive({
  loading: false,
  error: null,
  payrolls: [],
  fetchPayrolls: vi.fn(async () => {}),
  fetchGenerateReadiness: vi.fn(async () => ({
    can_generate: true,
    reason_code: "ready",
    message: "Payroll is ready to be generated.",
  })),
  fetchReadinessDashboard: vi.fn(async () => ({
    summary: {
      total_employees: 0,
      ready_employees: 0,
      warning_employees: 0,
      blocked_employees: 0,
    },
    employees: [],
  })),
  generatePayroll: vi.fn(async () => {}),
});

vi.mock("vue-router", () => ({
  useRouter: () => ({
    push,
    back,
  }),
}));

vi.mock("@/composables/useToast", () => ({
  useToast: () => ({
    success: toastSuccess,
    error: toastError,
    warning: toastWarning,
  }),
}));

vi.mock("@/stores/payroll", () => ({
  usePayrollStore: () => payrollStore,
}));

import PayrollCreate from "@/views/admin/payroll/PayrollCreate.vue";

const factory = () => mount(PayrollCreate);

const flushAsync = async () => {
  await nextTick();
  await Promise.resolve();
  await nextTick();
};

const getMonthKey = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  return `${year}-${month}`;
};

const getNextMonthKey = (monthKey) => {
  const [year, month] = monthKey.split("-").map(Number);
  const date = new Date(year, month - 1, 1);
  date.setMonth(date.getMonth() + 1);
  return getMonthKey(date);
};

describe("PayrollCreate smoke", () => {
  beforeEach(() => {
    payrollStore.loading = false;
    payrollStore.error = null;
    payrollStore.payrolls = [];
    payrollStore.fetchPayrolls = vi.fn(async () => {});
    payrollStore.fetchGenerateReadiness = vi.fn(async () => ({
      can_generate: true,
      reason_code: "ready",
      message: "Payroll is ready to be generated.",
    }));
    payrollStore.fetchReadinessDashboard = vi.fn(async () => ({
      summary: {
        total_employees: 0,
        ready_employees: 0,
        warning_employees: 0,
        blocked_employees: 0,
      },
      employees: [],
    }));
    payrollStore.generatePayroll = vi.fn(async () => {});

    push.mockClear();
    back.mockClear();
    toastSuccess.mockClear();
    toastError.mockClear();
    toastWarning.mockClear();
  });

  it("preloads existing payroll months and auto-selects the next available month", async () => {
    const currentMonth = getMonthKey(new Date());
    payrollStore.fetchPayrolls = vi.fn(async () => {
      payrollStore.payrolls = [{ id: 1, period: `${currentMonth}-01` }];
    });

    const wrapper = factory();
    await flushAsync();

    const input = wrapper.find('input[type="month"]');
    expect(payrollStore.fetchPayrolls).toHaveBeenCalledWith({
      page: 1,
      row_per_page: 500,
    });
    expect(input.element.value).toBe(getNextMonthKey(currentMonth));
  });

  it("blocks duplicate month generation on client-side and shows warning toast", async () => {
    payrollStore.fetchPayrolls = vi.fn(async () => {
      payrollStore.payrolls = [{ id: 1, period: "2026-04-01" }];
    });
    payrollStore.fetchGenerateReadiness = vi.fn(async (salaryMonth) => {
      if (salaryMonth === "2026-04") {
        return {
          can_generate: false,
          reason_code: "duplicate_period",
          message: "Payroll for April 2026 already exists.",
        };
      }

      return {
        can_generate: true,
        reason_code: "ready",
        message: "Payroll is ready to be generated.",
      };
    });

    const wrapper = factory();
    await flushAsync();

    const input = wrapper.find('input[type="month"]');
    await input.setValue("2026-04");
    await flushAsync();

    expect(toastWarning).toHaveBeenCalledWith(
      "Payroll period unavailable",
      "Payroll for April 2026 already exists."
    );
    expect(payrollStore.generatePayroll).not.toHaveBeenCalled();
    expect(push).not.toHaveBeenCalled();
    expect(wrapper.find('button[type="submit"]').attributes("disabled")).toBeDefined();
  });

  it("blocks future month selection before submit", async () => {
    const wrapper = factory();
    await flushAsync();

    const futureMonth = getNextMonthKey(getMonthKey(new Date()));
    const input = wrapper.find('input[type="month"]');
    await input.setValue(futureMonth);
    await flushAsync();

    expect(toastWarning).toHaveBeenCalledWith(
      "Payroll period unavailable",
      "Future payroll months are locked until the period starts."
    );
    expect(wrapper.get('[data-testid="payroll-create-readiness-message"]').text()).toContain(
      "Future payroll months are locked"
    );
    expect(wrapper.find('button[type="submit"]').attributes("disabled")).toBeDefined();
  });

  it("generates payroll for valid month, then redirects with success toast", async () => {
    const wrapper = factory();
    await flushAsync();
    const validMonth = getMonthKey(new Date());

    const input = wrapper.find('input[type="month"]');
    await input.setValue(validMonth);
    await wrapper.find("form").trigger("submit");
    await flushAsync();

    expect(payrollStore.generatePayroll).toHaveBeenCalledWith({
      salary_month: validMonth,
    });
    expect(toastSuccess).toHaveBeenCalledWith(
      "Payroll successfully generated",
      expect.stringContaining(String(new Date().getFullYear()))
    );
    expect(push).toHaveBeenCalledWith({ name: "admin.payroll.dashboard" });
  });

  it("shows error toast when API generation fails", async () => {
    const validMonth = getMonthKey(new Date());
    payrollStore.generatePayroll = vi.fn(async () => {
      payrollStore.error = "Payroll for current month already exists";
      throw new Error("Request failed");
    });

    const wrapper = factory();
    await flushAsync();

    const input = wrapper.find('input[type="month"]');
    await input.setValue(validMonth);
    await wrapper.find("form").trigger("submit");
    await flushAsync();

    expect(toastError).toHaveBeenCalledWith(
      "Failed to generate payroll",
      "Payroll for current month already exists"
    );
    expect(push).not.toHaveBeenCalled();
  });

  it("shows readiness warning returned by backend before submit", async () => {
    payrollStore.fetchGenerateReadiness = vi.fn(async () => ({
      can_generate: false,
      reason_code: "cutoff_not_reached",
      message:
        "Payroll for April 2026 can only be generated after the attendance cut-off date on 25 April 2026.",
    }));

    const wrapper = factory();
    await flushAsync();

    await wrapper.find("form").trigger("submit");
    await flushAsync();

    expect(toastWarning).toHaveBeenCalledWith(
      "Payroll not ready",
      "Payroll for April 2026 can only be generated after the attendance cut-off date on 25 April 2026."
    );
    expect(payrollStore.generatePayroll).not.toHaveBeenCalled();
  });

  it("filters readiness employees and opens attendance workspace", async () => {
    payrollStore.fetchReadinessDashboard = vi.fn(async () => ({
      summary: {
        total_employees: 3,
        ready_employees: 1,
        warning_employees: 1,
        blocked_employees: 1,
      },
      employees: [
        {
          employee_id: 99,
          employee_name: "QA Blocked",
          employee_code: "EMP2026040099",
          status: "blocked",
          blocker_reasons: ["missing_attendance_or_valid_leave"],
          warning_flags: [],
          attendance_workspace_url: "/admin/attendances?search=QA+Blocked",
        },
        {
          employee_id: 100,
          employee_name: "QA Warning",
          employee_code: "EMP2026040100",
          status: "warning",
          blocker_reasons: [],
          warning_flags: ["high_late_trend"],
          attendance_workspace_url: "/admin/attendances?search=QA+Warning",
        },
        {
          employee_id: 101,
          employee_name: "QA Ready",
          employee_code: "EMP2026040101",
          status: "ready",
          blocker_reasons: [],
          warning_flags: [],
          attendance_workspace_url: "/admin/attendances?search=QA+Ready",
        },
      ],
    }));

    const wrapper = factory();
    await flushAsync();
    await flushAsync();

    expect(
      wrapper.find('[data-testid="payroll-create-readiness-dashboard"]').exists()
    ).toBe(true);

    expect(wrapper.find('[data-testid="payroll-readiness-row-99"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="payroll-readiness-row-100"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="payroll-readiness-row-101"]').exists()).toBe(true);

    await wrapper.find('[data-testid="payroll-readiness-filter-warning"]').trigger("click");
    await flushAsync();

    expect(wrapper.find('[data-testid="payroll-readiness-row-99"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="payroll-readiness-row-100"]').exists()).toBe(true);

    await wrapper.find('[data-testid="payroll-readiness-filter-all"]').trigger("click");
    await flushAsync();

    await wrapper.find('[data-testid="payroll-readiness-search"]').setValue("0099");
    await flushAsync();

    expect(wrapper.find('[data-testid="payroll-readiness-row-99"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="payroll-readiness-row-100"]').exists()).toBe(false);

    await wrapper
      .find('[data-testid="payroll-readiness-open-attendance-99"]')
      .trigger("click");

    expect(push).toHaveBeenCalledWith("/admin/attendances?search=QA+Blocked");
  });
});
