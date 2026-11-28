<template>
    <div :class="$attrs.class" :style="$attrs.style">
        <label :for="fieldId" class="block text-sm font-medium text-brand-dark mb-1.5" v-if="label">
            {{ label }}
        </label>

        <div class="relative">
            <!-- slot icon -->
            <div v-if="hasIcon" class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <slot name="icon" />
            </div>

            <select
                v-bind="controlAttrs()"
                :id="fieldId"
                :name="name"
                v-model="modelValue"
                :required="required"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="[$attrs['aria-describedby'], error ? errorId : null].filter(Boolean).join(' ') || undefined"
                :class="[
                    'appearance-none w-full pr-10 py-3 border rounded-2xl motion-reduce:transition-none transition-all duration-300 font-semibold',
                    hasIcon ? 'pl-12' : 'pl-4',
                    'hover:ring-2 hover:ring-brand-primary/20',
                    'focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white',
                    'cursor-pointer',
                    borderColor,
                ]"
                @change="modelValue = $event.target.value"
            >
                <option v-if="placeholder" value="">{{ placeholder }}</option>
                <option v-for="opt in options" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                </option>
            </select>
            <!-- custom chevron -->
            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none opacity-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </div>
        <p v-if="error" :id="errorId" role="alert" class="text-xs text-[var(--color-feedback-danger)] mt-1 flex items-start gap-1 px-1">
            <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            <span class="leading-tight">{{ error }}</span>
        </p>
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
const slots = useSlots();
const hasIcon = computed(() => !!slots.icon);

const props = defineProps({
    id: { type: String, default: "" },
    name: { type: String, default: "" },
    label: { type: String, required: true },
    placeholder: { type: String, default: "" }, // optional first option
    required: { type: Boolean, default: false },
    modelValue: { type: [String, Number], default: "" },
    options: {
        type: Array,
        default: () => [], // [{ value:'dev', label:'Development' }]
    },
    error: { type: String, default: "" },
});
const emit = defineEmits(["update:modelValue"]);

const fieldId = computed(() => props.id || `select-${generatedId}`);

const errorId = computed(() => `${fieldId.value}-error`);

const modelValue = computed({
    get: () => props.modelValue || "",
    set: (value) => emit("update:modelValue", value),
});

const borderColor = computed(() => (props.error ? "border-danger-600 border-2" : "border-brand-border"));
</script>
