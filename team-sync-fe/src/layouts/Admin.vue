<script setup>
import Sidebar from "@/components/admin/Sidebar.vue";
import Header from "@/components/admin/Header.vue";
import { ref } from "vue";

const isSidebarOpen = ref(false);

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value;
};

const closeSidebar = () => {
  isSidebarOpen.value = false;
};
</script>

<template>
  <div class="flex h-screen overflow-hidden">
    <!-- Sidebar Slot - can be replaced with custom content -->
    <slot name="sidebar">
      <Sidebar :isOpen="isSidebarOpen" @navigate="closeSidebar" />
    </slot>

    <!-- Main Content -->
    <div id="main-content" class="flex-1 flex flex-col overflow-hidden">
      <!-- Top Navbar -->
      <Header @toggle-sidebar="toggleSidebar" />
      <!-- Dashboard Content -->
      <main class="main-content flex-1 overflow-auto p-3">
        <RouterView />
      </main>
    </div>
  </div>

  <div
    class="fixed inset-0 bg-black/30 lg:hidden"
    v-if="isSidebarOpen"
    @click="closeSidebar"
  ></div>
</template>
