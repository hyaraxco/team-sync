import { mount, flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";
import PolicyMismatches from "@/views/admin/attendance/PolicyMismatches.vue";

vi.mock("@/stores/attendance", () => ({
    useAttendanceStore: () => ({
        fetchPolicyMismatches: vi.fn().mockRejectedValue(new Error("Network error")),
        acknowledgePolicyMismatch: vi.fn(),
        resolvePolicyMismatch: vi.fn(),
        error: "Network error",
    }),
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({ error: vi.fn(), success: vi.fn() }),
}));

describe("PolicyMismatches.vue smoke", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it("renders user-friendly error copy without dev language", async () => {
        const wrapper = mount(PolicyMismatches);
        await flushPromises();

        const text = wrapper.text();
        expect(text).not.toContain("under construction");
        expect(text).not.toContain("API endpoint");
        expect(text).toContain("Unable to load policy mismatches");
    });
});
