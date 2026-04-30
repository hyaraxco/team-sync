import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, reactive } from "vue";

const push = vi.fn();
const toastSuccess = vi.fn();
const toastError = vi.fn();
const toastWarning = vi.fn();

const mockDashboard = {
    salary_month: "2026-04",
    attendance_period: {
        id: 1,
        status: "review",
        cutoff_day: 25,
        cutoff_date: "2026-04-25",
    },
    generation: {
        can_generate: false,
        reason_code: "blocked_employees",
        message: "Some employees are blocked.",
    },
    summary: {
        total_employees: 10,
        ready_employees: 6,
        warning_employees: 2,
        blocked_employees: 2,
        employees_with_attendance: 8,
    },
    employees: [
        {
            staff_member_id: 1,
            employee_code: "EMP001",
            employee_name: "Alice Johnson",
            team_name: "Engineering",
            status: "ready",
            blocker_reasons: [],
            warning_flags: [],
            metrics: {
                scheduled_working_days: 22,
                covered_days: 22,
                no_coverage_days: 0,
                present_days: 20,
                late_days: 1,
                half_day_count: 0,
                paid_leave_days: 2,
                unpaid_leave_days: 0,
                absent_days: 0,
                invalid_leave_count: 0,
            },
            attendance_workspace_url: "/admin/attendances?search=EMP001&date=2026-04-01",
        },
        {
            staff_member_id: 2,
            employee_code: "EMP002",
            employee_name: "Bob Smith",
            team_name: "Engineering",
            status: "blocked",
            blocker_reasons: ["pending_leave_approval"],
            warning_flags: [],
            metrics: {
                scheduled_working_days: 22,
                covered_days: 18,
                no_coverage_days: 4,
                present_days: 16,
                late_days: 2,
                half_day_count: 1,
                paid_leave_days: 2,
                unpaid_leave_days: 0,
                absent_days: 2,
                invalid_leave_count: 0,
            },
            attendance_workspace_url: "/admin/attendances?search=EMP002&date=2026-04-01",
        },
        {
            staff_member_id: 3,
            employee_code: "EMP003",
            employee_name: "Carol White",
            team_name: "Marketing",
            status: "warning",
            blocker_reasons: [],
            warning_flags: ["high_late_trend"],
            metrics: {
                scheduled_working_days: 22,
                covered_days: 20,
                no_coverage_days: 2,
                present_days: 18,
                late_days: 5,
                half_day_count: 2,
                paid_leave_days: 2,
                unpaid_leave_days: 0,
                absent_days: 0,
                invalid_leave_count: 0,
            },
            attendance_workspace_url: "/admin/attendances?search=EMP003&date=2026-04-01",
        },
    ],
    blocked_reasons: {
        pending_leave_approval: [2],
        sick_proof_unresolved: [],
        missing_attendance_or_valid_leave: [],
        invalid_leave_entitlement: [],
    },
    warning_flags: {
        absent_pct_threshold_reached: [],
        unresolved_policy_mismatch: [],
        high_late_trend: [3],
        high_half_day_trend: [],
    },
};

const mockTeamSummary = {
    salary_month: "2026-04",
    teams: [
        {
            team_name: "Engineering",
            total: 2,
            ready: 1,
            warning: 0,
            blocked: 1,
            coverage_pct: 90.9,
        },
        {
            team_name: "Marketing",
            total: 1,
            ready: 0,
            warning: 1,
            blocked: 0,
            coverage_pct: 90.9,
        },
    ],
    unassigned: {
        total: 0,
        ready: 0,
        warning: 0,
        blocked: 0,
        coverage_pct: 0,
    },
};

const payrollStore = reactive({
    loading: false,
    error: null,
    fetchReadinessDashboard: vi.fn(async () => mockDashboard),
    fetchReadinessTeamSummary: vi.fn(async () => mockTeamSummary),
});

