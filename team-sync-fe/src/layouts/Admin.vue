<script setup>
import Sidebar from "@/components/admin/Sidebar.vue";
import Header from "@/components/admin/Header.vue";
import { provideSidebar } from "@/composables/useSidebar";

const { isOpen, toggleMobile, closeMobile } = provideSidebar();
</script>

<template>
    <div class="flex h-screen overflow-hidden">
        <!-- Skip to main content link for keyboard users -->
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[10000] focus:px-4 focus:py-2 focus:bg-white focus:shadow-lg focus:rounded-lg focus:text-brand-dark focus:font-semibold"
        >
            Skip to main content
        </a>

        <!-- Sidebar Slot - can be replaced with custom content -->
        <slot name="sidebar">
            <Sidebar />
        </slot>

        <!-- Main Content -->
        <div
            id="main-content"
            class="flex-1 flex flex-col overflow-hidden bg-gray-50 transition-colors duration-300 dark:bg-gray-900"
        >
            <!-- Top Navbar -->
            <Header @toggle-sidebar="toggleMobile" />
            <!-- Dashboard Content -->
            <main class="main-content flex-1 overflow-auto p-3 sm:p-4 md:p-6 lg:p-8">
                <RouterView />
            </main>
        </div>
    </div>

    <div class="fixed inset-0 bg-black/30 lg:hidden" v-if="isOpen" @click="closeMobile"></div>
</template>
