import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
    routeState,
    routerPushMock,
    authStoreMock,
    notificationStoreRaw,
    notificationStoreHolder,
    authRefs,
    routerLinkStub,
    toastInfoMock,
} = vi.hoisted(() => ({
    routeState: {
        name: "admin.dashboard",
    },
    routerPushMock: vi.fn(),
    authStoreMock: {
        logout: vi.fn(),
    },
    notificationStoreRaw: {
        notifications: [],
        loading: false,
        error: null,
        unreadCount: 0,
        fetchUnreadCount: vi.fn(),
        fetchLatestNotifications: vi.fn(),
        markNotificationAsRead: vi.fn(),
    },
    notificationStoreHolder: { current: null },
    authRefs: {
        user: {
            __v_isRef: true,
            value: {
                name: "Taylor Admin",
                email: "taylor@example.com",
                roles: ["finance"],
                profile_photo: null,
            },
        },
        loading: {
            __v_isRef: true,
            value: false,
        },
    },
    routerLinkStub: {
        name: "RouterLink",
        props: ["to"],
        emits: ["click"],
        template:
            '<a class="router-link-stub" :data-route-name="to && to.name" @click="$emit(\'click\')"><slot /></a>',
    },
    toastInfoMock: vi.fn(),
}));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => authStoreMock,
}));

vi.mock("@/stores/notifications", async () => {
    const { reactive } = await import("vue");
    notificationStoreHolder.current = reactive(notificationStoreRaw);

    return {
        useNotificationStore: () => notificationStoreHolder.current,
    };
});

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        info: toastInfoMock,
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();

    return {
        ...actual,
        storeToRefs: () => authRefs,
    };
});

vi.mock("vue-router", () => ({
    useRoute: () => routeState,
    useRouter: () => ({
        push: routerPushMock,
    }),
    RouterLink: routerLinkStub,
}));

import Header from "@/components/admin/Header.vue";

const factory = () =>
    mount(Header, {
        global: {
            stubs: {
                RouterLink: routerLinkStub,
            },
        },
    });

const openDropdown = async (wrapper) => {
    await wrapper.get('[data-testid="header-profile-toggle"]').trigger("click");
};

