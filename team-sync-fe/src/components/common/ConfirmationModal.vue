<script setup>
import { X, AlertTriangle } from "lucide-vue-next";
import { ref, useId } from "vue";
import { useModalLifecycle } from "@/composables/useModalLifecycle";

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: "Confirm Action",
    },
    message: {
        type: String,
        default: "Are you sure you want to proceed?",
    },
    confirmText: {
        type: String,
        default: "Confirm",
    },
    cancelText: {
        type: String,
        default: "Cancel",
    },
    loading: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: "danger", // danger, warning, info
    },
});

const emit = defineEmits(["confirm", "cancel"]);
const modalRef = ref(null);
const id = useId();
const titleId = `confirm-title-${id}`;
const messageId = `confirm-message-${id}`;
const handleConfirm = () => { if (!props.loading) emit("confirm"); };
const { layer, dismiss: handleCancel } = useModalLifecycle(
    () => props.show,
    modalRef,
    () => emit("cancel"),
);
</script>

<template>
    <Teleport to="body">
    <div
        v-if="show"
        ref="modalRef"
        data-modal-overlay
        :style="{ zIndex: layer }"
        class="fixed inset-0 backdrop-blur-sm bg-black/30 flex items-center justify-center p-4"
        @click.self="handleCancel"
    >
        <div
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
            :aria-describedby="messageId"
            tabindex="-1"
            class="rounded-2xl border border-brand-border w-full max-w-md overflow-hidden"
            style="background: var(--color-surface)"
        >
            <!-- Header -->
            <div class="p-6 border-b border-brand-border">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 rounded-xl flex items-center justify-center"
                            :class="{
                                'bg-red-50': type === 'danger',
                                'bg-yellow-50': type === 'warning',
                                'bg-blue-50': type === 'info',
                            }"
                        >
                            <AlertTriangle
                                aria-hidden="true"
                                class="w-6 h-6"
                                :class="{
                                    'text-red-600': type === 'danger',
                                    'text-yellow-600': type === 'warning',
                                    'text-blue-600': type === 'info',
                                }"
                            />
                        </div>
                        <div>
                            <h3 :id="titleId" class="text-brand-dark text-xl font-bold">{{ title }}</h3>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="handleCancel"
                        aria-label="Close"
                        class="w-10 h-10 rounded-full border border-brand-border flex items-center justify-center transition-colors motion-reduce:transition-none duration-200 disabled:opacity-50"
                    >
                        <X class="w-5 h-5 text-brand-light" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div class="p-6">
                <p :id="messageId" class="text-brand-light text-base">{{ message }}</p>
                <p role="status" aria-live="polite" class="sr-only">{{ loading ? "Processing..." : "" }}</p>
            </div>

            <!-- Actions -->
            <div class="p-6 border-t border-brand-border flex gap-3 justify-end">
                <button
                    type="button"
                    @click="handleCancel"
                    :disabled="loading"
                    class="border border-brand-border rounded-lg transition-colors motion-reduce:transition-none duration-300 px-6 py-3 disabled:opacity-50"
                >
                    <span class="text-brand-dark text-base font-semibold">{{ cancelText }}</span>
                </button>
                <button
                    type="button"
                    @click="handleConfirm"
                    :disabled="loading"
                    class="rounded-lg border border-transparent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition-colors motion-reduce:transition-none duration-300 px-6 py-3 flex items-center gap-2 disabled:opacity-50"
                    :class="{
                        'bg-red-700 hover:bg-red-800 text-white': type === 'danger',
                        'bg-amber-800 hover:bg-amber-900 text-white': type === 'warning',
                        'bg-blue-800 hover:bg-blue-900 text-white': type === 'info',
                    }"
                >
                    <span class="text-white text-base font-semibold">
                        {{ loading ? "Processing..." : confirmText }}
                    </span>
                </button>
            </div>
        </div>
    </div>
    </Teleport>
</template>
