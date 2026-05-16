import { mount, flushPromises } from "@vue/test-utils";
import { describe, it, expect, vi } from "vitest";
import PolicyMismatches from "@/views/admin/attendance/PolicyMismatches.vue";
import { createPinia, setActivePinia } from "pinia";
import { useAttendanceStore } from "@/stores/attendance";

describe("PolicyMismatches.vue", () => {
    it("renders the header and mismatches table", async () => {
        setActivePinia(createPinia());
        const store = useAttendanceStore();
        store.fetchPolicyMismatches = vi.fn().mockResolvedValue({
            data: [
                {
                    id: 1,
                    employee_name: "Ahmad Fauzi",
                    date: "2026-04-20",
                    scheduled_location: "Remote",
                    actual_location: "Office",
                },
            ],
        });

        const wrapper = mount(PolicyMismatches, {
            global: {
                stubs: {
                    RouterLink: true,
                    Icon: true,
                },
            },
        });

        await flushPromises();

        expect(wrapper.text()).toContain("Policy Mismatches");
        expect(wrapper.text()).toContain("Acknowledge");
        expect(wrapper.text()).toContain("Resolve");
        expect(wrapper.text()).toContain("Ahmad Fauzi");
    });
});
