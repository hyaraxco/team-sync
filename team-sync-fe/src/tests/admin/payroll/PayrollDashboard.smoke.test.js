import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, ref } from "vue";

const payrolls = ref([
    {
        id: 1,
        period: "2026-04-01",
        created_at: "2026-04-07T00:00:00.000Z",
        status: "pending",
        employee_count: 12,
        total_amount: 125000000,
    },
]);
const statistics = ref({
    total_payroll: 12,
    pending_review: 1,
    finalized: 5,
    total_amount: 125000000,
    average_salary: 10400000,
});
const buildAnalyticsPayload = () => ({
    periods_requested: 6,
    periods_returned: 6,
    status_scope: ["approved", "paid"],
    reporting_period: {
        start_month: "2025-11-01",
        end_month: "2026-04-01",
        as_of_timestamp: "2026-04-12T09:00:00.000Z",
    },
    summary: {
        total_payroll_batches: 6,
        total_employee_entries: 72,
        total_amount: 760000000,
        total_deductions: 82000000,
        average_salary_across_periods: 10555555.56,
        average_deduction_rate: 0.1079,
    },
    growth_metrics: {
        salary_growth_percentage: 4.12,
        headcount_change: 3,
        deduction_rate_change: -0.002,
    },
    trends: [
        {
            salary_month: "2025-11-01",
            label: "Nov 2025",
            payroll_count: 1,
            employee_count: 10,
            total_amount: 110000000,
            total_deductions: 13000000,
        },
        {
            salary_month: "2026-04-01",
            label: "Apr 2026",
            payroll_count: 1,
            employee_count: 13,
            total_amount: 145000000,
            total_deductions: 15000000,
        },
    ],
});
const analytics = ref(buildAnalyticsPayload());
const loading = ref(false);
const loadingAnalytics = ref(false);
const success = ref("");
const fetchStatistics = vi.fn().mockResolvedValue(undefined);
const fetchPayrollAnalytics = vi.fn().mockResolvedValue(undefined);
const fetchPayrolls = vi.fn().mockResolvedValue(undefined);
const exportPayrollReport = vi.fn().mockResolvedValue(undefined);
const push = vi.fn();
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
        fetchStatistics,
        fetchPayrollAnalytics,
        fetchPayrolls,
        exportPayrollReport,
    }),
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: vi.fn(),
        error: vi.fn(),
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();

    return {
        ...actual,
        storeToRefs: () => ({
            payrolls,
            statistics,
            analytics,
            loading,
            loadingAnalytics,
            success,
        }),
    };
});

vi.mock("vue-router", () => ({
    useRouter: () => ({
        push,
    }),
}));

import PayrollDashboard from "@/views/admin/payroll/PayrollDashboard.vue";

const factory = () =>
    mount(PayrollDashboard, {
        global: {
            stubs: {
                RouterLink: {
                    props: ["to"],
                    template: "<a :data-route-name=\"typeof to === 'object' ? to.name : ''\"><slot /></a>",
                },
                Alert: {
                    props: ["show", "title", "message"],
                    template: '<div v-if="show">{{ title }} {{ message }}</div>',
                },
                StatusBadge: {
                    props: ["value"],
                    template: '<div class="status-badge-stub">{{ value }}</div>',
                },
                StatsCard: {
                    props: ["title"],
                    template: '<div class="stats-card-stub">{{ title }}</div>',
                },
                MainCard: {
                    props: ["title"],
                    template:
                        '<div class="main-card-stub">{{ title }}<slot /><template v-if="$slots.footer"><slot name="footer" /></template></div>',
                },
                VueApexCharts: {
                    props: ["series", "options"],
                    template: '<div class="apex-chart-stub">chart</div>',
                },
                ModalWrapper: {
                    props: ["show", "title"],
                    template: '<div v-if="show"><slot /><slot name="footer" /></div>',
                },
            },
        },
    });

describe("PayrollDashboard smoke", () => {
    beforeEach(() => {
        setPermissions([]);
        analytics.value = buildAnalyticsPayload();
        loadingAnalytics.value = false;
        fetchStatistics.mockClear();
        fetchPayrollAnalytics.mockClear();
        fetchPayrolls.mockClear();
        exportPayrollReport.mockClear();
        push.mockClear();
    });

    it("uses HR mode without fetching sensitive statistics", async () => {
        setPermissions(["payroll-menu", "payroll-list", "payroll-create"]);

        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(fetchStatistics).not.toHaveBeenCalled();
        expect(fetchPayrollAnalytics).not.toHaveBeenCalled();
        expect(fetchPayrolls).toHaveBeenCalledWith({ page: 1, row_per_page: 10 });
        expect(wrapper.text()).toContain("Payroll Operations");
        expect(wrapper.text()).toContain("Create New Payroll");
        expect(wrapper.text()).not.toContain("Total Payroll Amount");
    });

    it("uses Finance mode and fetches sensitive statistics", async () => {
        setPermissions([
            "payroll-menu",
            "payroll-list",
            "payroll-create",
            "payroll-edit",
            "payroll-process",
            "payroll-statistics",
        ]);

        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(fetchStatistics).toHaveBeenCalled();
        expect(fetchPayrollAnalytics).toHaveBeenCalledWith(6);
        expect(wrapper.text()).toContain("Total Payroll Amount");
        expect(wrapper.text()).toContain("Payroll Analytics (Last 6 Periods)");
        expect(wrapper.text()).toContain("Create New Payroll");
        expect(wrapper.text()).toContain("Readiness Dashboard");
        expect(wrapper.text()).toContain("Export Payroll Report");
        const settingsLink = wrapper.find('[data-testid="payroll-settings-link"]');
        expect(settingsLink.exists()).toBe(true);
        expect(settingsLink.attributes("data-route-name")).toBe("admin.payroll.settings");
        expect(settingsLink.text()).toContain("Finance only");
    });

    it("opens finance export report modal and submits selected filters", async () => {
        setPermissions([
            "payroll-menu",
            "payroll-list",
            "payroll-create",
            "payroll-edit",
            "payroll-process",
            "payroll-statistics",
        ]);

        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        await wrapper.get('[data-testid="payroll-export-report-open"]').trigger("click");
        await nextTick();

        await wrapper.get('[data-testid="payroll-report-type"]').setValue("detail");
        await wrapper.get('[data-testid="payroll-report-status"]').setValue("paid");
        await wrapper.get('[data-testid="payroll-report-period-type"]').setValue("yearly");
        await nextTick();
        await wrapper.get('[data-testid="payroll-report-year"]').setValue("2026");
        await wrapper.get('[data-testid="payroll-report-submit"]').trigger("click");
        await Promise.resolve();

        expect(exportPayrollReport).toHaveBeenCalledWith({
            report_type: "detail",
            status: "paid",
            period_type: "yearly",
            month: undefined,
            year: 2026,
        });
    });

    it("shows analytics empty state when finance has no trend data", async () => {
        setPermissions([
            "payroll-menu",
            "payroll-list",
            "payroll-create",
            "payroll-edit",
            "payroll-process",
            "payroll-statistics",
        ]);
        analytics.value = {
            ...buildAnalyticsPayload(),
            periods_returned: 0,
            trends: [],
        };

        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(wrapper.find('[data-testid="payroll-analytics-empty"]').exists()).toBe(true);
        expect(wrapper.text()).toContain("No analytics data yet");
    });
});