vi.mock("vue-router", () => ({
    useRouter: () => ({
        push,
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

import PayrollReadiness from "@/views/admin/payroll/PayrollReadiness.vue";

const factory = () => mount(PayrollReadiness);

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

describe("PayrollReadiness smoke", () => {
    beforeEach(() => {
        payrollStore.loading = false;
        payrollStore.error = null;
        payrollStore.fetchReadinessDashboard = vi.fn(async () => mockDashboard);
        payrollStore.fetchReadinessTeamSummary = vi.fn(async () => mockTeamSummary);
        push.mockClear();
        toastSuccess.mockClear();
        toastError.mockClear();
        toastWarning.mockClear();
    });

    it("renders summary cards with correct values", async () => {
        const wrapper = factory();
        await flushAsync();

        expect(wrapper.find('[data-testid="readiness-total"]').text()).toBe("10");
        expect(wrapper.find('[data-testid="readiness-ready"]').text()).toBe("6");
        expect(wrapper.find('[data-testid="readiness-warning"]').text()).toBe("2");
        expect(wrapper.find('[data-testid="readiness-blocked"]').text()).toBe("2");
    });

    it("renders overall readiness percentage", async () => {
        const wrapper = factory();
        await flushAsync();

        expect(wrapper.find('[data-testid="readiness-overall-pct"]').text()).toBe("60%");
    });

    it("renders team breakdown section", async () => {
        const wrapper = factory();
        await flushAsync();

        const teamBreakdown = wrapper.find('[data-testid="readiness-team-breakdown"]');
        expect(teamBreakdown.exists()).toBe(true);
        expect(teamBreakdown.text()).toContain("Engineering");
        expect(teamBreakdown.text()).toContain("Marketing");
    });

    it("renders employee table with correct columns", async () => {
        const wrapper = factory();
        await flushAsync();

        const table = wrapper.find('[data-testid="readiness-table"]');
        expect(table.exists()).toBe(true);

        const headers = table.findAll("th");
        const headerTexts = headers.map((h) => h.text());
        expect(headerTexts).toContain("Name");
        expect(headerTexts).toContain("Code");
        expect(headerTexts).toContain("Team");
        expect(headerTexts).toContain("Status");
        expect(headerTexts).toContain("Coverage %");
        expect(headerTexts).toContain("Blockers");
        expect(headerTexts).toContain("Warnings");
        expect(headerTexts).toContain("Actions");
    });

    it("renders employee rows", async () => {
        const wrapper = factory();
        await flushAsync();

        expect(wrapper.find('[data-testid="readiness-row-1"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="readiness-row-2"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="readiness-row-3"]').exists()).toBe(true);
    });

    it("filter tabs work correctly", async () => {
        const wrapper = factory();
        await flushAsync();

        const blockedFilter = wrapper.find('[data-testid="readiness-filter-blocked"]');
        await blockedFilter.trigger("click");
        await nextTick();

        const rows = wrapper.findAll("tbody tr");
        const visibleRows = rows.filter((r) => !r.classes().includes("bg-slate-50"));
        expect(visibleRows.length).toBe(1);
        expect(wrapper.find('[data-testid="readiness-row-2"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="readiness-row-1"]').exists()).toBe(false);
    });

    it("calculates coverage percentage correctly", async () => {
        const wrapper = factory();
        await flushAsync();

        // Alice: 22/22 = 100%
        const row1 = wrapper.find('[data-testid="readiness-row-1"]');
        expect(row1.text()).toContain("100%");

        // Bob: 18/22 = 82%
        const row2 = wrapper.find('[data-testid="readiness-row-2"]');
        expect(row2.text()).toContain("82%");
    });

    it("export button exists", async () => {
        const wrapper = factory();
        await flushAsync();

        expect(wrapper.find('[data-testid="readiness-export-btn"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="readiness-export-report-btn"]').exists()).toBe(true);
    });

    it("go to generate payroll button navigates correctly", async () => {
        const wrapper = factory();
        await flushAsync();

        const btn = wrapper.find('[data-testid="readiness-go-generate"]');
        await btn.trigger("click");

        expect(push).toHaveBeenCalledWith({ name: "admin.payroll.create" });
    });

    it("renders blocker panel with aggregate counts", async () => {
        const wrapper = factory();
        await flushAsync();

        const blockerPanel = wrapper.find('[data-testid="readiness-blocker-panel"]');
        expect(blockerPanel.exists()).toBe(true);
        expect(blockerPanel.text()).toContain("Pending leave approval");
        expect(blockerPanel.text()).toContain("1");
    });

    it("renders warning panel with aggregate counts", async () => {
        const wrapper = factory();
        await flushAsync();

        const warningPanel = wrapper.find('[data-testid="readiness-warning-panel"]');
        expect(warningPanel.exists()).toBe(true);
        expect(warningPanel.text()).toContain("High late trend");
        expect(warningPanel.text()).toContain("1");
    });

    it("search filters employees by name", async () => {
        const wrapper = factory();
        await flushAsync();

        const searchInput = wrapper.find('[data-testid="readiness-search"]');
        await searchInput.setValue("Alice");
        await nextTick();

        expect(wrapper.find('[data-testid="readiness-row-1"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="readiness-row-2"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="readiness-row-3"]').exists()).toBe(false);
    });

    it("month selector triggers data fetch", async () => {
        const wrapper = factory();
        await flushAsync();

        payrollStore.fetchReadinessDashboard.mockClear();
        payrollStore.fetchReadinessTeamSummary.mockClear();

        const monthInput = wrapper.find('[data-testid="readiness-month-selector"]');
        await monthInput.setValue("2026-03");
        await flushAsync();

        expect(payrollStore.fetchReadinessDashboard).toHaveBeenCalledWith("2026-03");
        expect(payrollStore.fetchReadinessTeamSummary).toHaveBeenCalledWith("2026-03");
    });

    it("overall coverage percentage is calculated correctly", async () => {
        const wrapper = factory();
        await flushAsync();

        // Total covered: 22 + 18 + 20 = 60, Total scheduled: 22 + 22 + 22 = 66
        // 60/66 = 90.9% → rounds to 91%
        expect(wrapper.find('[data-testid="readiness-coverage"]').text()).toBe("91%");
    });
});
