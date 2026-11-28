<template>
    <div :class="$attrs.class" :style="$attrs.style">
        <label :for="fieldId" class="block mb-2 text-sm font-semibold text-brand-dark">
            {{ label }}
        </label>

        <div class="relative">
            <!-- slot icon -->
            <div v-if="hasIcon" class="absolute top-3 left-4 pointer-events-none">
                <slot name="icon" />
            </div>

            <textarea
                v-bind="controlAttrs()"
                :id="fieldId"
                :name="name"
                v-model="modelValue"
                :rows="rows"
                :placeholder="placeholder"
                :required="required"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="[$attrs['aria-describedby'], error ? errorId : null].filter(Boolean).join(' ') || undefined"
                :class="[
                    'w-full pr-4 py-3 border rounded-2xl bg-white motion-reduce:transition-none transition-all duration-300 font-semibold',
                    hasIcon ? 'pl-12' : 'pl-4',
                    'hover:ring-2 hover:ring-brand-primary/20',
                    'focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white',
                    borderColor,
                ]"
                @input="modelValue = $event.target.value"
            ></textarea>
        </div>

        <p v-if="error" :id="errorId" role="alert" class="mt-2 text-sm text-[var(--color-feedback-danger)]">
            {{ error }}
        </p>
    </div>
</template>

<script setup>
import { computed, useAttrs, useId } from "vue";

defineOptions({ inheritAttrs: false });
const attrs = useAttrs();
const controlAttrs = () => {
    const { class: wrapperClass, style: wrapperStyle, ...nativeAttrs } = attrs;
    return nativeAttrs;
};
const generatedId = useId();

const props = defineProps({
    id: { type: String, default: "" },
    name: { type: String, default: "" },
    label: { type: String, required: true },
    rows: { type: [String, Number], default: 4 },
    placeholder: { type: String, default: "" },
    required: { type: Boolean, default: false },
    modelValue: { type: String, default: "" },
    error: { type: String, default: "" },
});
const emit = defineEmits(["update:modelValue"]);

import { useSlots } from "vue";
const slots = useSlots();
const hasIcon = computed(() => !!slots.icon);

const fieldId = computed(() => props.id || `textarea-${generatedId}`);

const errorId = computed(() => `${fieldId.value}-error`);

const modelValue = computed({
    get: () => props.modelValue || "",
    set: (value) => emit("update:modelValue", value),
});

const borderColor = computed(() => (props.error ? "border-danger-600 border-2" : "border-brand-border"));
</script>
