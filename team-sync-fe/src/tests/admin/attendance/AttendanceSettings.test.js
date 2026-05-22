import { mount } from "@vue/test-utils";
import { describe, it, expect, vi } from "vitest";
import AttendanceSettings from "@/views/admin/attendance/AttendanceSettings.vue";
import { createPinia, setActivePinia } from "pinia";

vi.mock("@/stores/attendancePolicy", () => ({
    useAttendancePolicyStore: vi.fn(() => ({
        fetchPolicies: vi.fn().mockResolvedValue(undefined),
        updatePolicy: vi.fn().mockResolvedValue({}),
        policies: [],
        loading: false,
        error: null,
    })),
}));

vi.mock("@/stores/holidayCalendar", () => ({
    useHolidayCalendarStore: vi.fn(() => ({
        fetchAllPaginated: vi.fn().mockResolvedValue(undefined),
        createHoliday: vi.fn(),
        updateHoliday: vi.fn(),
        paginatedHolidays: [],
        meta: { current_page: 1, per_page: 10 },
        loading: false,
        error: null,
    })),
}));

vi.mock("@/stores/leaveEntitlement", () => ({
    useLeaveEntitlementStore: vi.fn(() => ({
        fetchEntitlements: vi.fn().mockResolvedValue(undefined),
        updateEntitlement: vi.fn().mockResolvedValue({}),
        groupedEntitlements: {},
        loading: false,
        error: null,
    })),
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({ error: vi.fn(), success: vi.fn() }),
}));

describe("AttendanceSettings.vue", () => {
    it("renders the header and tabs", () => {
        setActivePinia(createPinia());

        const wrapper = mount(AttendanceSettings, {
            global: {
                stubs: {
                    RouterLink: true,
                    Icon: true,
                },
            },
        });

        expect(wrapper.text()).toContain("Attendance Policies");
        expect(wrapper.text()).toContain("Holiday Calendars");
    });

    it("does not render a local h1 because page title comes from layout header", () => {
        setActivePinia(createPinia());

        const wrapper = mount(AttendanceSettings);

        expect(wrapper.find("h1").exists()).toBe(false);
        expect(wrapper.text()).toContain("Configure global attendance rules");
    });

    it("renders settings content inside tokenized surface shells", () => {
        setActivePinia(createPinia());

        const wrapper = mount(AttendanceSettings);
        const shells = wrapper.findAll(".rounded-2xl.border.border-brand-border.p-6.shadow-sm");

        expect(shells.length).toBeGreaterThan(0);
        expect(shells.some((shell) => shell.attributes("style")?.includes("var(--color-surface)"))).toBe(true);
    });
});
