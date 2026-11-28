<template>
    <div class="w-full space-y-2 focus-within:z-10 relative text-left" :class="$attrs.class" :style="$attrs.style">
        <label :for="fieldId" class="block text-sm font-medium text-brand-dark" v-if="label">
            {{ label }}
        </label>

        <div class="relative flex items-center h-12">
            <!-- slot icon -->
            <div
                v-if="hasIcon"
                class="absolute left-4 flex h-full items-center pointer-events-none text-gray-400 motion-reduce:transition-none transition-colors peer-focus:text-brand-primary z-10"
            >
                <slot name="icon" />
            </div>

            <input
                v-bind="controlAttrs()"
                :id="fieldId"
                :name="name"
                :type="type"
                v-model="modelValue"
                :placeholder="placeholder"
                :required="required"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="[$attrs['aria-describedby'], error ? errorId : null].filter(Boolean).join(' ') || undefined"
                :min="min"
                :max="max"
                :step="step"
                :autocomplete="autocomplete"
                class="peer w-full h-full bg-white text-gray-950 border text-sm rounded-2xl motion-reduce:transition-none transition-all duration-200 placeholder:text-gray-600 placeholder:font-normal font-medium"
                :class="[
                    hasIcon ? 'pl-12' : 'pl-4',
                    hasSuffix ? 'pr-12' : 'pr-4',
                    error
                        ? 'border-red-300 ring-4 ring-red-500/10 focus:border-red-500 focus:ring-red-500/20'
                        : 'border-gray-200 hover:border-gray-300 focus:border-brand-primary focus:ring-4 focus:ring-brand-primary/10',
                ]"
                @input="modelValue = $event.target.value"
                @blur="emit('blur', $event)"
            />

            <!-- slot suffix -->
            <div v-if="hasSuffix" class="absolute right-4 flex h-full items-center z-10">
                <slot name="suffix" />
            </div>
        </div>

        <!-- Error Message -->
        <div
            v-if="error"
            :id="errorId"
            role="alert"
            class="text-xs text-[var(--color-feedback-danger)] mt-1 flex items-start gap-1 px-1"
        >
            <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            <span class="leading-tight">{{ error }}</span>
        </div>
    </div>
</template>

<script setup>
import { computed, useSlots, useAttrs, useId } from "vue";

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
    type: { type: String, default: "text" },
    placeholder: { type: String, default: "" },
    required: { type: Boolean, default: false },
    modelValue: { type: [String, Number], default: "" },
    error: { type: String, default: "" },
    min: { type: [String, Number], default: undefined },
    max: { type: [String, Number], default: undefined },
    step: { type: [String, Number], default: undefined },
    autocomplete: { type: String, default: undefined },
});
const emit = defineEmits(["update:modelValue", "blur"]);

const slots = useSlots();
const hasIcon = computed(() => !!slots.icon);
const hasSuffix = computed(() => !!slots.suffix);

const fieldId = computed(() => props.id || `input-${generatedId}`);

const errorId = computed(() => `${fieldId.value}-error`);

const modelValue = computed({
    get: () => props.modelValue ?? "",
    set: (value) => emit("update:modelValue", value),
});
</script>


