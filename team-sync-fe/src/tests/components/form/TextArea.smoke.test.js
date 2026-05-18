import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import TextArea from "@/components/common/form/TextArea.vue";

describe("TextArea.vue", () => {
    it("does not use inline styles for label", () => {
        const wrapper = mount(TextArea, {
            props: { label: "Description" },
        });

        const label = wrapper.find("label");
        expect(label.attributes("style")).toBeUndefined();
    });

    it("does not use inline styles for textarea", () => {
        const wrapper = mount(TextArea, {
            props: { label: "Description" },
        });

        const textarea = wrapper.find("textarea");
        expect(textarea.attributes("style")).toBeUndefined();
    });

    it("does not use inline styles for error message", async () => {
        const wrapper = mount(TextArea, {
            props: { label: "Description", error: "Required field" },
        });

        const errorEl = wrapper.find("p[role='alert']");
        expect(errorEl.attributes("style")).toBeUndefined();
    });

    it("applies Tailwind text classes on label", () => {
        const wrapper = mount(TextArea, {
            props: { label: "Description" },
        });

        const label = wrapper.find("label");
        expect(label.classes()).toContain("text-sm");
        expect(label.classes()).toContain("font-semibold");
        expect(label.classes()).toContain("text-gray-600");
    });

    it("applies Tailwind bg-white on textarea", () => {
        const wrapper = mount(TextArea, {
            props: { label: "Description" },
        });

        const textarea = wrapper.find("textarea");
        expect(textarea.classes()).toContain("bg-white");
    });

    it("applies Tailwind text classes on error message", async () => {
        const wrapper = mount(TextArea, {
            props: { label: "Description", error: "Required field" },
        });

        const errorEl = wrapper.find("p[role='alert']");
        expect(errorEl.classes()).toContain("text-red-600");
        expect(errorEl.classes()).toContain("text-sm");
        expect(errorEl.classes()).toContain("font-normal");
    });
});
