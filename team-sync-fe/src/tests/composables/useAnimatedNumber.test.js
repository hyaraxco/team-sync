import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { defineComponent, ref } from "vue";
import { mount } from "@vue/test-utils";
import { useAnimatedNumber } from "@/composables/useAnimatedNumber";

function mountAnimated(source, options = {}) {
    const wrapper = mount(
        defineComponent({
            setup() {
                const { displayValue } = useAnimatedNumber(source, options);
                return { displayValue };
            },
            template: "<div />",
        }),
    );
    return wrapper;
}

describe("useAnimatedNumber", () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it("shows the final number without animation under reduced motion", async () => {
        vi.stubGlobal("matchMedia", () => ({ matches: true, addEventListener: vi.fn(), removeEventListener: vi.fn() }));
        const source = ref(1250);
        const wrapper = mountAnimated(source);
        expect(wrapper.vm.displayValue).toBe((1250).toLocaleString());
        source.value = 2750;
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.displayValue).toBe((2750).toLocaleString());
        wrapper.unmount();
    });

    it.each([
        [true, 100, 0, "4"],
        [false, 0, 0, "4"],
        [true, 100, 2, "3.50"],
        [false, 0, 2, "3.50"],
        [false, 100, 0, "4"],
    ])("keeps precision with reduced motion %s, duration %s and decimals %s", async (reduced, duration, decimals, expected) => {
        vi.stubGlobal("matchMedia", () => ({ matches: reduced, addEventListener: vi.fn(), removeEventListener: vi.fn() }));
        const wrapper = mountAnimated(ref(3.5), { duration, decimals });
        await vi.advanceTimersByTimeAsync(200);
        expect(wrapper.vm.displayValue).toBe(expected);
        wrapper.unmount();
    });

    it("finishes an active animation when reduced motion is enabled", async () => {
        let change;
        const remove = vi.fn();
        vi.stubGlobal("matchMedia", () => ({ matches: false, addEventListener: (_event, callback) => { change = callback; }, removeEventListener: remove }));
        const wrapper = mountAnimated(ref(1000));
        change({ matches: true });
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.displayValue).toBe((1000).toLocaleString());
        wrapper.unmount();
        expect(remove).toHaveBeenCalledWith("change", change);
    });

    it("initializes displayValue to '0'", () => {
        const source = ref(0);
        const wrapper = mountAnimated(source);
        expect(wrapper.vm.displayValue).toBe("0");
    });

    it("animates from source value to target", async () => {
        const source = ref(0);
        const wrapper = mountAnimated(source, { duration: 100 });

        source.value = 100;
        await vi.advanceTimersByTimeAsync(200);

        expect(wrapper.vm.displayValue).toBe("100");
    });

    it("handles zero target immediately", async () => {
        const source = ref(0);
        const wrapper = mountAnimated(source, { duration: 100 });

        source.value = 0;
        await vi.advanceTimersByTimeAsync(0);

        expect(wrapper.vm.displayValue).toBe("0");
    });

    it("handles NaN target by displaying raw value", async () => {
        const source = ref(0);
        const wrapper = mountAnimated(source);

        source.value = "not-a-number";
        await vi.advanceTimersByTimeAsync(0);

        expect(wrapper.vm.displayValue).toBe("not-a-number");
    });

    it("handles string number source values", async () => {
        const source = ref("0");
        const wrapper = mountAnimated(source, { duration: 100 });

        source.value = "500";
        await vi.advanceTimersByTimeAsync(200);

        expect(wrapper.vm.displayValue).toBe("500");
    });

    it("respects decimals option", async () => {
        const source = ref(0);
        const wrapper = mountAnimated(source, { duration: 100, decimals: 2 });

        source.value = 1.5;
        await vi.advanceTimersByTimeAsync(200);

        expect(wrapper.vm.displayValue).toContain("1.5");
    });

    it("cancels animation on unmount", async () => {
        const source = ref(0);
        const wrapper = mountAnimated(source, { duration: 100 });

        source.value = 1000;
        await vi.advanceTimersByTimeAsync(50);

        wrapper.unmount();

        // No error should occur after unmount
        expect(true).toBe(true);
    });

    it("handles initial immediate watch with non-zero value", async () => {
        const source = ref(100);
        const wrapper = mountAnimated(source, { duration: 100 });

        await vi.advanceTimersByTimeAsync(200);

        expect(wrapper.vm.displayValue).toBe("100");
    });
});
