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

const sampleEntitlement = {
    id: 10,
    leave_type: "annual_leave",
    employment_type: "full_time",
    is_eligible: true,
    is_paid: true,
    quota_scope: "annual",
    quota_days: 12,
    carry_over_max_days: 5,
    requires_attachment: false,
    requires_reason: false,
    allowed_mime_types: [],
    max_attachment_size_kb: null,
};

const mockUpdateEntitlement = vi.fn().mockResolvedValue({});

vi.mock("@/stores/attendancePolicy", () => ({
    useAttendancePolicyStore: vi.fn(() => ({
        fetchPolicies: vi.fn().mockResolvedValue(),
        updatePolicy: vi.fn().mockResolvedValue({}),
        policies: [samplePolicy],
        loading: false,
        error: null,
    })),
}));

vi.mock("@/stores/holidayCalendar", () => ({
    useHolidayCalendarStore: vi.fn(() => ({
        fetchAllPaginated: vi.fn().mockResolvedValue(),
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
        fetchEntitlements: vi.fn().mockResolvedValue(),
        updateEntitlement: mockUpdateEntitlement,
        groupedEntitlements: {},
        loading: false,
        error: null,
    })),
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

    it("renders settings shell with tokenized surface and no local h1", async () => {
        const wrapper = mount(AttendanceSettings);
        await flushPromises();

        expect(wrapper.find("h1").exists()).toBe(false);
        const shell = wrapper.find("section.rounded-2xl.border.border-brand-border.p-6.shadow-sm");
        expect(shell.exists()).toBe(true);
        expect(shell.classes()).toContain("bg-[var(--color-surface)]");
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

    it("preserves null on submit for nullable entitlement numeric fields", async () => {
        const { useLeaveEntitlementStore } = await import("@/stores/leaveEntitlement");

        const nullEntitlement = {
            ...sampleEntitlement,
            quota_days: null,
            carry_over_max_days: null,
            max_attachment_size_kb: null,
        };

        useLeaveEntitlementStore.mockReturnValue({
            fetchEntitlements: vi.fn().mockResolvedValue(),
            updateEntitlement: mockUpdateEntitlement,
            groupedEntitlements: {
                annual_leave: [nullEntitlement],
            },
            loading: false,
            error: null,
        });

        mockUpdateEntitlement.mockClear();

        const wrapper = mount(AttendanceSettings, {
            global: { stubs: { ModalWrapper: false } },
        });
        await flushPromises();

        // Switch to Leave Entitlements tab
        const tabs = wrapper.findAll("nav button");
        const entitlementTab = tabs.find((btn) => btn.text().includes("Leave Entitlements"));
        await entitlementTab.trigger("click");
        await flushPromises();

        // Open entitlement modal
        const editBtn = wrapper.find(".policy-card button");
        await editBtn.trigger("click");
        await flushPromises();

        // Submit
        await wrapper.vm.submitEntitlementForm();
        await flushPromises();

        // Assert updateEntitlement was called with null, not 0
        expect(mockUpdateEntitlement).toHaveBeenCalled();
        const callPayload = mockUpdateEntitlement.mock.calls[0][1];
        expect(callPayload.quota_days).toBeNull();
        expect(callPayload.carry_over_max_days).toBeNull();
        expect(callPayload.max_attachment_size_kb).toBeNull();
    });
});
