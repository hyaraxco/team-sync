import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const stubs = vi.hoisted(() => ({
    focusMenuTrigger: null,
}));

vi.mock("@/components/admin/Header.vue", async () => {
    const { defineComponent } = await import("vue");

    return {
        default: defineComponent({
            name: "Header",
            props: ["isOpen", "sidebarId"],
            emits: ["toggle-sidebar"],
            setup(props, { emit, expose }) {
                stubs.focusMenuTrigger = vi.fn(() => document.getElementById("stub-trigger")?.focus());
                expose({ focusMenuTrigger: stubs.focusMenuTrigger });

                return { emit };
            },
            template: `
                <header>
                    <button
                        id="stub-trigger"
                        type="button"
                        data-testid="stub-header-trigger"
                        :aria-expanded="isOpen ? 'true' : 'false'"
                        :aria-controls="sidebarId"
                        @click="emit('toggle-sidebar')"
                    >Menu</button>
                </header>
            `,
        }),
    };
});

vi.mock("@/components/admin/Sidebar.vue", async () => {
    const { defineComponent } = await import("vue");

    return {
        default: defineComponent({
            name: "Sidebar",
            template:
                '<aside id="sidebar" aria-label="Main sidebar"><button id="sidebar-inner">Close</button></aside>',
        }),
    };
});

vi.mock("@/components/common/ErrorBoundary.vue", async () => {
    const { defineComponent } = await import("vue");

    return {
        default: defineComponent({ name: "ErrorBoundary", template: "<div><slot /></div>" }),
    };
});

import AdminLayout from "@/layouts/Admin.vue";

const factory = () =>
    mount(AdminLayout, {
        attachTo: document.body,
        global: {
            stubs: {
                RouterView: true,
            },
        },
    });

const openDrawer = async (wrapper) => {
    await wrapper.get('[data-testid="stub-header-trigger"]').trigger("click");
    await nextTick();
};

const pressEscape = async () => {
    document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true }));
    await nextTick();
};

describe("Admin layout mobile sidebar", () => {
    let mediaListeners;
    let wrappers;

    beforeEach(() => {
        document.body.innerHTML = "";
        stubs.focusMenuTrigger = null;
        mediaListeners = [];
        wrappers = [];

        window.matchMedia = vi.fn(() => ({
            matches: false,
            media: "(min-width: 1024px)",
            addEventListener: (_event, handler) => mediaListeners.push(handler),
            removeEventListener: vi.fn(),
        }));
    });

    afterEach(() => {
        wrappers.forEach((wrapper) => wrapper.unmount());
        wrappers = [];
        vi.restoreAllMocks();
    });

    const mountLayout = () => {
        const wrapper = factory();
        wrappers.push(wrapper);
        return wrapper;
    };

    const resizeToDesktop = async () => {
        mediaListeners.forEach((handler) => handler({ matches: true }));
        await nextTick();
    };

    it("moves keyboard focus past the header to the main landmark", async () => {
        const wrapper = mountLayout();
        const skip = wrapper.get('a[href="#main-content"]');
        skip.element.focus();
        await skip.trigger("click");
        expect(document.activeElement).toBe(wrapper.get("main#main-content").element);
    });

    it("renders no backdrop while the drawer is closed", () => {
        const wrapper = mountLayout();

        expect(wrapper.find('[data-testid="sidebar-backdrop"]').exists()).toBe(false);
    });

    it("closes an open drawer and clears the trigger state when the viewport becomes desktop", async () => {
        const wrapper = mountLayout();

        await openDrawer(wrapper);
        expect(wrapper.find('[data-testid="sidebar-backdrop"]').exists()).toBe(true);

        await resizeToDesktop();

        expect(wrapper.find('[data-testid="sidebar-backdrop"]').exists()).toBe(false);
        expect(wrapper.get('[data-testid="stub-header-trigger"]').attributes("aria-expanded")).toBe("false");
        expect(stubs.focusMenuTrigger).not.toHaveBeenCalled();
    });

    it("shows a hidden backdrop that closes the drawer when opened", async () => {
        const wrapper = mountLayout();

        await openDrawer(wrapper);

        const backdrop = wrapper.get('[data-testid="sidebar-backdrop"]');
        expect(backdrop.attributes("aria-hidden")).toBe("true");

        await backdrop.trigger("click");
        await nextTick();

        expect(wrapper.find('[data-testid="sidebar-backdrop"]').exists()).toBe(false);
    });

    it("closes on Escape and returns focus to the menu trigger", async () => {
        const wrapper = mountLayout();

        await openDrawer(wrapper);
        document.getElementById("sidebar-inner").focus();

        await pressEscape();

        expect(wrapper.find('[data-testid="sidebar-backdrop"]').exists()).toBe(false);
        expect(document.activeElement.id).toBe("stub-trigger");
    });

    it("ignores Escape while the drawer is already closed", async () => {
        const wrapper = mountLayout();

        await pressEscape();

        expect(wrapper.find('[data-testid="sidebar-backdrop"]').exists()).toBe(false);
        expect(stubs.focusMenuTrigger).not.toHaveBeenCalled();
    });

    it("keeps the drawer closed after Escape is pressed twice", async () => {
        const wrapper = mountLayout();

        await openDrawer(wrapper);
        await pressEscape();
        await pressEscape();

        expect(wrapper.find('[data-testid="sidebar-backdrop"]').exists()).toBe(false);
        expect(document.activeElement.id).toBe("stub-trigger");
    });
});
