<script setup>
import Sidebar from "@/components/admin/Sidebar.vue";
import Header from "@/components/admin/Header.vue";
import ErrorBoundary from "@/components/common/ErrorBoundary.vue";
import { provideSidebar } from "@/composables/useSidebar";
import { nextTick, onMounted, onUnmounted, ref, watch } from "vue";

const SIDEBAR_ID = "sidebar";
const DESKTOP_MEDIA_QUERY = "(min-width: 1024px)";

const { isOpen, toggleMobile, closeMobile } = provideSidebar();
const headerRef = ref(null);
const isDesktop = ref(false);
let desktopQueryList = null;

// Every close path (Escape, close button, backdrop, navigation) funnels through
// isOpen, so focus returns to the trigger from one place.
watch(isOpen, async (open, wasOpen) => {
    if (open || !wasOpen || isDesktop.value) {
        return;
    }

    const drawer = document.getElementById(SIDEBAR_ID);
    const active = document.activeElement;
    const focusIsLost = !active || active === document.body || drawer?.contains(active);

    await nextTick();

    if (focusIsLost) {
        headerRef.value?.focusMenuTrigger();
    }
});

// Desktop keeps its own persistent sidebar, so a drawer left open by a resize
// must close instead of becoming a modal over the desktop layout.
const handleDesktopChange = (event) => {
    isDesktop.value = event.matches;

    if (event.matches && isOpen.value) {
        closeMobile();
    }
};

const handleKeydown = (event) => {
    if (event.key !== "Escape" || !isOpen.value) {
        return;
    }

    closeMobile();
};

onMounted(() => {
    document.addEventListener("keydown", handleKeydown);

    if (typeof window.matchMedia === "function") {
        desktopQueryList = window.matchMedia(DESKTOP_MEDIA_QUERY);
        isDesktop.value = desktopQueryList.matches;
        desktopQueryList.addEventListener("change", handleDesktopChange);
    }
});

onUnmounted(() => {
    document.removeEventListener("keydown", handleKeydown);
    desktopQueryList?.removeEventListener("change", handleDesktopChange);
    desktopQueryList = null;
});
</script>

<template>
    <div class="flex min-h-[100dvh] overflow-hidden">
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
            class="flex-1 flex flex-col overflow-hidden transition-colors duration-200"
            style="background-color: var(--main-bg)"
        >
            <!-- Top Navbar -->
            <Header ref="headerRef" :is-open="isOpen" :sidebar-id="SIDEBAR_ID" @toggle-sidebar="toggleMobile" />
            <!-- Dashboard Content -->
            <main class="main-content flex-1 overflow-auto p-3 sm:p-4 md:p-6 lg:p-8">
                <ErrorBoundary>
                    <RouterView />
                </ErrorBoundary>
            </main>
        </div>
    </div>

    <div
        v-if="isOpen"
        data-testid="sidebar-backdrop"
        aria-hidden="true"
        class="fixed inset-0 bg-black/30 lg:hidden"
        @click="closeMobile"
    ></div>
</template>
