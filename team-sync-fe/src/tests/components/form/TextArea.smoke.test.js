import { mount } from "@vue/test-utils";
import { describe, it, expect } from "vitest";
import TextArea from "@/components/common/form/TextArea.vue";

describe("TextArea.vue smoke", () => {
    it("label uses Tailwind tokens not inline color style", () => {
        const wrapper = mount(TextArea, { props: { label: "Notes" } });
        const label = wrapper.find("label");
        expect(label.attributes("style")).toBeFalsy();
        expect(label.classes()).toContain("text-gray-600");
        expect(label.classes()).toContain("text-sm");
        expect(label.classes()).toContain("font-semibold");
    });

    it("textarea uses Tailwind bg-white not inline background", () => {
        const wrapper = mount(TextArea, { props: { label: "Notes" } });
        const textarea = wrapper.find("textarea");
        expect(textarea.attributes("style")).toBeFalsy();
        expect(textarea.classes()).toContain("bg-white");
    });

    it("error message uses text-red-600 token", async () => {
        const wrapper = mount(TextArea, {
            props: { label: "Notes", error: "Required field" },
        });
        const error = wrapper.find('[role="alert"]');
        expect(error.attributes("style")).toBeFalsy();
        expect(error.classes()).toContain("text-red-600");
        expect(error.classes()).toContain("text-sm");
    });
});
