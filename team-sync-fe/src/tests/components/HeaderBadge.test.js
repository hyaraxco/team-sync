import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { ref } from "vue";
import { createRouter, createMemoryHistory } from "vue-router";

const mockUser = ref({ name: "Test User", email: "test@example.com", roles: [{ name: "staff" }] });

const authStoreMock = {
    user: mockUser.value,
    logout: vi.fn(),
};

const notificationStoreMock = {
    notifications: [],
    loading: false,
    error: null,
    unreadCount: 3,
    markingAllRead: false,
    fetchNotifications: vi.fn(),
    fetchUnreadCount: vi.fn(),
    fetchLatestNotifications: vi.fn(),
    markAllAsRead: vi.fn(),
    markNotificationAsRead: vi.fn(),
};

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => authStoreMock,
}));

vi.mock("@/stores/notifications", () => ({
    useNotificationStore: () => notificationStoreMock,
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        info: vi.fn(),
        success: vi.fn(),
        error: vi.fn(),
    }),
}));

vi.mock("@/composables/useDarkMode", () => ({
    useDarkMode: () => ({
        isDark: false,
        toggle: vi.fn(),
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: (store) => {
            if (store === authStoreMock) {
                return { user: mockUser };
            }
            return store;
        },
    };
});

import Header from "@/components/admin/Header.vue";

const router = createRouter({
    history: createMemoryHistory(),
    routes: [
        { path: "/admin/dashboard", name: "admin.dashboard", component: { template: "<div />" } },
        { path: "/staff/profile", name: "staffMember.profile", component: { template: "<div />" } },
    ],
});

const factory = async () => {
    await router.push("/admin/dashboard");
    await router.isReady();
    return mount(Header, {
        global: {
            plugins: [router],
            stubs: { RouterLink: true, NotificationPanel: true },
        },
    });
};

describe("Header - Notification Badge Standardization", () => {
    it("renders red badge with bg-danger-500 class", async () => {
        const wrapper = await factory();
        const badge = wrapper.find('[data-testid="header-notification-unread-badge"]');
        expect(badge.exists()).toBe(true);
        expect(badge.classes()).toContain("bg-danger-500");
    });

    it("badge has rounded-full class", async () => {
        const wrapper = await factory();
        const badge = wrapper.find('[data-testid="header-notification-unread-badge"]');
        expect(badge.classes()).toContain("rounded-full");
    });

    it("badge has consistent positioning (-right-1 -top-1)", async () => {
        const wrapper = await factory();
        const badge = wrapper.find('[data-testid="header-notification-unread-badge"]');
        expect(badge.classes()).toContain("-right-1");
        expect(badge.classes()).toContain("-top-1");
    });

    it("badge displays formatted unread count", async () => {
        const wrapper = await factory();
        const badge = wrapper.find('[data-testid="header-notification-unread-badge"]');
        expect(badge.text()).toBe("3");
    });
});
