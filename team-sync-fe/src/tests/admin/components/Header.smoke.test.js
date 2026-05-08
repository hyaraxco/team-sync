import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const { routeState, routerPushMock, authStoreMock, notificationStoreMock, authRefs, routerLinkStub } = vi.hoisted(
    () => ({
        routeState: {
            name: "admin.dashboard",
        },
        routerPushMock: vi.fn(),
        authStoreMock: {
            logout: vi.fn(),
        },
        notificationStoreMock: {
            notifications: [],
            loading: false,
            error: null,
            unreadCount: 0,
            fetchUnreadCount: vi.fn(),
            fetchLatestNotifications: vi.fn(),
            markNotificationAsRead: vi.fn(),
        },
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
    }),
);

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => authStoreMock,
}));

vi.mock("@/stores/notifications", () => ({
    useNotificationStore: () => notificationStoreMock,
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
        notificationStoreMock.notifications = [];
        notificationStoreMock.loading = false;
        notificationStoreMock.error = null;
        notificationStoreMock.unreadCount = 0;
        notificationStoreMock.fetchUnreadCount.mockReset().mockResolvedValue(0);
        notificationStoreMock.fetchLatestNotifications.mockReset().mockResolvedValue([]);
        notificationStoreMock.markNotificationAsRead.mockReset().mockResolvedValue({
            id: "n-1",
            is_read: true,
            read_at: "2026-04-13T10:00:00Z",
        });
        routerPushMock.mockReset().mockResolvedValue(undefined);
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

        expect(notificationStoreMock.fetchLatestNotifications).toHaveBeenCalledWith(5);
        expect(notificationStoreMock.fetchUnreadCount).toHaveBeenCalled();
        expect(wrapper.get('[data-testid="header-notification-panel"]').classes()).not.toContain("hidden");
    });

    it("fetches unread count on mount", async () => {
        factory();
        await nextTick();

        expect(notificationStoreMock.fetchUnreadCount).toHaveBeenCalledTimes(1);
    });

    it("shows unread badge when notifications have unread items", async () => {
        notificationStoreMock.notifications = [
            {
                id: "n-1",
                title: "Unread",
                body: "New update",
                is_read: false,
                action_url: "/admin/my-payroll/1",
                created_at: "2026-04-13T10:00:00Z",
            },
        ];
        notificationStoreMock.unreadCount = 1;

        const wrapper = factory();
        await nextTick();

        expect(wrapper.get('[data-testid="header-notification-unread-badge"]').text()).toBe("1");
    });

    it("announces unread count in notification button label", async () => {
        notificationStoreMock.unreadCount = 11;

        const wrapper = factory();
        await nextTick();

        expect(wrapper.get('[data-testid="header-notification-toggle"]').attributes("aria-label")).toBe(
            "Notifications, 11 new",
        );
    });

    it("caps very large unread count at 99+", async () => {
        notificationStoreMock.unreadCount = 120;

        const wrapper = factory();
        await nextTick();

        expect(wrapper.get('[data-testid="header-notification-unread-badge"]').text()).toBe("99+");
        expect(wrapper.get('[data-testid="header-notification-toggle"]').attributes("aria-label")).toBe(
            "Notifications, 99+ new",
        );
    });

    it("marks notification as read and routes to action url when selected", async () => {
        notificationStoreMock.notifications = [
            {
                id: "n-1",
                title: "Payroll update",
                body: "Click to open payslip",
                is_read: false,
                action_url: "/admin/my-payroll/12",
                created_at: "2026-04-13T10:10:00Z",
            },
        ];
        notificationStoreMock.unreadCount = 1;

        const wrapper = factory();

        await wrapper.get('[data-testid="header-notification-toggle"]').trigger("click");
        await nextTick();
        await wrapper.get('[data-testid="notification-select-n-1"]').trigger("click");
        await nextTick();

        expect(notificationStoreMock.markNotificationAsRead).toHaveBeenCalledWith("n-1");
        expect(notificationStoreMock.fetchUnreadCount.mock.calls.length).toBeGreaterThanOrEqual(2);
        expect(routerPushMock).toHaveBeenCalledWith("/admin/my-payroll/12");
        expect(wrapper.get('[data-testid="header-notification-panel"]').classes()).toContain("hidden");
    });

    it("routes task assignment notification to project detail", async () => {
        notificationStoreMock.notifications = [
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
        notificationStoreMock.unreadCount = 1;

        const wrapper = factory();

        await wrapper.get('[data-testid="header-notification-toggle"]').trigger("click");
        await nextTick();
        await wrapper.get('[data-testid="notification-select-task-n-3"]').trigger("click");
        await nextTick();

        expect(notificationStoreMock.markNotificationAsRead).toHaveBeenCalledWith("task-n-3");
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
});
