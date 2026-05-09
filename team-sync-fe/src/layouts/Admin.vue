<script setup>
import Sidebar from "@/components/admin/Sidebar.vue";
import Header from "@/components/admin/Header.vue";
import { provideSidebar } from "@/composables/useSidebar";

const { isOpen, toggleMobile, closeMobile } = provideSidebar();
</script>

<template>
    <div class="flex h-screen overflow-hidden">
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
