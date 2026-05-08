import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const { authStoreMock } = vi.hoisted(() => ({
    authStoreMock: {
        user: null,
    },
}));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => authStoreMock,
}));

vi.mock("@/helpers/permissionHelper", () => ({
    can: (permission) => {
        return authStoreMock.user?.permissions?.includes(permission) ?? false;
    },
}));

import Dashboard from "@/views/admin/Dashboard.vue";

const factory = () =>
    mount(Dashboard, {
        global: {
            stubs: {
                Statistics: { template: '<div class="statistics-stub"></div>' },
                EmployeeStatistics: {
                    template: '<div class="employee-statistics-stub"></div>',
                },
                SearchSection: {
                    template: '<div class="search-section-stub"></div>',
                },
                LatestEmployees: {
                    template: '<div class="latest-employees-stub"></div>',
                },
                LatestTeams: { template: '<div class="latest-teams-stub"></div>' },
                TeamPulseOverview: { template: '<div class="team-pulse-overview-stub"></div>' },
                TodayAttendanceOverview: {
                    template: '<div class="today-attendance-overview-stub"></div>',
                },
                PayrollAnalyticsEnhanced: {
                    template: '<div class="payroll-analytics-enhanced-stub"></div>',
                },
                UpcomingMeetings: {
                    template: '<div class="upcoming-meetings-stub"></div>',
                },
            },
        },
    });

describe("Dashboard smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        authStoreMock.user = {
            roles: ["hr"],
            permissions: ["dashboard-menu", "dashboard-view", "dashboard-hr-view", "review-manager-submit"],
        };
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("renders HR/admin dashboard widget container with dashboard-hr-view", () => {
        const wrapper = factory();

        expect(wrapper.find(".statistics-stub").exists()).toBe(true);
        expect(wrapper.find(".search-section-stub").exists()).toBe(true);
        expect(wrapper.find(".latest-employees-stub").exists()).toBe(true);
        expect(wrapper.find(".latest-teams-stub").exists()).toBe(true);
        expect(wrapper.find(".today-attendance-overview-stub").exists()).toBe(true);
    });

    it("renders team pulse for manager with dashboard-team-view", () => {
        authStoreMock.user = {
            roles: ["manager"],
            permissions: ["dashboard-menu", "dashboard-view", "dashboard-team-view", "review-manager-submit"],
        };

        const wrapper = factory();

        expect(wrapper.find(".team-pulse-overview-stub").exists()).toBe(true);
        expect(wrapper.find(".employee-statistics-stub").exists()).toBe(true);
        // Manager should NOT see company-wide stats
        expect(wrapper.find(".statistics-stub").exists()).toBe(false);
        expect(wrapper.find(".latest-employees-stub").exists()).toBe(false);
    });

    it("renders staff dashboard with only employee statistics (dashboard-self-view)", () => {
        authStoreMock.user = {
            roles: ["staff"],
            permissions: ["dashboard-menu", "dashboard-view", "dashboard-self-view"],
        };

        const wrapper = factory();
        expect(wrapper.find(".employee-statistics-stub").exists()).toBe(true);
        // Staff should NOT see company-wide stats or search
        expect(wrapper.find(".search-section-stub").exists()).toBe(false);
        expect(wrapper.find(".statistics-stub").exists()).toBe(false);
    });

    it("renders finance dashboard analytics with dashboard-finance-view", () => {
        authStoreMock.user = {
            roles: ["finance"],
            permissions: ["dashboard-menu", "dashboard-view", "dashboard-finance-view"],
        };

        const wrapper = factory();
        expect(wrapper.find(".payroll-analytics-enhanced-stub").exists()).toBe(true);
        expect(wrapper.find(".statistics-stub").exists()).toBe(false);
    });
});
