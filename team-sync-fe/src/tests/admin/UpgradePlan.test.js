import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import UpgradePlan from "@/views/admin/UpgradePlan.vue";

describe("UpgradePlan", () => {
    it("states license terms without presenting unsupported billing or purchase options", () => {
        const wrapper = mount(UpgradePlan);

        expect(wrapper.text()).toContain("license keys to enable features and set the user limit");
        expect(wrapper.text()).toContain("No public plans, billing terms, or purchase route");
        expect(wrapper.text()).toContain("Contact your Team Sync administrator");
        expect(wrapper.findAll("button")).toHaveLength(0);
        expect(wrapper.text()).not.toMatch(/billed yearly|billed monthly|recommended|save \d+%|trial/i);
    });
});
