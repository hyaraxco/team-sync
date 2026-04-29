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
            roles: ["admin"],
            permissions: [{ name: "dashboard-view" }],
        };
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("renders admin dashboard widget container by default", () => {
        const wrapper = factory();

        expect(wrapper.find(".statistics-stub").exists()).toBe(true);
        expect(wrapper.find(".search-section-stub").exists()).toBe(true);
        expect(wrapper.find(".latest-employees-stub").exists()).toBe(true);
        expect(wrapper.find(".latest-teams-stub").exists()).toBe(true);
        expect(wrapper.find(".today-attendance-overview-stub").exists()).toBe(true);
    });

    it("renders staff dashboard widgets for staff role", () => {
        authStoreMock.user = {
            roles: ["staff"],
            permissions: [{ name: "dashboard-view" }],
        };

        const wrapper = factory();
        expect(wrapper.find(".employee-statistics-stub").exists()).toBe(true);
        expect(wrapper.find(".search-section-stub").exists()).toBe(true);
        expect(wrapper.find(".statistics-stub").exists()).toBe(false);
    });

    it("renders finance dashboard analytics for finance role", () => {
        authStoreMock.user = {
            roles: ["finance"],
            permissions: [],
        };

        const wrapper = factory();
        expect(wrapper.find(".payroll-analytics-enhanced-stub").exists()).toBe(true);
        expect(wrapper.find(".statistics-stub").exists()).toBe(false);
    });
});
