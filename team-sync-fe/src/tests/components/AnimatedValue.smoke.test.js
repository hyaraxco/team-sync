import { mount } from "@vue/test-utils";
import { describe, it, expect } from "vitest";
import AnimatedValue from "@/components/common/AnimatedValue.vue";

describe("AnimatedValue.vue smoke", () => {
    it("applies tabular-nums for stable digit width", () => {
        const wrapper = mount(AnimatedValue, { props: { value: 1000 } });
        expect(wrapper.find("span").classes()).toContain("tabular-nums");
    });
});
