import { describe, it, expect, beforeEach, vi } from "vitest";
import { useDarkMode } from "@/composables/useDarkMode";

describe("useDarkMode", () => {
    beforeEach(() => {
        localStorage.clear();
        document.documentElement.classList.remove("dark");
        // Reset module-level isDark ref to false
        const { isDark } = useDarkMode();
        if (isDark.value) {
            isDark.value = false;
            document.documentElement.classList.remove("dark");
        }
    });

    it("toggle switches isDark from false to true", () => {
        const { isDark, toggle } = useDarkMode();

        toggle();

        expect(isDark.value).toBe(true);
    });

    it("toggle adds dark class to document", () => {
        const { isDark, toggle } = useDarkMode();

        // Ensure starts at false
        isDark.value = false;
        toggle();

        expect(document.documentElement.classList.contains("dark")).toBe(true);
    });

    it("toggle persists dark to localStorage", () => {
        const { isDark, toggle } = useDarkMode();

        isDark.value = false;
        toggle();

        expect(localStorage.getItem("theme")).toBe("dark");
    });

    it("toggle removes dark class when toggled off", () => {
        const { isDark, toggle } = useDarkMode();

        isDark.value = true;
        toggle();

        expect(document.documentElement.classList.contains("dark")).toBe(false);
    });

    it("toggle persists light to localStorage", () => {
        const { isDark, toggle } = useDarkMode();

        isDark.value = true;
        toggle();

        expect(localStorage.getItem("theme")).toBe("light");
    });
});
