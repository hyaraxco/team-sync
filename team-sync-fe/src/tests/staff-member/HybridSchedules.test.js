import { mount, flushPromises } from "@vue/test-utils";
import { describe, it, expect, vi } from "vitest";
import HybridSchedules from "@/views/staff-member/HybridSchedules.vue";
import { createPinia, setActivePinia } from "pinia";
import { useHybridScheduleStore } from "@/stores/hybridSchedule";

describe("HybridSchedules.vue", () => {
    it("renders the hybrid schedule workspace", async () => {
        setActivePinia(createPinia());
        const store = useHybridScheduleStore();
        store.fetchMySchedule = vi.fn().mockResolvedValue({
            data: {
                base_schedule: {
                    monday: "office",
                    tuesday: "remote",
                    wednesday: "office",
                    thursday: "office",
                    friday: "remote",
                },
                overrides: [],
            },
        });

        const wrapper = mount(HybridSchedules, {
            global: {
                stubs: {
                    RouterLink: true,
                    Icon: true,
                },
            },
        });

        await flushPromises();

        expect(wrapper.text()).toContain("Jadwal Hybrid");
        expect(wrapper.text()).toContain("Request Override");
    });
});
