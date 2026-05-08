<template>
    <div>
        <label :for="fieldId" class="block text-sm font-medium text-gray-700 mb-1.5" v-if="label">
            {{ label }}
        </label>

        <div class="relative">
            <!-- slot icon -->
            <div v-if="hasIcon" class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <slot name="icon" />
            </div>

            <select
                :id="fieldId"
                :name="name"
                v-model="modelValue"
                :required="required"
                :class="[
                    'appearance-none w-full pr-10 py-3 border rounded-[16px] transition-all duration-300 font-semibold',
                    hasIcon ? 'pl-12' : 'pl-4',
                    'hover:border-[#0C51D9] hover:border-2',
                    'focus:border-[#0C51D9] focus:border-2 focus:bg-white',
                    'cursor-pointer',
                    borderColor,
                ]"
                :style="selectStyle"
                @change="modelValue = $event.target.value"
            >
                <option v-if="placeholder" value="">{{ placeholder }}</option>
                <option v-for="opt in options" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                </option>
            </select>
        </div>

        <!-- custom chevron -->
        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none opacity-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>

        <p v-if="error" class="text-xs text-red-600 mt-1 flex items-start gap-1 px-1">
            <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
import { computed, useSlots } from "vue";
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

const fieldId = computed(() => {
    if (props.id) return props.id;
    if (props.name) return props.name;
    return `select-${String(props.label)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-|-$/g, "")}`;
});

const modelValue = computed({
    get: () => props.modelValue || "",
    set: (value) => emit("update:modelValue", value),
});

const borderColor = computed(() => (props.error ? "border-[#DC2626] border-2" : "border-[#DCDEDD]"));
</script>
