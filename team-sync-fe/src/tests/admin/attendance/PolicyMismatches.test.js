import { mount, flushPromises } from "@vue/test-utils";
import { describe, it, expect, vi } from "vitest";
import PolicyMismatches from "@/views/admin/attendance/PolicyMismatches.vue";
import { createPinia, setActivePinia } from "pinia";
import { useAttendanceStore } from "@/stores/attendance";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

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
                    RouterLink: { template: "<a><slot /></a>" },
                    Icon: { template: "<span />" },
                    SearchFilter: { name: "SearchFilter", template: '<div data-test="search-filter"></div>' },
                },
            },
        });

        await flushPromises();

        expect(wrapper.text()).toContain("Policy Mismatches");
        expect(wrapper.text()).toContain("Acknowledge");
        expect(wrapper.text()).toContain("Resolve");
        expect(wrapper.text()).toContain("Ahmad Fauzi");
        expect(wrapper.find('[data-test="search-filter"]').exists()).toBe(true);
        const pageHeading = wrapper.find('[role="heading"][aria-level="1"]');
        expect(pageHeading.exists()).toBe(true);
        expect(pageHeading.text()).toBe("Policy Mismatches");
        expect(pageHeading.classes()).toContain("sr-only");
        expect(wrapper.find("h1").exists()).toBe(false);
    });

    it("uses shared EmptyState instead of inline empty markup", async () => {
        setActivePinia(createPinia());
        const store = useAttendanceStore();
        store.fetchPolicyMismatches = vi.fn().mockResolvedValue({ data: [] });

        const wrapper = mount(PolicyMismatches, {
            global: {
                stubs: {
                    EmptyState: { template: '<div data-test="empty-state">EmptyState</div>' },
                    SearchFilter: { name: "SearchFilter", template: '<div data-test="search-filter"></div>' },
                },
            },
        });

        await flushPromises();

        expect(wrapper.find('[role="heading"][aria-level="1"]').text()).toBe("Policy Mismatches");
        expect(wrapper.find('[role="heading"][aria-level="1"]').classes()).toContain("sr-only");
        expect(wrapper.find("h1").exists()).toBe(false);
        expect(wrapper.find('[data-test="search-filter"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="empty-state"]').exists()).toBe(true);
        expect(wrapper.find(".text-center > svg").exists()).toBe(false);
        expect(wrapper.text()).toContain("EmptyState");
    });

    it("uses tokenized surface shells instead of bg-white", () => {
        const source = readFileSync(
            resolve(process.cwd(), "src/views/admin/attendance/PolicyMismatches.vue"),
            "utf8",
        );

        expect(source).toContain("var(--color-surface)");
        expect(source).not.toContain("bg-white");
    });
});
