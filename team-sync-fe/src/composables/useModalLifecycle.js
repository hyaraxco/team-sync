import { nextTick, onUnmounted, ref, watch } from "vue";

const stack = [];
const restoredElements = new Map();
let observer;
let previousOverflow;

const notificationRoots = () => Array.from(document.querySelectorAll("[data-modal-notifications]"));
const topModal = () => stack[stack.length - 1];
const allowedRoots = () => topModal() ? [topModal().element, ...notificationRoots()] : [];

function isAvailable(element) {
    if (!element.isConnected || element.matches(":disabled, input[type='hidden']")) return false;
    for (let node = element; node instanceof HTMLElement; node = node.parentElement) {
        const style = getComputedStyle(node);
        if (node.hidden || node.hasAttribute("inert") || node.getAttribute("aria-hidden") === "true" ||
            style.display === "none" || style.visibility === "hidden" || style.visibility === "collapse") return false;
        if (node instanceof HTMLDetailsElement && !node.open && !node.querySelector("summary")?.contains(element)) return false;
    }
    return true;
}

function focusableElements(roots) {
    return roots.flatMap(root => Array.from(root.querySelectorAll(
        "button, a[href], area[href], input, select, textarea, [tabindex], [contenteditable='true'], summary, audio[controls], video[controls]",
    ))).filter(element => element.tabIndex >= 0 && isAvailable(element));
}

function focusModal(modal) {
    const targets = focusableElements([modal.element]);
    const target = targets.find(element => element.hasAttribute("autofocus")) || targets[0] || modal.element.querySelector("[role='dialog']");
    target.focus({ preventScroll: true });
}

function restoreElement(element, state) {
    for (const [name, value] of Object.entries(state)) {
        if (value === null) element.removeAttribute(name);
        else element.setAttribute(name, value);
    }
}

function syncBackground() {
    const roots = allowedRoots();
    const blocked = new Set();
    function visit(parent) {
        for (const child of parent.children) {
            if (roots.includes(child)) continue;
            if (roots.some(root => child.contains(root))) visit(child);
            else blocked.add(child);
        }
    }
    if (roots.length) visit(document.body);
    for (const [element, state] of restoredElements) {
        if (!blocked.has(element)) {
            restoreElement(element, state);
            restoredElements.delete(element);
        }
    }
    for (const element of blocked) {
        if (!restoredElements.has(element)) {
            restoredElements.set(element, {
                inert: element.getAttribute("inert"),
                "aria-hidden": element.getAttribute("aria-hidden"),
            });
            element.setAttribute("inert", "");
            element.setAttribute("aria-hidden", "true");
        }
    }
    stack.forEach((modal, index) => { modal.layer.value = 10000 + index; });
}

function handleKeydown(event) {
    const modal = topModal();
    if (!modal) return;
    if (event.key === "Escape") {
        event.preventDefault();
        event.stopImmediatePropagation();
        modal.dismiss();
    } else if (event.key === "Tab") {
        const targets = focusableElements(allowedRoots());
        const index = targets.indexOf(document.activeElement);
        if (!targets.length) {
            event.preventDefault();
            modal.element.querySelector("[role='dialog']").focus({ preventScroll: true });
        } else {
            event.preventDefault();
            const next = index === -1 ? (event.shiftKey ? targets.length - 1 : 0) :
                (index + (event.shiftKey ? -1 : 1) + targets.length) % targets.length;
            targets[next].focus();
        }
    }
}

function handleFocusin(event) {
    const modal = topModal();
    if (modal && !allowedRoots().some(root => root.contains(event.target))) focusModal(modal);
}

function activate(modal) {
    if (!stack.length) {
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        document.addEventListener("keydown", handleKeydown);
        document.addEventListener("focusin", handleFocusin);
        observer = new MutationObserver(syncBackground);
        observer.observe(document.body, { childList: true, subtree: true });
    }
    stack.push(modal);
    modal.element.removeAttribute("inert");
    modal.element.removeAttribute("aria-hidden");
    syncBackground();
    focusModal(modal);
}

function deactivate(modal) {
    const index = stack.indexOf(modal);
    if (index === -1) return;
    const wasTop = modal === topModal();
    stack.splice(index, 1);
    syncBackground();
    modal.element.setAttribute("inert", "");
    modal.element.setAttribute("aria-hidden", "true");
    if (!stack.length) {
        observer.disconnect();
        document.removeEventListener("keydown", handleKeydown);
        document.removeEventListener("focusin", handleFocusin);
        document.body.style.overflow = previousOverflow;
    }
    if (!wasTop) return;
    const opener = modal.opener;
    if (opener instanceof HTMLElement && isAvailable(opener) &&
        (!topModal() || allowedRoots().some(root => root.contains(opener)))) {
        opener.focus({ preventScroll: true });
    } else if (topModal()) {
        focusModal(topModal());
    } else {
        const fallback = document.querySelector("main, [role='main']") || document.body;
        const previousTabindex = fallback.getAttribute("tabindex");
        fallback.setAttribute("tabindex", "-1");
        fallback.focus({ preventScroll: true });
        if (previousTabindex === null) fallback.removeAttribute("tabindex");
        else fallback.setAttribute("tabindex", previousTabindex);
    }
}

export function useModalLifecycle(show, elementRef, dismiss) {
    const layer = ref(10000);
    let current;
    let revision = 0;
    watch(show, async (visible) => {
        const opening = ++revision;
        if (!visible) {
            if (current) deactivate(current);
            current = undefined;
            return;
        }
        const opener = document.activeElement;
        await nextTick();
        if (opening !== revision || !elementRef.value) return;
        current = { element: elementRef.value, opener, dismiss, layer };
        activate(current);
    }, { immediate: true, flush: "sync" });
    onUnmounted(() => {
        revision++;
        if (current) deactivate(current);
    });
    return {
        layer,
        dismiss: () => { if (current === topModal()) dismiss(); },
    };
}
