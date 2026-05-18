import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import AnimatedValue from "@/components/common/AnimatedValue.vue";

describe("AnimatedValue.vue", () => {
    it("applies tabular-nums class on the span for numeric stability", () => {
        const wrapper = mount(AnimatedValue, {
            props: { value: 1000 },
        });

        const span = wrapper.find("span");
        expect(span.classes()).toContain("tabular-nums");
    });

    it("renders prefix and suffix with the value", () => {
        const wrapper = mount(AnimatedValue, {
            props: { value: 42, prefix: "IDR ", suffix: "%" },
        });

        const span = wrapper.find("span");
        expect(span.text()).toContain("IDR");
        expect(span.text()).toContain("%");
    });
});
