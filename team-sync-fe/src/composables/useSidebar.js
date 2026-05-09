import { ref, provide, inject } from "vue";

const SIDEBAR_KEY = Symbol("sidebar");
const STORAGE_KEY = "sidebar-collapsed";

export function provideSidebar() {
    const isOpen = ref(true); // mobile open/close
    const isCollapsed = ref(localStorage.getItem(STORAGE_KEY) === "true");

    function toggleCollapse() {
        isCollapsed.value = !isCollapsed.value;
        localStorage.setItem(STORAGE_KEY, String(isCollapsed.value));
    }

    function openMobile() {
        isOpen.value = true;
    }

    function closeMobile() {
        isOpen.value = false;
    }

    function toggleMobile() {
        isOpen.value = !isOpen.value;
    }

    const context = {
        isOpen,
        isCollapsed,
        toggleCollapse,
        openMobile,
        closeMobile,
        toggleMobile,
    };

    provide(SIDEBAR_KEY, context);
    return context;
}

export function useSidebar() {
    const context = inject(SIDEBAR_KEY);
    if (!context) {
        throw new Error("useSidebar must be used within a SidebarProvider (provideSidebar)");
    }
    return context;
}
