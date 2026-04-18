<template>
  <div class="w-full space-y-1.5 focus-within:z-10 relative text-left">
    <label
      :for="fieldId"
      class="block text-sm font-medium text-gray-700"
      v-if="label"
    >
      {{ label }}
    </label>

    <div class="relative flex items-center h-12">
      <!-- slot icon -->
      <div
        v-if="hasIcon"
        class="absolute left-4 flex h-full items-center pointer-events-none text-gray-400 transition-colors peer-focus:text-[#0C51D9] z-10"
      >
        <slot name="icon" />
      </div>

      <input
        :id="fieldId"
        :name="name"
        :type="type"
        v-model="modelValue"
        :placeholder="placeholder"
        :required="required"
        :min="min"
        :step="step"
        class="peer w-full h-full bg-white text-gray-900 border text-sm rounded-xl outline-none transition-all duration-200 placeholder:text-gray-400 placeholder:font-normal font-medium"
        :class="[
          hasIcon ? 'pl-12' : 'pl-4',
          hasSuffix ? 'pr-12' : 'pr-4',
          error 
            ? 'border-red-300 ring-4 ring-red-500/10 focus:border-red-500 focus:ring-red-500/20' 
            : 'border-gray-200 hover:border-gray-300 focus:border-[#0C51D9] focus:ring-4 focus:ring-[#0C51D9]/10'
        ]"
        @input="modelValue = $event.target.value"
        @blur="emit('blur', $event)"
      />

      <!-- slot suffix -->
      <div
        v-if="hasSuffix"
        class="absolute right-4 flex h-full items-center z-10"
      >
        <slot name="suffix" />
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="text-xs text-red-600 mt-1 flex items-start gap-1 px-1">
      <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span class="leading-tight">{{ error }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed, useSlots } from "vue";

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
  step: { type: [String, Number], default: undefined },
});
const emit = defineEmits(["update:modelValue", "blur"]);

const slots = useSlots();
const hasIcon = computed(() => !!slots.icon);
const hasSuffix = computed(() => !!slots.suffix);

const fieldId = computed(() => {
  if (props.id) return props.id;
  if (props.name) return props.name;
  return `input-${String(props.label)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "")}`;
});

const modelValue = computed({
  get: () => props.modelValue || "",
  set: (value) => emit("update:modelValue", value),
});
</script>

<style>
/* Global because scoped keyframes can be tricky with dynamic classes */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
  animation: fadeIn 0.5s ease-out forwards;
}
</style>
