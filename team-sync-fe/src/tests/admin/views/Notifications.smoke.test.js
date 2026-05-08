import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const { notificationStoreMock, routerPushMock } = vi.hoisted(() => ({
    notificationStoreMock: {
        notifications: [],
        loading: false,
        error: null,
        meta: null,
        markingAllRead: false,
        fetchNotificationsPaginated: vi.fn(),
        markAllAsRead: vi.fn(),
    },
    routerPushMock: vi.fn(),
}));

vi.mock("@/stores/notifications", () => ({
    useNotificationStore: () => notificationStoreMock,
}));

vi.mock("vue-router", () => ({
    useRouter: () => ({
        push: routerPushMock,
    }),
}));

import Notifications from "@/views/admin/Notifications.vue";

const flushPromises = () => new Promise((resolve) => setTimeout(resolve, 0));
const flushUi = async () => {
    await flushPromises();
    await nextTick();
    await flushPromises();
};

const factory = () => mount(Notifications);

describe("Notifications view smoke", () => {
    beforeEach(() => {
        notificationStoreMock.notifications = [];
        notificationStoreMock.loading = false;
        notificationStoreMock.error = null;
        notificationStoreMock.meta = null;
        notificationStoreMock.markingAllRead = false;
        notificationStoreMock.fetchNotificationsPaginated.mockReset().mockResolvedValue({ items: [], meta: null });
        notificationStoreMock.markAllAsRead.mockReset().mockResolvedValue(null);
        routerPushMock.mockReset().mockResolvedValue(undefined);
    });

    it("fetches notifications on mount and renders feed rows", async () => {
        notificationStoreMock.notifications = [
            {
                id: "n-1",
                title: "Payroll update",
                body: "Your payroll summary is ready.",
                action_url: "/admin/my-payroll/12",
                is_read: false,
                created_at: "2026-04-14T09:00:00Z",
            },
            {
                id: "n-2",
                title: "Task assigned",
                body: "A new task was assigned to you.",
                action_url: "/admin/projects/77",
                is_read: true,
                created_at: "2026-04-14T08:00:00Z",
            },
        ];

        const wrapper = factory();
        await flushUi();

        expect(notificationStoreMock.fetchNotificationsPaginated).toHaveBeenCalledWith({ page: 1, perPage: 15 });
        expect(wrapper.text()).toContain("All Notifications");
        expect(wrapper.text()).toContain("Payroll update");
        expect(wrapper.text()).toContain("Task assigned");
    });

    it("refreshes notifications feed with refresh button", async () => {
        const wrapper = factory();
        await flushUi();

        await wrapper
            .findAll("button")
            .find((button) => button.text().includes("Refresh"))
            .trigger("click");

        expect(notificationStoreMock.fetchNotificationsPaginated).toHaveBeenCalledTimes(2);
        expect(notificationStoreMock.fetchNotificationsPaginated).toHaveBeenNthCalledWith(2, { page: 1, perPage: 15 });
    });

    it("navigates to notification action_url when row is clicked", async () => {
        notificationStoreMock.notifications = [
            {
                id: "task-n-3",
                title: "Open project detail",
                body: "Navigate to related project",
                action_url: "/admin/projects/77",
                is_read: false,
                created_at: "2026-04-14T10:00:00Z",
            },
        ];

        const wrapper = factory();
        await flushUi();

        const rowButton = wrapper.findAll("button").find((button) => button.text().includes("Open project detail"));

        expect(rowButton).toBeDefined();
        await rowButton.trigger("click");

        expect(routerPushMock).toHaveBeenCalledWith("/admin/projects/77");
    });

    it("shows empty state when notifications list is empty", async () => {
        const wrapper = factory();
        await flushUi();

        expect(wrapper.text()).toContain("No notifications yet.");
        expect(wrapper.text()).toContain("New updates will appear here.");
    });

    it("retries the current page from the error state", async () => {
        notificationStoreMock.error = "Unable to load notifications";
        notificationStoreMock.meta = { current_page: 2, last_page: 3 };

        const wrapper = factory();
        await flushUi();

        await wrapper
            .findAll("button")
            .find((button) => button.text().includes("Try again"))
            .trigger("click");

        expect(notificationStoreMock.fetchNotificationsPaginated).toHaveBeenCalledTimes(2);
        expect(notificationStoreMock.fetchNotificationsPaginated).toHaveBeenNthCalledWith(2, { page: 1, perPage: 15 });
    });
});
