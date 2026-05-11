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
