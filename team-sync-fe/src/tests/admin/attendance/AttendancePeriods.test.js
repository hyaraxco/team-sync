import { mount } from "@vue/test-utils";
import { describe, it, expect, vi } from "vitest";
import AttendancePeriods from "@/views/admin/attendance/AttendancePeriods.vue";
import { createPinia, setActivePinia } from "pinia";

vi.mock("vue-router", () => ({
    useRouter: () => ({ push: vi.fn() }),
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({ error: vi.fn(), success: vi.fn() }),
}));

describe("AttendancePeriods.vue", () => {
    it("renders the header and table", () => {
        setActivePinia(createPinia());

        const wrapper = mount(AttendancePeriods, {
            global: {
                stubs: {
                    RouterLink: true,
                    Icon: true,
                },
            },
        });

        expect(wrapper.text()).toContain("Monitor period statuses");
        expect(wrapper.text()).toContain("Readiness");
    });

    it("does not render a local h1 because page title comes from layout header", () => {
        setActivePinia(createPinia());

        const wrapper = mount(AttendancePeriods);

        expect(wrapper.find("h1").exists()).toBe(false);
        expect(wrapper.text()).toContain("Monitor period statuses");
    });

    it("renders period workspace inside tokenized surface shells", () => {
        setActivePinia(createPinia());

        const wrapper = mount(AttendancePeriods);
        const shells = wrapper.findAll(".rounded-2xl.border.border-brand-border.p-6.shadow-sm");

        expect(shells.length).toBeGreaterThan(0);
        expect(shells.some((shell) => shell.attributes("style")?.includes("var(--color-surface)"))).toBe(true);
    });
});
