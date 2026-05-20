import { mount, flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";
import { nextTick } from "vue";
import AttendanceSettings from "@/views/admin/attendance/AttendanceSettings.vue";
import Input from "@/components/common/form/Input.vue";
import Select from "@/components/common/form/Select.vue";

const samplePolicy = {
    id: 1,
    employment_type: "full_time",
    work_start_time: "09:00:00",
    work_end_time: "17:00:00",
    work_days_per_week: 5,
    default_working_weekdays: ["monday", "tuesday"],
    late_grace_minutes: 30,
    half_day_min_hours: 4,
    warning_absent_pct: 5.0,
};

vi.mock("@/stores/attendancePolicy", () => ({
    useAttendancePolicyStore: () => ({
        fetchPolicies: vi.fn().mockResolvedValue(),
        updatePolicy: vi.fn().mockResolvedValue(),
        policies: [samplePolicy],
        loading: false,
        error: null,
    }),
}));

vi.mock("@/stores/holidayCalendar", () => ({
    useHolidayCalendarStore: () => ({
        fetchAllPaginated: vi.fn().mockResolvedValue(),
        createHoliday: vi.fn(),
        updateHoliday: vi.fn(),
        paginatedHolidays: [],
        meta: { current_page: 1, per_page: 10 },
        loading: false,
        error: null,
    }),
}));

vi.mock("@/stores/leaveEntitlement", () => ({
    useLeaveEntitlementStore: () => ({
        fetchEntitlements: vi.fn().mockResolvedValue(),
        updateEntitlement: vi.fn(),
        groupedEntitlements: {},
        loading: false,
        error: null,
    }),
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({ error: vi.fn(), success: vi.fn() }),
}));

describe("AttendanceSettings.vue smoke", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it("renders policy edit modal with Input primitives when opened", async () => {
        const wrapper = mount(AttendanceSettings);
        await flushPromises();

        // Open policy modal
        await wrapper.find(".policy-card button").trigger("click");
        await nextTick();

        // Modal must contain Input primitives (4 number + 2 time inputs)
        const inputs = wrapper.findAllComponents(Input);
        expect(inputs.length).toBeGreaterThanOrEqual(6);

        // Every Input renders an <input> element with auto-generated id
        for (const inputComp of inputs) {
            expect(inputComp.find("input").attributes("id")).toBeTruthy();
        }
    });

    it("holiday modal uses Select primitive for type field", async () => {
        const wrapper = mount(AttendanceSettings);
        await flushPromises();

        // Switch to Holiday Calendars tab
        const tabs = wrapper.findAll("nav button");
        const holidayTab = tabs.find((btn) => btn.text().includes("Holiday Calendars"));
        await holidayTab.trigger("click");
        await nextTick();

        // Open Add Holiday modal
        const addBtn = wrapper.findAll("button").find((btn) => btn.text().includes("Add Holiday"));
        await addBtn.trigger("click");
        await nextTick();

        const selects = wrapper.findAllComponents(Select);
        expect(selects.length).toBeGreaterThanOrEqual(1);
    });
});
