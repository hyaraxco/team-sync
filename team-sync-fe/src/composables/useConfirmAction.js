import { ref } from "vue";

export function useConfirmAction(config = {}) {
    const isModalOpen = ref(false);
    const selectedItem = ref(null);
    const isProcessing = ref(false);
    const error = ref(null);

    const openModal = (item = null) => {
        selectedItem.value = item;
        isModalOpen.value = true;
        error.value = null;
        if (config.onOpen) {
            config.onOpen(item);
        }
    };

    const closeModal = () => {
        isModalOpen.value = false;
        selectedItem.value = null;
        error.value = null;
        if (config.onClose) {
            config.onClose();
        }
    };

    const confirmAction = async (actionCallback) => {
        try {
            isProcessing.value = true;
            error.value = null;
            await actionCallback(selectedItem.value);

            closeModal();

            if (config.onSuccess) {
                await config.onSuccess();
            }
        } catch (err) {
            error.value = err;
            if (config.onError) {
                config.onError(err);
            }
        } finally {
            isProcessing.value = false;
        }
    };

    return {
        isModalOpen,
        selectedItem,
        isProcessing,
        error,
        openModal,
        closeModal,
        confirmAction,
    };
}
