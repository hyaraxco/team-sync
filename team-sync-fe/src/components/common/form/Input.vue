<template>
  <div>
    <label
      :for="fieldId"
      class="block mb-2 text-gray-700 font-semibold font-jakarta text-[14px]"
      v-if="label"
    >
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

      <!-- slot suffix -->
      <div
        v-if="$slots.suffix"
        class="absolute inset-y-0 right-0 pr-3 flex items-center"
      >
        <slot name="suffix" />
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
        :class="[
          'w-full border rounded-[16px] transition-all duration-300',
          'hover:border-[#0C51D9] hover:border-2',
          'focus:border-[#0C51D9] focus:border-2 focus:bg-white',
          borderColor,
        ]"
        :style="inputStyle"
        @input="modelValue = $event.target.value"
        @blur="emit('blur', $event)"
      />
    </div>

    <p v-if="error" class="mt-2" :style="errorStyle">
      {{ error }}
    </p>
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

const borderColor = computed(() =>
  props.error ? "border-[#DC2626] border-2" : "border-[#DCDEDD]"
);

const hasIcon = computed(() => Boolean(slots.icon));
const hasSuffix = computed(() => Boolean(slots.suffix));

const inputStyle = computed(() => ({
  display: "flex",
  padding: "12px",
  paddingLeft: hasIcon.value ? "40px" : "12px",
  paddingRight: hasSuffix.value ? "44px" : "12px",
  justifyContent: "flex-start",
  alignItems: "center",
  gap: "10px",
  background: "#ffffff",
}));

const errorStyle = {
  color: "#dc2626",
  fontFamily: "Plus Jakarta Sans",
  fontSize: "14px",
  fontWeight: 400,
};
</script>
