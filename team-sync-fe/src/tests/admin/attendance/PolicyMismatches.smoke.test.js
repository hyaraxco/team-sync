import { describe, it, expect, vi } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import PolicyMismatches from "@/views/admin/attendance/PolicyMismatches.vue";
import { createPinia, setActivePinia } from "pinia";
import { useAttendanceStore } from "@/stores/attendance";
import { useToast } from "@/composables/useToast";

vi.mock("@/composables/useToast", () => ({
    useToast: vi.fn(() => ({
        error: vi.fn(),
        success: vi.fn(),
    })),
}));

describe("PolicyMismatches.vue — error copy", () => {
    it("shows user-friendly error message when fetch fails", async () => {
        setActivePinia(createPinia());
        const store = useAttendanceStore();
        store.fetchPolicyMismatches = vi.fn().mockRejectedValue(new Error("Network error"));

        const wrapper = mount(PolicyMismatches, {
            global: {
                stubs: {
                    RouterLink: true,
                },
            },
        });

        await flushPromises();

        expect(wrapper.text()).toContain("Unable to load policy mismatches. Please try again later.");
        expect(wrapper.text()).not.toContain("API endpoint might be missing");
    });
});