describe("Header smoke", () => {
    beforeEach(() => {
        routeState.name = "admin.dashboard";
        authRefs.user.value = {
            name: "Taylor Admin",
            email: "taylor@example.com",
            roles: ["finance"],
            profile_photo: null,
        };
        authRefs.loading.value = false;
        authStoreMock.logout.mockReset().mockResolvedValue(undefined);
        notificationStoreHolder.current.notifications = [];
        notificationStoreHolder.current.loading = false;
        notificationStoreHolder.current.error = null;
        notificationStoreHolder.current.unreadCount = 0;
        notificationStoreHolder.current.fetchUnreadCount.mockReset().mockResolvedValue(0);
        notificationStoreHolder.current.fetchLatestNotifications.mockReset().mockResolvedValue([]);
        notificationStoreHolder.current.markNotificationAsRead.mockReset().mockResolvedValue({
            id: "n-1",
            is_read: true,
            read_at: "2026-04-13T10:00:00Z",
        });
        routerPushMock.mockReset().mockResolvedValue(undefined);
        toastInfoMock.mockReset();
    });

    it("shows actionable dropdown items and removes placeholders", async () => {
        const wrapper = factory();

        await openDropdown(wrapper);

        expect(wrapper.get('[data-testid="header-mobile-menu-toggle"]').attributes("aria-label")).toBe(
            "Toggle sidebar",
        );
        expect(wrapper.get('[data-testid="header-profile-toggle"]').attributes("aria-haspopup")).toBe("menu");
        expect(wrapper.get('[data-testid="header-profile-toggle"]').attributes("aria-expanded")).toBe("true");
        expect(wrapper.get('[data-testid="header-notification-toggle"]').attributes("aria-haspopup")).toBe("dialog");
        expect(wrapper.get('[data-testid="header-profile-menu-item"]').attributes("data-route-name")).toBe(
            "staffMember.profile",
        );
        expect(wrapper.text()).toContain("Profile");
        expect(wrapper.text()).toContain("Sign Out");
        expect(wrapper.text()).not.toContain("Pengaturan Sistem");
        expect(wrapper.text()).not.toContain("Bantuan");
        expect(wrapper.find('a[href="#"]').exists()).toBe(false);
    });

    it("renders the route title without claiming page-level heading semantics", () => {
        const wrapper = factory();

        expect(wrapper.findAll("h1")).toHaveLength(0);
        expect(wrapper.text()).toContain("Dashboard");
    });

    it("closes dropdown after profile link click", async () => {
        const wrapper = factory();

        await openDropdown(wrapper);
        await wrapper.get('[data-testid="header-profile-menu-item"]').trigger("click");
        await nextTick();

        expect(wrapper.get('[data-testid="header-account-menu"]').classes()).toContain("hidden");
    });

    it("calls logout when sign out is clicked", async () => {
        const wrapper = factory();

        await openDropdown(wrapper);
        await wrapper.get("button.text-red-600").trigger("click");
        await nextTick();

        expect(authStoreMock.logout).toHaveBeenCalledTimes(1);
        expect(wrapper.get('[data-testid="header-account-menu"]').classes()).toContain("hidden");
    });

    it("opens notification panel and fetches latest notifications", async () => {
        const wrapper = factory();

        await wrapper.get('[data-testid="header-notification-toggle"]').trigger("click");
        await nextTick();

        expect(notificationStoreHolder.current.fetchLatestNotifications).toHaveBeenCalledWith(5);
        expect(notificationStoreHolder.current.fetchUnreadCount).toHaveBeenCalled();
        expect(wrapper.get('[data-testid="header-notification-panel"]').classes()).not.toContain("hidden");
    });

    it("fetches unread count on mount", async () => {
        factory();
        await nextTick();

        expect(notificationStoreHolder.current.fetchUnreadCount).toHaveBeenCalledTimes(1);
    });

    it("shows unread badge when notifications have unread items", async () => {
        notificationStoreHolder.current.notifications = [
            {
                id: "n-1",
                title: "Unread",
                body: "New update",
                is_read: false,
                action_url: "/admin/my-payroll/1",
                created_at: "2026-04-13T10:00:00Z",
            },
        ];
        notificationStoreHolder.current.unreadCount = 1;

        const wrapper = factory();
        await nextTick();

        expect(wrapper.get('[data-testid="header-notification-unread-badge"]').text()).toBe("1");
    });

    it("announces unread count in notification button label", async () => {
        notificationStoreHolder.current.unreadCount = 11;

        const wrapper = factory();
        await nextTick();

        expect(wrapper.get('[data-testid="header-notification-toggle"]').attributes("aria-label")).toBe(
            "Notifications, 11 new",
        );
    });

    it("caps very large unread count at 99+", async () => {
        notificationStoreHolder.current.unreadCount = 120;

        const wrapper = factory();
        await nextTick();

        expect(wrapper.get('[data-testid="header-notification-unread-badge"]').text()).toBe("99+");
        expect(wrapper.get('[data-testid="header-notification-toggle"]').attributes("aria-label")).toBe(
            "Notifications, 99+ new",
        );
    });

    it("marks notification as read and routes to action url when selected", async () => {
        notificationStoreHolder.current.notifications = [
            {
                id: "n-1",
                title: "Payroll update",
                body: "Click to open payslip",
                is_read: false,
                action_url: "/admin/my-payroll/12",
                created_at: "2026-04-13T10:10:00Z",
            },
        ];
        notificationStoreHolder.current.unreadCount = 1;

        const wrapper = factory();

        await wrapper.get('[data-testid="header-notification-toggle"]').trigger("click");
        await nextTick();
        await wrapper.get('[data-testid="notification-select-n-1"]').trigger("click");
        await nextTick();

        expect(notificationStoreHolder.current.markNotificationAsRead).toHaveBeenCalledWith("n-1");
        expect(notificationStoreHolder.current.fetchUnreadCount.mock.calls.length).toBeGreaterThanOrEqual(2);
        expect(routerPushMock).toHaveBeenCalledWith("/admin/my-payroll/12");
        expect(wrapper.get('[data-testid="header-notification-panel"]').classes()).toContain("hidden");
    });

    it("routes task assignment notification to project detail", async () => {
        notificationStoreHolder.current.notifications = [
            {
                id: "task-n-3",
                category: "task",
                title: "New Task Assigned",
                body: "Prepare sprint report in Notifications Project has been assigned to you.",
                is_read: false,
                action_url: "/admin/projects/77",
                created_at: "2026-04-13T10:30:00Z",
            },
        ];
        notificationStoreHolder.current.unreadCount = 1;

        const wrapper = factory();

        await wrapper.get('[data-testid="header-notification-toggle"]').trigger("click");
        await nextTick();
        await wrapper.get('[data-testid="notification-select-task-n-3"]').trigger("click");
        await nextTick();

        expect(notificationStoreHolder.current.markNotificationAsRead).toHaveBeenCalledWith("task-n-3");
        expect(routerPushMock).toHaveBeenCalledWith("/admin/projects/77");
        expect(wrapper.get('[data-testid="header-notification-panel"]').classes()).toContain("hidden");
    });

    it("closes notification panel on outside click", async () => {
        const wrapper = factory();

        await wrapper.get('[data-testid="header-notification-toggle"]').trigger("click");
        await nextTick();

        document.dispatchEvent(new MouseEvent("click", { bubbles: true }));
        await nextTick();

        expect(wrapper.get('[data-testid="header-notification-panel"]').classes()).toContain("hidden");
    });

    it("shows toast when unread count increases during polling", async () => {
        vi.useFakeTimers();

        notificationStoreHolder.current.unreadCount = 2;
        notificationStoreHolder.current.fetchUnreadCount.mockImplementation(async () => {
            return notificationStoreHolder.current.unreadCount;
        });

        const wrapper = factory();
        await vi.advanceTimersByTimeAsync(0);
        await nextTick();

        expect(toastInfoMock).not.toHaveBeenCalled();

        notificationStoreHolder.current.unreadCount = 5;
        await vi.advanceTimersByTimeAsync(15000);
        await nextTick();

        expect(toastInfoMock).toHaveBeenCalledWith("New Notification", "You have new notifications");

        vi.useRealTimers();
        wrapper.unmount();
    });

    it("does not show toast on first poll after mount when count stays same", async () => {
        vi.useFakeTimers();

        notificationStoreHolder.current.unreadCount = 5;
        notificationStoreHolder.current.fetchUnreadCount.mockImplementation(async () => {
            return notificationStoreHolder.current.unreadCount;
        });

        const wrapper = factory();
        await vi.advanceTimersByTimeAsync(15000);
        await nextTick();

        expect(toastInfoMock).not.toHaveBeenCalled();

        vi.useRealTimers();
        wrapper.unmount();
    });

    it("does not show toast when unread count stays the same", async () => {
        vi.useFakeTimers();

        notificationStoreHolder.current.unreadCount = 2;
        notificationStoreHolder.current.fetchUnreadCount.mockImplementation(async () => {
            return notificationStoreHolder.current.unreadCount;
        });

        const wrapper = factory();
        await vi.advanceTimersByTimeAsync(0);
        await nextTick();

        await vi.advanceTimersByTimeAsync(15000);
        await nextTick();

        expect(toastInfoMock).not.toHaveBeenCalled();

        vi.useRealTimers();
        wrapper.unmount();
    });

    it("does not show toast when unread count decreases", async () => {
        vi.useFakeTimers();

        notificationStoreHolder.current.unreadCount = 5;
        notificationStoreHolder.current.fetchUnreadCount.mockImplementation(async () => {
            return notificationStoreHolder.current.unreadCount;
        });

        const wrapper = factory();
        await vi.advanceTimersByTimeAsync(0);
        await nextTick();

        notificationStoreHolder.current.unreadCount = 2;
        await vi.advanceTimersByTimeAsync(15000);
        await nextTick();

        expect(toastInfoMock).not.toHaveBeenCalled();

        vi.useRealTimers();
        wrapper.unmount();
    });

    it("shows toast on subsequent count increase after first increase", async () => {
        vi.useFakeTimers();

        notificationStoreHolder.current.unreadCount = 0;
        notificationStoreHolder.current.fetchUnreadCount.mockImplementation(async () => {
            return notificationStoreHolder.current.unreadCount;
        });

        const wrapper = factory();
        await vi.advanceTimersByTimeAsync(0);
        await nextTick();

        // First increase from 0 is guarded (simulates page load with existing notifications)
        notificationStoreHolder.current.unreadCount = 2;
        await vi.advanceTimersByTimeAsync(15000);
        await nextTick();

        expect(toastInfoMock).not.toHaveBeenCalled();

        // Second increase from non-zero triggers toast
        notificationStoreHolder.current.unreadCount = 5;
        await vi.advanceTimersByTimeAsync(15000);
        await nextTick();

        expect(toastInfoMock).toHaveBeenCalledTimes(1);
        expect(toastInfoMock).toHaveBeenCalledWith("New Notification", "You have new notifications");

        vi.useRealTimers();
        wrapper.unmount();
    });
});
