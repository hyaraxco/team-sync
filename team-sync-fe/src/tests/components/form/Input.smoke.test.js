import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import Input from "@/components/common/form/Input.vue";

describe("Input.vue", () => {
    it("uses rounded-2xl border radius on the input element", () => {
        const wrapper = mount(Input, {
            props: { label: "Test Input" },
        });

        const input = wrapper.find("input");
        expect(input.classes()).toContain("rounded-2xl");
    });

    it("forwards max prop to the native input element", () => {
        const wrapper = mount(Input, {
            props: { label: "Work Days", type: "number", max: 7 },
        });

        const input = wrapper.find("input");
        expect(input.attributes("max")).toBe("7");
    });
});
