<script setup>
import { X, AlertTriangle } from "lucide-vue-next";
import { ref, watch, onMounted, onUnmounted, nextTick } from "vue";

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
let previouslyFocused = null;

const handleConfirm = () => emit("confirm");
const handleCancel = () => emit("cancel");

const getFocusableElements = () => {
    if (!modalRef.value) return [];
    return Array.from(
        modalRef.value.querySelectorAll(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )
    );
};

const handleKeydown = (e) => {
    if (!props.show) return;
    if (e.key === "Escape") {
        handleCancel();
        return;
    }
    if (e.key === "Tab") {
        const focusable = getFocusableElements();
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey) {
            if (document.activeElement === first) {
                e.preventDefault();
                last.focus();
            }
        } else {
            if (document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }
};

watch(
    () => props.show,
    async (val) => {
        if (val) {
            previouslyFocused = document.activeElement;
            await nextTick();
            const focusable = getFocusableElements();
            if (focusable.length > 0) focusable[0].focus();
        } else {
            if (previouslyFocused) previouslyFocused.focus();
        }
    }
);

onMounted(() => document.addEventListener("keydown", handleKeydown));
onUnmounted(() => document.removeEventListener("keydown", handleKeydown));
</script>

<template>
    <div
        v-if="show"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`confirm-title-${title.replace(/\s+/g, '-').toLowerCase()}`"
        class="fixed inset-0 backdrop-blur-sm bg-black/30 z-50 flex items-center justify-center p-4"
        @click.self="handleCancel"
    >
        <div
            ref="modalRef"
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
                                class="w-6 h-6"
                                :class="{
                                    'text-red-600': type === 'danger',
                                    'text-yellow-600': type === 'warning',
                                    'text-blue-600': type === 'info',
                                }"
                            />
                        </div>
                        <div>
                            <h3 :id="`confirm-title-${title.replace(/\s+/g, '-').toLowerCase()}`" class="text-brand-dark text-xl font-bold">{{ title }}</h3>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="handleCancel"
                        aria-label="Close"
                        class="w-10 h-10 rounded-full border border-brand-border flex items-center justify-center hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200"
                    >
                        <X class="w-5 h-5 text-gray-600" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div class="p-6">
                <p class="text-brand-light text-base">{{ message }}</p>
            </div>

            <!-- Actions -->
            <div class="p-6 border-t border-brand-border flex gap-3 justify-end">
                <button
                    type="button"
                    @click="handleCancel"
                    :disabled="loading"
                    class="border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-6 py-3"
                >
                    <span class="text-brand-dark text-base font-semibold">{{ cancelText }}</span>
                </button>
                <button
                    type="button"
                    @click="handleConfirm"
                    :disabled="loading"
                    class="rounded-lg border hover:brightness-110 focus:ring-2 transition-all duration-300 px-6 py-3 flex items-center gap-2"
                    :class="{
                        'bg-gradient-to-r from-red-500 to-red-600 shadow-lg focus:ring-red-500':
                            type === 'danger',
                        'bg-gradient-to-r from-yellow-500 to-yellow-600 shadow-lg focus:ring-yellow-500':
                            type === 'warning',
                        'blue-gradient blue-btn-shadow focus:ring-brand-primary': type === 'info',
                    }"
                >
                    <span class="text-brand-white text-base font-semibold">
                        {{ loading ? "Processing..." : confirmText }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
