<script setup>
import { X } from "lucide-vue-next";
import { ref, useId } from "vue";
import { useModalLifecycle } from "@/composables/useModalLifecycle";

const props = defineProps({
    show: {
        type: Boolean,
        required: true,
    },
    title: {
        type: String,
        required: false,
    },
    labelledby: String,
    describedby: String,
    loading: Boolean,
    maxWidth: {
        type: String,
        default: "md", // sm, md, lg, xl, 2xl, 3xl, 4xl
    },
});

const emit = defineEmits(["close"]);

const dialogRef = ref(null);
const titleId = `modal-title-${useId()}`;
const { layer, dismiss: close } = useModalLifecycle(
    () => props.show,
    dialogRef,
    () => { if (!props.loading) emit("close"); },
);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                ref="dialogRef"
                data-modal-overlay
                class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center"
                :style="{ margin: 0, padding: 0, zIndex: layer }"
                @click.self="close"
            >
                <Transition
                    enter-active-class="transition duration-200 ease-out flex motion-reduce:transition-none"
                    enter-from-class="opacity-0 motion-safe:scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition duration-150 ease-in flex motion-reduce:transition-none"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 motion-safe:scale-95"
                >
                    <div
                        v-if="show"
                        role="dialog"
                        aria-modal="true"
                        :aria-labelledby="labelledby || (title || $slots.header ? titleId : undefined)"
                        :aria-describedby="describedby"
                        :aria-busy="loading || undefined"
                        tabindex="-1"
                        class="rounded-2xl p-6 w-full mx-4 flex flex-col max-h-[90vh]"
                        style="background: var(--color-surface)"
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
                            <div v-if="$slots.header" :id="titleId" class="flex-1 min-w-0">
                                <slot name="header" :title-id="titleId" />
                            </div>
                            <template v-else>
                                <h3 :id="titleId" class="text-brand-dark text-xl font-bold">
                                    {{ title }}
                                </h3>
                            </template>

                            <button
                                type="button"
                                :disabled="loading"
                                @click="close"
                                aria-label="Close"
                                class="text-brand-light hover:text-brand-dark transition-colors motion-reduce:transition-none ml-4 min-w-6 min-h-6 flex items-center justify-center rounded-lg disabled:opacity-50"
                            >
                                <X class="w-5 h-5" aria-hidden="true" />
                            </button>
                        </div>

                        <!-- Content -->
                        <div class="overflow-y-auto pr-1 custom-scrollbar shrink min-h-0">
                            <slot :title-id="titleId"></slot>
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
