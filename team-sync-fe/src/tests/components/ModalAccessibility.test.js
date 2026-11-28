import { afterEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";
import { defineComponent, nextTick, ref } from "vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import ConfirmationModal from "@/components/common/ConfirmationModal.vue";
import ErrorModal from "@/components/admin/staff-member/create/ErrorModal.vue";

const wrappers = [];
const addedElements = [];
const render = (component, options = {}) => {
    const wrapper = mount(component, { attachTo: document.body, ...options });
    wrappers.push(wrapper);
    return wrapper;
};
const addElement = (tag, attributes = {}) => {
    const element = document.createElement(tag);
    for (const [name, value] of Object.entries(attributes)) element.setAttribute(name, value);
    document.body.append(element);
    addedElements.push(element);
    return element;
};
const settle = async () => { await nextTick(); await flushPromises(); };
const press = (key, shiftKey = false) => {
    const event = new KeyboardEvent("keydown", { key, shiftKey, bubbles: true, cancelable: true });
    document.activeElement.dispatchEvent(event);
    return event;
};
const dialog = () => document.querySelector("[role='dialog']");
const overlay = () => document.querySelector("[data-modal-overlay]");

afterEach(() => {
    wrappers.reverse().forEach(wrapper => wrapper.unmount());
    wrappers.length = 0;
    addedElements.forEach(element => element.remove());
    addedElements.length = 0;
    document.body.style.overflow = "";
});

describe("shared modal accessibility", () => {
    it('layers a later-opened confirmation above a wrapper regardless of component order', async () => {
        const confirmation = render(ConfirmationModal, { props: { show: false, title: 'Delete' } });
        const parent = render(ModalWrapper, { props: { show: true, title: 'Details' } });
        await settle();
        await confirmation.setProps({ show: true });
        await settle();
        const overlays = [...document.querySelectorAll('[data-modal-overlay]')];
        const top = overlays.find(node => node.textContent.includes('Delete'));
        const lower = overlays.find(node => node.textContent.includes('Details'));
        expect(Number(top.style.zIndex)).toBeGreaterThan(Number(lower.style.zIndex));
        expect(lower.closest('[inert]')).not.toBeNull();
        expect(top.contains(document.activeElement)).toBe(true);
        press('Escape');
        expect(confirmation.emitted('cancel')).toHaveLength(1);
        expect(parent.emitted('close')).toBeUndefined();
        await confirmation.setProps({ show: false });
        expect(lower.closest('[inert]')).toBeNull();
    });

    it("focuses an initially open wrapper and restores focus, background state and scroll on close", async () => {
        const opener = addElement("button");
        const background = addElement("section", { "aria-hidden": "false" });
        const alreadyInert = addElement("section", { inert: "", "aria-hidden": "true" });
        document.body.style.overflow = "clip";
        opener.focus();
        const wrapper = render(ModalWrapper, { props: { show: true, title: "Edit" } });
        await settle();
        expect(document.activeElement.getAttribute("aria-label")).toBe("Close");
        expect(dialog().getAttribute("aria-labelledby")).toBe(document.querySelector("h3").id);
        expect(background.hasAttribute("inert")).toBe(true);
        expect(document.body.style.overflow).toBe("hidden");
        await wrapper.setProps({ show: false });
        await settle();
        expect(document.activeElement).toBe(opener);
        expect(background.hasAttribute("inert")).toBe(false);
        expect(background.getAttribute("aria-hidden")).toBe("false");
        expect(alreadyInert.hasAttribute("inert")).toBe(true);
        expect(alreadyInert.getAttribute("aria-hidden")).toBe("true");
        expect(document.body.style.overflow).toBe("clip");
    });

    it("skips disabled, hidden and negative-tabindex controls while cycling in both directions", async () => {
        render(ModalWrapper, {
            props: { show: true, labelledby: "controls-heading" },
            slots: { default: `<h2 id="controls-heading">Controls</h2>
                <button disabled>Disabled</button><input type="hidden" />
                <button hidden>Hidden</button><div style="display:none"><button>Not displayed</button></div>
                <button style="visibility:hidden">Invisible</button><button tabindex="-1">Skipped</button>
                <fieldset disabled><button>Disabled fieldset</button></fieldset>
                <button id="first">First</button><button id="last">Last</button>` },
        });
        await settle();
        const first = document.getElementById("first");
        const last = document.getElementById("last");
        expect(document.activeElement).toBe(first);
        expect(press("Tab").defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(last);
        press("Tab");
        expect(document.activeElement).toBe(first);
        press("Tab", true);
        expect(document.activeElement).toBe(last);
        first.disabled = true;
        press("Tab");
        expect(document.activeElement).toBe(last);
    });

    it("focuses the dialog itself when every control is unavailable and recovers escaped focus", async () => {
        const outside = addElement("button");
        render(ModalWrapper, {
            props: { show: true, labelledby: "empty-heading" },
            slots: { default: '<h2 id="empty-heading">Notice</h2><button disabled>Unavailable</button>' },
        });
        await settle();
        expect(document.activeElement).toBe(dialog());
        press("Tab");
        expect(document.activeElement).toBe(dialog());
        outside.focus();
        expect(document.activeElement).toBe(dialog());
    });

    it("only closes the top confirmation on Escape and returns focus to its trigger", async () => {
        const closeParent = vi.fn();
        const cancelConfirmation = vi.fn();
        render(defineComponent({
            components: { ModalWrapper, ConfirmationModal },
            setup() {
                const nested = ref(false);
                return { nested, closeParent, cancelConfirmation };
            },
            template: `<ModalWrapper :show="true" title="Task detail" @close="closeParent">
                    <button id="confirmation-trigger" @click="nested = true">Delete attachment</button>
                </ModalWrapper>
                <ConfirmationModal :show="nested" title="Delete attachment" @cancel="nested = false; cancelConfirmation()" />`,
        }));
        await settle();
        const parentOverlay = overlay();
        const trigger = document.getElementById("confirmation-trigger");
        trigger.focus();
        trigger.click();
        await settle();
        const overlays = document.querySelectorAll("[data-modal-overlay]");
        expect(overlays).toHaveLength(2);
        expect(Number(overlays[1].style.zIndex)).toBeGreaterThan(Number(overlays[0].style.zIndex));
        expect(overlays[1].parentElement).toBe(document.body);
        expect(parentOverlay.closest("[inert]")).not.toBeNull();
        expect(overlays[1].contains(document.activeElement)).toBe(true);
        press("Tab", true);
        expect(document.activeElement.textContent.trim()).toBe("Confirm");
        press("Escape");
        await settle();
        expect(cancelConfirmation).toHaveBeenCalledOnce();
        expect(closeParent).not.toHaveBeenCalled();
        expect(parentOverlay.closest("[inert]")).toBeNull();
        expect(document.activeElement).toBe(trigger);
        expect(document.body.style.overflow).toBe("hidden");
    });

    it("restores focus and scroll on unmount and falls back to the main landmark if the opener disappeared", async () => {
        const opener = addElement("button");
        const main = addElement("main");
        opener.focus();
        const wrapper = render(ConfirmationModal, { props: { show: true } });
        await settle();
        wrapper.unmount();
        wrappers.pop();
        expect(document.activeElement).toBe(opener);
        expect(document.body.style.overflow).toBe("");
        const another = render(ModalWrapper, { props: { show: true, title: "Removed trigger" } });
        await settle();
        opener.remove();
        another.unmount();
        wrappers.pop();
        expect(document.activeElement).toBe(main);
        expect(main.hasAttribute("tabindex")).toBe(false);
    });

    it("keeps notifications perceivable and includes toast dismissal in the active tab sequence", async () => {
        const notification = addElement("section", { "data-modal-notifications": "", role: "status" });
        notification.innerHTML = '<p>Could not save</p><button id="dismiss-toast">Dismiss notification</button>';
        render(ModalWrapper, { props: { show: true, title: "Save" } });
        await settle();
        expect(notification.hasAttribute("inert")).toBe(false);
        expect(notification.hasAttribute("aria-hidden")).toBe(false);
        press("Tab");
        expect(document.activeElement.id).toBe("dismiss-toast");
        press("Tab");
        expect(document.activeElement.getAttribute("aria-label")).toBe("Close");
        const dynamicBackground = addElement("button");
        await settle();
        expect(dynamicBackground.hasAttribute("inert")).toBe(true);
    });

    it("allows Close, backdrop and Escape while processing but disables footer actions", async () => {
        const wrapper = render(ConfirmationModal, { props: { show: true, loading: true } });
        await settle();
        expect(document.querySelector('[role="status"]').closest('[aria-busy="true"]')).toBeNull();
        const close = document.querySelector("button[aria-label='Close']");
        expect(document.activeElement).toBe(close);
        expect(close.disabled).toBe(false);
        expect(document.querySelector("[role='status']").textContent).toBe("Processing...");
        press("Escape");
        overlay().click();
        close.click();
        const footerButtons = Array.from(document.querySelectorAll("[role='dialog'] button")).filter(button => button !== close);
        footerButtons.forEach(button => {
            expect(button.disabled).toBe(true);
            button.click();
        });
        expect(wrapper.emitted("cancel")).toHaveLength(3);
        expect(wrapper.emitted("confirm")).toBeUndefined();
        await wrapper.setProps({ loading: false });
        footerButtons[0].click();
        expect(wrapper.emitted("cancel")).toHaveLength(4);
    });

    it("guards wrapper Escape, backdrop and close while loading", async () => {
        const wrapper = render(ModalWrapper, { props: { show: true, title: "Save", loading: true } });
        await settle();
        press("Escape");
        overlay().click();
        document.querySelector("button[aria-label='Close']").click();
        expect(wrapper.emitted("close")).toBeUndefined();
        await wrapper.setProps({ loading: false });
        press("Escape");
        expect(wrapper.emitted("close")).toHaveLength(1);
    });

    it("uses unique confirmation names and messages for equal titles", async () => {
        render(defineComponent({
            components: { ConfirmationModal },
            template: '<ConfirmationModal :show="true" title="Delete" message="First message" /><ConfirmationModal :show="true" title="Delete" message="Second message" />',
        }));
        await settle();
        const dialogs = Array.from(document.querySelectorAll("[role='dialog']"));
        const titles = dialogs.map(element => element.getAttribute("aria-labelledby"));
        const messages = dialogs.map(element => element.getAttribute("aria-describedby"));
        expect(new Set(titles).size).toBe(2);
        expect(new Set(messages).size).toBe(2);
        expect(titles.map(id => document.getElementById(id).textContent)).toEqual(["Delete", "Delete"]);
        expect(messages.map(id => document.getElementById(id).textContent)).toEqual(["First message", "Second message"]);
    });

    it("names custom wrapper headers and the existing error dialog without a duplicate heading", async () => {
        const wrapper = render(ModalWrapper, {
            props: { show: true, title: "Leader" },
            slots: { header: "<h2>Change project leader</h2><p>Choose a colleague</p>" },
        });
        await settle();
        const title = document.getElementById(dialog().getAttribute("aria-labelledby"));
        expect(title.textContent).toContain("Change project leader");
        wrapper.unmount();
        wrappers.pop();
        render(ErrorModal, { props: { show: true } });
        await settle();
        expect(document.getElementById(dialog().getAttribute("aria-labelledby")).textContent).toContain("Oops!");
        expect(document.getElementById(dialog().getAttribute("aria-describedby")).textContent).toContain("Please check your data");
        expect(document.querySelectorAll("h3")).toHaveLength(1);
    });
});
