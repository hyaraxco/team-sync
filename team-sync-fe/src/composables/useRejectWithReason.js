import { ref, computed } from "vue";

/**
 * Composable for reject-with-reason modal flow.
 * Encapsulates the reject reason state, validation, and execution.
 *
 * @param {Object} options
 * @param {Function} options.rejectFn - async (item, reason) => void
 * @param {Function} [options.onSuccess] - callback after successful rejection
 * @param {number} [options.minLength=10] - minimum reason length
 */
export function useRejectWithReason({ rejectFn, onSuccess, minLength = 10 } = {}) {
    const showRejectModal = ref(false);
    const rejectingItem = ref(null);
    const rejectReason = ref("");
    const processingReject = ref(false);

    const isReasonValid = computed(() => rejectReason.value.trim().length >= minLength);

    const openRejectModal = (item) => {
        rejectingItem.value = item;
        rejectReason.value = "";
        showRejectModal.value = true;
    };

    const closeRejectModal = () => {
        showRejectModal.value = false;
        rejectingItem.value = null;
        rejectReason.value = "";
    };

    const confirmReject = async () => {
        if (!rejectingItem.value || !isReasonValid.value) return;

        processingReject.value = true;
        try {
            await rejectFn(rejectingItem.value, rejectReason.value);
            closeRejectModal();
            if (onSuccess) await onSuccess();
        } finally {
            processingReject.value = false;
        }
    };

    return {
        showRejectModal,
        rejectingItem,
        rejectReason,
        processingReject,
        isReasonValid,
        openRejectModal,
        closeRejectModal,
        confirmReject,
        minLength,
    };
}
