import { describe, it, expect, beforeEach } from "vitest";
import { defineComponent, h } from "vue";
import { mount } from "@vue/test-utils";
import { provideSidebar, useSidebar } from "@/composables/useSidebar";

describe("provideSidebar", () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it("returns context with isOpen, isCollapsed, and functions", () => {
        const wrapper = mount(
            defineComponent({
                setup() {
                    const ctx = provideSidebar();
                    return { ctx };
                },
                template: "<div />",
            }),
        );

        const ctx = wrapper.vm.ctx;
        expect(ctx.isOpen.value).toBe(true);
        expect(typeof ctx.isCollapsed.value).toBe("boolean");
        expect(typeof ctx.toggleCollapse).toBe("function");
        expect(typeof ctx.openMobile).toBe("function");
        expect(typeof ctx.closeMobile).toBe("function");
        expect(typeof ctx.toggleMobile).toBe("function");
    });

    it("initializes isCollapsed from localStorage", () => {
        localStorage.setItem("sidebar-collapsed", "true");

        const wrapper = mount(
            defineComponent({
                setup() {
                    const ctx = provideSidebar();
                    return { ctx };
                },
                template: "<div />",
            }),
        );

        expect(wrapper.vm.ctx.isCollapsed.value).toBe(true);
    });

    it("defaults isCollapsed to false when localStorage is empty", () => {
        const wrapper = mount(
            defineComponent({
                setup() {
                    const ctx = provideSidebar();
                    return { ctx };
                },
                template: "<div />",
            }),
        );

        expect(wrapper.vm.ctx.isCollapsed.value).toBe(false);
    });
});

describe("useSidebar", () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it("throws when used without provider", () => {
        const Consumer = defineComponent({
            setup() {
                useSidebar();
            },
            template: "<div />",
        });

        expect(() => mount(Consumer)).toThrow("useSidebar must be used within a SidebarProvider");
    });

    it("works when used within parent provider", () => {
        const Parent = defineComponent({
            setup() {
                const ctx = provideSidebar();
                return { ctx };
            },
            template: "<div><slot /></div>",
        });

        const Child = defineComponent({
            setup() {
                const sidebar = useSidebar();
                return { sidebar };
            },
            template: "<div />",
        });

        const wrapper = mount(Parent, {
            slots: {
                default: () => h(Child),
            },
        });

        expect(wrapper.vm.ctx.isOpen.value).toBe(true);
    });
});

describe("sidebar toggle functions", () => {
    beforeEach(() => {
        localStorage.clear();
    });

    function mountWithProvider() {
        const Child = defineComponent({
            setup() {
                const ctx = provideSidebar();
                return { ctx };
            },
            template: "<div />",
        });
        return mount(Child);
    }

    it("toggleCollapse toggles isCollapsed and persists to localStorage", () => {
        const wrapper = mountWithProvider();
        const ctx = wrapper.vm.ctx;

        expect(ctx.isCollapsed.value).toBe(false);

        ctx.toggleCollapse();
        expect(ctx.isCollapsed.value).toBe(true);
        expect(localStorage.getItem("sidebar-collapsed")).toBe("true");

        ctx.toggleCollapse();
        expect(ctx.isCollapsed.value).toBe(false);
        expect(localStorage.getItem("sidebar-collapsed")).toBe("false");
    });

    it("openMobile sets isOpen to true", () => {
        const wrapper = mountWithProvider();
        const ctx = wrapper.vm.ctx;

        ctx.isOpen.value = false;
        ctx.openMobile();
        expect(ctx.isOpen.value).toBe(true);
    });

    it("closeMobile sets isOpen to false", () => {
        const wrapper = mountWithProvider();
        const ctx = wrapper.vm.ctx;

        ctx.isOpen.value = true;
        ctx.closeMobile();
        expect(ctx.isOpen.value).toBe(false);
    });

    it("toggleMobile toggles isOpen", () => {
        const wrapper = mountWithProvider();
        const ctx = wrapper.vm.ctx;

        expect(ctx.isOpen.value).toBe(true);

        ctx.toggleMobile();
        expect(ctx.isOpen.value).toBe(false);

        ctx.toggleMobile();
        expect(ctx.isOpen.value).toBe(true);
    });
});
