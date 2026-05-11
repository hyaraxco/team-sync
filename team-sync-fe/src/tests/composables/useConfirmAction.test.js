import { describe, it, expect, beforeEach, vi } from "vitest";
import { useConfirmAction } from "@/composables/useConfirmAction";

describe("useConfirmAction", () => {
    let action;

    beforeEach(() => {
        action = useConfirmAction();
    });

    it("initializes with default state", () => {
        expect(action.isModalOpen.value).toBe(false);
        expect(action.selectedItem.value).toBe(null);
        expect(action.isProcessing.value).toBe(false);
        expect(action.error.value).toBe(null);
    });

    it("openModal sets isModalOpen and selectedItem", () => {
        const item = { id: 1, name: "Test" };
        action.openModal(item);

        expect(action.isModalOpen.value).toBe(true);
        expect(action.selectedItem.value).toEqual(item);
    });

    it("openModal clears error", () => {
        action.error.value = "previous error";
        action.openModal();

        expect(action.error.value).toBe(null);
    });

    it("openModal calls config.onOpen", () => {
        const onOpen = vi.fn();
        const actionWithConfig = useConfirmAction({ onOpen });
        const item = { id: 1 };

        actionWithConfig.openModal(item);

        expect(onOpen).toHaveBeenCalledWith(item);
    });

    it("closeModal resets state", () => {
        action.openModal({ id: 1 });
        action.closeModal();

        expect(action.isModalOpen.value).toBe(false);
        expect(action.selectedItem.value).toBe(null);
        expect(action.error.value).toBe(null);
    });

    it("closeModal calls config.onClose", () => {
        const onClose = vi.fn();
        const actionWithConfig = useConfirmAction({ onClose });

        actionWithConfig.closeModal();

        expect(onClose).toHaveBeenCalled();
    });

    it("confirmAction calls callback with selectedItem", async () => {
        const item = { id: 1 };
        const callback = vi.fn().mockResolvedValue();
        action.openModal(item);

        await action.confirmAction(callback);

        expect(callback).toHaveBeenCalledWith(item);
    });

    it("confirmAction closes modal on success", async () => {
        const callback = vi.fn().mockResolvedValue();
        action.openModal();

        await action.confirmAction(callback);

        expect(action.isModalOpen.value).toBe(false);
    });

    it("confirmAction calls config.onSuccess on success", async () => {
        const onSuccess = vi.fn().mockResolvedValue();
        const actionWithConfig = useConfirmAction({ onSuccess });
        const callback = vi.fn().mockResolvedValue();
        actionWithConfig.openModal();

        await actionWithConfig.confirmAction(callback);

        expect(onSuccess).toHaveBeenCalled();
    });

    it("confirmAction sets error on failure", async () => {
        const error = new Error("Failed");
        const callback = vi.fn().mockRejectedValue(error);
        action.openModal();

        await action.confirmAction(callback);

        expect(action.error.value).toBe(error);
    });

    it("confirmAction calls config.onError on failure", async () => {
        const onError = vi.fn();
        const actionWithConfig = useConfirmAction({ onError });
        const error = new Error("Failed");
        const callback = vi.fn().mockRejectedValue(error);
        actionWithConfig.openModal();

        await actionWithConfig.confirmAction(callback);

        expect(onError).toHaveBeenCalledWith(error);
    });

    it("confirmAction sets isProcessing during execution", async () => {
        let resolveCallback;
        const callback = vi.fn().mockImplementation(
            () =>
                new Promise((resolve) => {
                    resolveCallback = resolve;
                }),
        );
        action.openModal();

        const promise = action.confirmAction(callback);

        // Wait for next tick to let the callback start
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(action.isProcessing.value).toBe(true);

        resolveCallback();
        await promise;

        expect(action.isProcessing.value).toBe(false);
    });
});
