<script setup>
import { X } from "lucide-vue-next";
import { onMounted, onUnmounted, watch, ref, nextTick } from "vue";

const props = defineProps({
    show: {
        type: Boolean,
        required: true,
    },
    title: {
        type: String,
        required: false,
    },
    maxWidth: {
        type: String,
        default: "md", // sm, md, lg, xl, 2xl, 3xl, 4xl
    },
});

const emit = defineEmits(["close"]);

const dialogRef = ref(null);
const titleId = ref(`modal-title-${Math.random().toString(36).slice(2, 8)}`);

const close = () => {
    emit("close");
};

// Handle escape key to close
const handleKeydown = (e) => {
    if (e.key === "Escape" && props.show) {
        close();
    }
    // Focus trap: keep Tab inside modal
    if (e.key === "Tab" && props.show && dialogRef.value) {
        const focusable = dialogRef.value.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }
};

// Focus first focusable element when modal opens
const focusFirst = async () => {
    await nextTick();
    if (!dialogRef.value) return;
    const focusable = dialogRef.value.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (focusable.length > 0) {
        focusable[0].focus();
    }
};

onMounted(() => {
    document.addEventListener("keydown", handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener("keydown", handleKeydown);
    document.body.style.overflow = "";
});

// Prevent body scrolling when modal is open
watch(
    () => props.show,
    (val) => {
        if (val) {
            document.body.style.overflow = "hidden";
            focusFirst();
        } else {
            document.body.style.overflow = "";
        }
    },
);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="title ? titleId : undefined"
                ref="dialogRef"
                class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999]"
                style="margin: 0; padding: 0"
                @click.self="close"
            >
                <Transition
                    enter-active-class="transition duration-200 ease-out flex"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition duration-150 ease-in flex"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="show"
                        class="bg-white rounded-[20px] p-6 w-full mx-4 flex flex-col max-h-[90vh]"
                        :class="{
                            'max-w-sm': maxWidth === 'sm',
                            'max-w-md': maxWidth === 'md',
                            'max-w-lg': maxWidth === 'lg',
                            'max-w-xl': maxWidth === 'xl',
                            'max-w-2xl': maxWidth === '2xl',
                            'max-w-3xl': maxWidth === '3xl',
                            'max-w-4xl': maxWidth === '4xl',
                        }"
                    >
                        <!-- Header -->
                        <div v-if="$slots.header || title" class="flex items-center justify-between mb-4 shrink-0">
                            <slot name="header">
                                <h3 :id="titleId" class="text-brand-dark text-xl font-bold">
                                    {{ title }}
                                </h3>
                            </slot>

                            <button
                                @click="close"
                                aria-label="Close"
                                class="text-gray-400 hover:text-gray-600 transition-colors ml-4 min-w-6 min-h-6 flex items-center justify-center rounded-lg hover:bg-gray-100"
                            >
                                <X class="w-5 h-5" aria-hidden="true" />
                            </button>
                        </div>

                        <!-- Content -->
                        <div class="overflow-y-auto pr-1 custom-scrollbar shrink min-h-0">
                            <slot></slot>
                        </div>

                        <!-- Footer (Optional) -->
                        <div v-if="$slots.footer" class="mt-6 shrink-0">
                            <slot name="footer"></slot>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
    border-radius: 20px;
}
</style>
