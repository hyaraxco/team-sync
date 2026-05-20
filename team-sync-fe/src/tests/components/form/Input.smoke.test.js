import { mount } from "@vue/test-utils";
import { describe, it, expect } from "vitest";
import Input from "@/components/common/form/Input.vue";

describe("Input.vue smoke", () => {
    it("uses rounded-2xl to match Select and TextArea", () => {
        const wrapper = mount(Input, {
            props: { label: "Test" },
        });
        const input = wrapper.find("input");
        expect(input.classes()).toContain("rounded-2xl");
        expect(input.classes()).not.toContain("rounded-xl");
    });

    it("forwards max prop to native input", () => {
        const wrapper = mount(Input, {
            props: { label: "Score", type: "number", max: 100 },
        });
        expect(wrapper.find("input").attributes("max")).toBe("100");
    });
});
