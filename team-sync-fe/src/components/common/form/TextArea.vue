<template>
    <div>
        <label :for="fieldId" class="block mb-2 text-sm font-semibold text-gray-600">
            {{ label }}
        </label>

        <div class="relative">
            <!-- slot icon -->
            <div v-if="hasIcon" class="absolute top-3 left-4 pointer-events-none">
                <slot name="icon" />
            </div>

            <textarea
                :id="fieldId"
                :name="name"
                v-model="modelValue"
                :rows="rows"
                :placeholder="placeholder"
                :required="required"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? errorId : undefined"
                :class="[
                    'w-full pr-4 py-3 border rounded-2xl transition-all duration-300 font-semibold bg-white',
                    hasIcon ? 'pl-12' : 'pl-4',
                    'hover:ring-2 hover:ring-brand-primary/20',
                    'focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white',
                    borderColor,
                ]"
                @input="modelValue = $event.target.value"
            ></textarea>
        </div>

        <p v-if="error" :id="errorId" role="alert" class="mt-2 text-red-600 text-sm font-normal">
            {{ error }}
        </p>
    </div>
</template>

<script setup>
import { computed } from "vue";

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

const fieldId = computed(() => {
    if (props.id) return props.id;
    if (props.name) return props.name;
    return `textarea-${String(props.label)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-|-$/g, "")}`;
});

const errorId = computed(() => `${fieldId.value}-error`);

const modelValue = computed({
    get: () => props.modelValue || "",
    set: (value) => emit("update:modelValue", value),
});

const borderColor = computed(() => (props.error ? "border-danger-600 border-2" : "border-brand-border"));
</script>
