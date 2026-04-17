<script setup>
import { computed, toRef } from "vue";
import { useAnimatedNumber } from "@/composables/useAnimatedNumber";

const props = defineProps({
  value: { type: [Number, String], default: 0 },
  suffix: { type: String, default: "" },
  prefix: { type: String, default: "" },
  duration: { type: Number, default: 800 },
  decimals: { type: Number, default: 0 },
});

const numericValue = computed(() => {
  const val = typeof props.value === "string" ? parseFloat(props.value) : props.value;
  return isNaN(val) ? 0 : val;
});

const { displayValue } = useAnimatedNumber(numericValue, {
  duration: props.duration,
  decimals: props.decimals,
});
</script>

<template>
  <span>{{ prefix }}{{ displayValue }}{{ suffix }}</span>
</template>
