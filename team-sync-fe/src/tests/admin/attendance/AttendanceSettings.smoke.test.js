import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import AttendanceSettings from "@/views/admin/attendance/AttendanceSettings.vue";
import { createPinia, setActivePinia } from "pinia";
import Input from "@/components/common/form/Input.vue";
import Select from "@/components/common/form/Select.vue";

vi.mock("@/stores/attendancePolicy", () => ({
    useAttendancePolicyStore: vi.fn(() => ({
        policies: [samplePolicy],
        loading: false,
        error: null,
        fetchPolicies: vi.fn().mockResolvedValue(),
        updatePolicy: vi.fn().mockResolvedValue({}),
    })),
}));

vi.mock("@/stores/holidayCalendar", () => ({
    useHolidayCalendarStore: vi.fn(() => ({
        paginatedHolidays: [],
        loading: false,
        error: null,
        meta: {},
        fetchAllPaginated: vi.fn().mockResolvedValue(),
        createHoliday: vi.fn().mockResolvedValue({}),
        updateHoliday: vi.fn().mockResolvedValue({}),
    })),
}));

const mockUpdateEntitlement = vi.fn().mockResolvedValue({});

vi.mock("@/stores/leaveEntitlement", () => ({
    useLeaveEntitlementStore: vi.fn(() => ({
        groupedEntitlements: {},
        loading: false,
        error: null,
        fetchEntitlements: vi.fn().mockResolvedValue(),
        updateEntitlement: mockUpdateEntitlement,
    })),
}));

vi.mock("@/composables/useToast", () => ({
    useToast: vi.fn(() => ({
        success: vi.fn(),
        error: vi.fn(),
    })),
}));

const samplePolicy = {
    id: 1,
    employment_type: "full_time",
    work_start_time: "09:00:00",
    work_end_time: "17:00:00",
    work_days_per_week: 5,
    late_grace_minutes: 30,
    half_day_min_hours: 4,
    warning_absent_pct: 50,
    default_working_weekdays: ["monday", "tuesday", "wednesday", "thursday", "friday"],
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

describe("AttendanceSettings.vue — common primitives migration", () => {
    let wrapper;

    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it("uses Input and Select components in the Policy modal", async () => {
        // Re-mock with a policy to open
        const { useAttendancePolicyStore } = await import("@/stores/attendancePolicy");
        useAttendancePolicyStore.mockReturnValue({
            policies: [samplePolicy],
            loading: false,
            error: null,
            fetchPolicies: vi.fn().mockResolvedValue(),
            updatePolicy: vi.fn().mockResolvedValue({}),
        });

        wrapper = mount(AttendanceSettings, {
            global: {
                stubs: {
                    ModalWrapper: false,
                },
            },
        });

        await flushPromises();

        // Open the policy modal by clicking Edit Policy button
        const editBtn = wrapper.find(".policy-card button");
        expect(editBtn.exists()).toBe(true);
        await editBtn.trigger("click");
        await flushPromises();

        // Policy modal has 4 number inputs (work_days, late_grace, half_day, warning_absent)
        // time inputs stay native (type="time" not supported by Input component)
        const inputs = wrapper.findAllComponents(Input);
        expect(inputs.length).toBeGreaterThanOrEqual(4);
    });

    it("uses Input and Select components in the Entitlement modal", async () => {
        const { useAttendancePolicyStore } = await import("@/stores/attendancePolicy");
        const { useLeaveEntitlementStore } = await import("@/stores/leaveEntitlement");

        useAttendancePolicyStore.mockReturnValue({
            policies: [samplePolicy],
            loading: false,
            error: null,
            fetchPolicies: vi.fn().mockResolvedValue(),
            updatePolicy: vi.fn().mockResolvedValue({}),
        });

        useLeaveEntitlementStore.mockReturnValue({
            groupedEntitlements: {
                annual_leave: [sampleEntitlement],
            },
            loading: false,
            error: null,
            fetchEntitlements: vi.fn().mockResolvedValue(),
            updateEntitlement: vi.fn().mockResolvedValue({}),
        });

        wrapper = mount(AttendanceSettings, {
            global: {
                stubs: {
                    ModalWrapper: false,
                },
            },
        });

        await flushPromises();

        // Switch to Leave Entitlements tab
        const tabs = wrapper.findAll("nav button");
        await tabs[1].trigger("click");
        await flushPromises();

        // Open entitlement modal
        const editBtn = wrapper.find(".policy-card button");
        expect(editBtn.exists()).toBe(true);
        await editBtn.trigger("click");
        await flushPromises();

        // Entitlement modal: 1 Select (quota_scope) + 3 number Inputs (quota_days, carry_over, max_attachment) + 1 text Input (MIME types)
        const selects = wrapper.findAllComponents(Select);
        expect(selects.length).toBeGreaterThanOrEqual(1);

        const inputs = wrapper.findAllComponents(Input);
        expect(inputs.length).toBeGreaterThanOrEqual(4);
    });

    it("preserves null on submit for nullable entitlement numeric fields", async () => {
        const { useAttendancePolicyStore } = await import("@/stores/attendancePolicy");
        const { useLeaveEntitlementStore } = await import("@/stores/leaveEntitlement");

        useAttendancePolicyStore.mockReturnValue({
            policies: [samplePolicy],
            loading: false,
            error: null,
            fetchPolicies: vi.fn().mockResolvedValue(),
            updatePolicy: vi.fn().mockResolvedValue({}),
        });

        const nullEntitlement = {
            ...sampleEntitlement,
            quota_days: null,
            carry_over_max_days: null,
            max_attachment_size_kb: null,
        };

        useLeaveEntitlementStore.mockReturnValue({
            groupedEntitlements: {
                annual_leave: [nullEntitlement],
            },
            loading: false,
            error: null,
            fetchEntitlements: vi.fn().mockResolvedValue(),
            updateEntitlement: mockUpdateEntitlement,
        });

        mockUpdateEntitlement.mockClear();

        wrapper = mount(AttendanceSettings, {
            global: {
                stubs: { ModalWrapper: false },
            },
        });

        await flushPromises();

        // Switch to Leave Entitlements tab
        const tabs = wrapper.findAll("nav button");
        await tabs[1].trigger("click");
        await flushPromises();

        // Open entitlement modal
        const editBtn = wrapper.find(".policy-card button");
        await editBtn.trigger("click");
        await flushPromises();

        // Submit without editing — call submit method directly on component
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
