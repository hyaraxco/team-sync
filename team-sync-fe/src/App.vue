<script setup>
import { RouterView } from "vue-router";
import ToastContainer from "@/components/common/ToastContainer.vue";
import { useDarkMode } from "@/composables/useDarkMode";

// Initialize dark mode on app mount (reads localStorage / system preference)
useDarkMode();
</script>

<template>
    <RouterView />
    <ToastContainer />
</template>
