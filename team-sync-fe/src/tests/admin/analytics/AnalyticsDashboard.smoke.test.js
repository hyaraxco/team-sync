import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const { analyticsStoreMock, teamStoreMock, optionStoreMock, analyticsStoreRefs, canMock } = vi.hoisted(() => ({
    analyticsStoreMock: {
        fetchExecutiveSummary: vi.fn(),
        fetchWorkforceAnalytics: vi.fn(),
        fetchAttendanceAnalytics: vi.fn(),
        fetchLeaveAnalytics: vi.fn(),
        fetchPayrollAnalytics: vi.fn(),
        fetchProjectAnalytics: vi.fn(),
        exportExcel: vi.fn(),
        exportPdf: vi.fn(),
        setFilters: vi.fn(),
    },
    teamStoreMock: {
        teamsAll: [],
    },
    optionStoreMock: {
        departments: [],
    },
    analyticsStoreRefs: {
        executiveSummary: {
            __v_isRef: true,
            value: {
                period: {
                    label: "Last 3 Months",
                    start: "2026-01-01",
                    end: "2026-03-31",
                },
                kpis: {
                    total_employees: 10,
                    employee_growth: 2,
                },
                attendance_vs_deduction_trend: [],
                monthly_hr_cost: [],
                team_performance: [],
            },
        },
        executiveSummaryLoading: {
            __v_isRef: true,
            value: false,
        },
        period: {
            __v_isRef: true,
            value: "3m",
        },
        department: {
            __v_isRef: true,
            value: null,
        },
        teamId: {
            __v_isRef: true,
            value: null,
        },
    },
    canMock: vi.fn(),
}));

vi.mock("@/stores/analytics", () => ({
    useAnalyticsStore: () => analyticsStoreMock,
}));

vi.mock("@/stores/team", () => ({
    useTeamStore: () => teamStoreMock,
}));

vi.mock("@/stores/option", () => ({
    useOptionStore: () => optionStoreMock,
}));

vi.mock("@/helpers/permissionHelper", () => ({
    can: (...args) => canMock(...args),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: (store) => {
            if (store === analyticsStoreMock) {
                return analyticsStoreRefs;
            }
            return store;
        },
    };
});

import AnalyticsDashboard from "@/views/admin/analytics/AnalyticsDashboard.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(AnalyticsDashboard, {
        global: {
            stubs: {
                StatsCard: { template: '<div class="stats-card-stub"><slot name="icon" /></div>' },
                MainCard: { template: '<div class="main-card-stub"><slot /></div>' },
                AttendanceAnalytics: { template: '<div class="attendance-analytics-stub"></div>' },
                WorkforceAnalytics: { template: '<div class="workforce-analytics-stub"></div>' },
                LeaveAnalytics: { template: '<div class="leave-analytics-stub"></div>' },
                PayrollAnalytics: { template: '<div class="payroll-analytics-stub"></div>' },
                ProjectAnalytics: { template: '<div class="project-analytics-stub"></div>' },
                VueApexCharts: { template: '<div class="apex-chart-stub"></div>' },
            },
        },
    });

describe("AnalyticsDashboard smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        canMock.mockReturnValue(false);
        analyticsStoreMock.fetchExecutiveSummary.mockResolvedValue(undefined);
        analyticsStoreMock.fetchAttendanceAnalytics.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchExecutiveSummary on mount", async () => {
        factory();
        await flushAsync();

        expect(analyticsStoreMock.fetchExecutiveSummary).toHaveBeenCalled();
    });

    it("switches tab and fetches attendance analytics", async () => {
        canMock.mockReturnValue(true);
        const wrapper = factory();
        await flushAsync();

        const attendanceTab = wrapper.findAll("button").find((button) => button.text().includes("Attendance"));
        await attendanceTab.trigger("click");
        await flushAsync();

        expect(analyticsStoreMock.fetchAttendanceAnalytics).toHaveBeenCalled();
    });
});
