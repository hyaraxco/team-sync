import { mount } from "@vue/test-utils";
import { describe, it, expect } from "vitest";
import Select from "@/components/common/form/Select.vue";

describe("Select.vue smoke", () => {
    it("positions the chevron inside the control and preserves label and error behavior", () => {
        const wrapper = mount(Select, {
            props: { label: "Department", name: "department", error: "Choose a department" },
        });
        const control = wrapper.find("select").element.parentElement;

        expect(control.classList.contains("relative")).toBe(true);
        expect(control.querySelector("select")).toBe(wrapper.find("select").element);
        expect(control.querySelector(".absolute")).toBeTruthy();
        expect(wrapper.find("label").attributes("for")).toBe("department");
        expect(wrapper.find("select").attributes("aria-invalid")).toBe("true");
        expect(wrapper.find("select").attributes("aria-describedby")).toBe("department-error");
        expect(wrapper.find("[role=alert]").text()).toBe("Choose a department");
    });
});
