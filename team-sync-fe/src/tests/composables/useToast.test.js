import { describe, it, expect, beforeEach, vi } from "vitest";
import { useToast } from "@/composables/useToast";

describe("useToast", () => {
    let toast;

    beforeEach(() => {
        toast = useToast();
        // Clear existing toasts
        toast.toasts.splice(0, toast.toasts.length);
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it("addToast adds a toast with correct properties", () => {
        const id = toast.addToast({ type: "success", title: "Test", message: "Hello" });

        expect(id).toBeGreaterThan(0);
        expect(toast.toasts).toHaveLength(1);
        expect(toast.toasts[0]).toMatchObject({
            type: "success",
            title: "Test",
            message: "Hello",
            visible: true,
        });
    });

    it("addToast defaults type to success", () => {
        toast.addToast({ title: "Test" });

        expect(toast.toasts[0].type).toBe("success");
    });

    it("addToast defaults duration to 4000", () => {
        toast.addToast({ title: "Test" });

        expect(toast.toasts[0].duration).toBe(4000);
    });

    it("removeToast sets visible to false", () => {
        const id = toast.addToast({ title: "Test" });

        toast.removeToast(id);

        expect(toast.toasts[0].visible).toBe(false);
    });

    it("removeToast removes from array after animation delay", () => {
        const id = toast.addToast({ title: "Test" });

        toast.removeToast(id);
        vi.advanceTimersByTime(300);

        expect(toast.toasts).toHaveLength(0);
    });

    it("success creates success toast", () => {
        const id = toast.success("Success Title", "Success message");

        expect(toast.toasts[0].type).toBe("success");
        expect(toast.toasts[0].title).toBe("Success Title");
        expect(toast.toasts[0].message).toBe("Success message");
    });

    it("error creates error toast", () => {
        toast.error("Error Title", "Error message");

        expect(toast.toasts[0].type).toBe("error");
        expect(toast.toasts[0].title).toBe("Error Title");
    });

    it("warning creates warning toast", () => {
        toast.warning("Warning Title");

        expect(toast.toasts[0].type).toBe("warning");
        expect(toast.toasts[0].title).toBe("Warning Title");
    });

    it("info creates info toast", () => {
        toast.info("Info Title");

        expect(toast.toasts[0].type).toBe("info");
        expect(toast.toasts[0].title).toBe("Info Title");
    });

    it("auto-removes toast after duration", () => {
        toast.addToast({ title: "Test", duration: 1000 });

        expect(toast.toasts).toHaveLength(1);

        vi.advanceTimersByTime(1000);

        // After duration, removeToast is called (sets visible=false)
        expect(toast.toasts[0].visible).toBe(false);

        // After animation delay, removed from array
        vi.advanceTimersByTime(300);
        expect(toast.toasts).toHaveLength(0);
    });

    it("does not auto-remove when duration is 0", () => {
        toast.addToast({ title: "Persistent", duration: 0 });

        vi.advanceTimersByTime(10000);

        expect(toast.toasts).toHaveLength(1);
        expect(toast.toasts[0].visible).toBe(true);
    });
});
