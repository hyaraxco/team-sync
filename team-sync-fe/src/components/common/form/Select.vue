<template>
  <div>
    <label :for="fieldId" class="block mb-2" :style="labelStyle">
      {{ label }}
    </label>

    <div class="relative">
      <!-- slot icon -->
      <div
        v-if="$slots.icon"
        class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
      >
        <slot name="icon" />
      </div>

      <select
        :id="fieldId"
        :name="name"
        v-model="modelValue"
        :required="required"
        :class="[
          'w-full pl-10 pr-4 py-3 border rounded-[16px] transition-all duration-300 font-semibold',
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

    <p v-if="error" class="mt-2" :style="errorStyle">
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

const borderColor = computed(() =>
  props.error ? "border-[#DC2626] border-2" : "border-[#DCDEDD]"
);

const labelStyle = {
  color: "#4b5563",
  fontFamily: "Plus Jakarta Sans",
  fontSize: "14px",
  fontWeight: 600,
};
const selectStyle = {
  background: "#ffffff",
  paddingLeft: "40px",
};
const errorStyle = {
  color: "#dc2626",
  fontFamily: "Plus Jakarta Sans",
  fontSize: "14px",
  fontWeight: 400,
};
</script>
