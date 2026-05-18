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
});
