import { mount } from "@vue/test-utils";
import { describe, it, expect } from "vitest";
import AttendanceSettings from "@/views/admin/attendance/AttendanceSettings.vue";
import { createPinia, setActivePinia } from "pinia";

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

        expect(wrapper.text()).toContain("System Configuration");
        expect(wrapper.text()).toContain("Attendance Policies");
        expect(wrapper.text()).toContain("Holiday Calendars");
    });
});
