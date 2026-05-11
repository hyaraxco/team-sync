import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";

import NotificationPanel from "@/components/admin/NotificationPanel.vue";

const defaultProps = {
    open: true,
    panelId: "header-notification-panel",
    notifications: [],
    loading: false,
    error: null,
};

const factory = (props = {}) =>
    mount(NotificationPanel, {
        props: {
            ...defaultProps,
            ...props,
        },
    });

describe("NotificationPanel smoke", () => {
    it("is hidden when open is false", () => {
        const wrapper = factory({ open: false });

        expect(wrapper.get('[data-testid="header-notification-panel"]').classes()).toContain("hidden");
    });

    it("shows loading state", () => {
        const wrapper = factory({ loading: true });

        expect(wrapper.get('[data-testid="notification-loading"]').text()).toContain("Loading notifications");
    });

    it("shows empty state", () => {
        const wrapper = factory();

        expect(wrapper.get('[data-testid="notification-empty"]').text()).toContain("No notifications yet");
    });

    it("renders up to five latest notifications", () => {
        const wrapper = factory({
            notifications: [
                { id: "n-1", title: "One", body: "Body", created_at: "2026-04-13T09:00:00Z" },
                { id: "n-2", title: "Two", body: "Body", created_at: "2026-04-13T09:10:00Z" },
                { id: "n-3", title: "Three", body: "Body", created_at: "2026-04-13T09:20:00Z" },
                { id: "n-4", title: "Four", body: "Body", created_at: "2026-04-13T09:30:00Z" },
                { id: "n-5", title: "Five", body: "Body", created_at: "2026-04-13T09:40:00Z" },
                { id: "n-6", title: "Six", body: "Body", created_at: "2026-04-13T09:50:00Z" },
            ],
        });

        const items = wrapper.findAll('[data-testid^="notification-item-"]');

        expect(items).toHaveLength(5);
        expect(wrapper.text()).toContain("One");
        expect(wrapper.text()).toContain("Five");
        expect(wrapper.text()).not.toContain("Six");
    });

    it("emits retry when retry button is clicked", async () => {
        const wrapper = factory({
            error: "Something went wrong",
        });

        await wrapper.get('[data-testid="notification-retry"]').trigger("click");

        expect(wrapper.emitted("retry")).toHaveLength(1);
    });

    it("emits select when notification item is clicked", async () => {
        const wrapper = factory({
            notifications: [
                {
                    id: "n-9",
                    title: "Click me",
                    body: "Navigate",
                    is_read: false,
                    action_url: "/admin/my-payroll/9",
                    created_at: "2026-04-13T09:00:00Z",
                },
            ],
        });

        await wrapper.get('[data-testid="notification-select-n-9"]').trigger("click");

        const emitted = wrapper.emitted("select");
        expect(emitted).toHaveLength(1);
        expect(emitted[0][0]).toMatchObject({ id: "n-9", action_url: "/admin/my-payroll/9" });
        expect(wrapper.get('[data-testid="notification-item-n-9"]').exists()).toBe(true);
    });

    it("renders task assignment payload and preserves click contract", async () => {
        const wrapper = factory({
            notifications: [
                {
                    id: "task-n-1",
                    category: "task",
                    title: "New Task Assigned",
                    body: "Prepare sprint report in Notifications Project has been assigned to you.",
                    action_url: "/admin/projects/77",
                    is_read: false,
                    created_at: "2026-04-13T10:30:00Z",
                    data: {
                        task_id: 301,
                        project_id: 77,
                        is_reassignment: false,
                    },
                },
            ],
        });

        expect(wrapper.text()).toContain("New Task Assigned");
        expect(wrapper.text()).toContain("Prepare sprint report");
        expect(wrapper.get('[data-testid="notification-item-task-n-1"]').exists()).toBe(true);

        await wrapper.get('[data-testid="notification-select-task-n-1"]').trigger("click");

        const emitted = wrapper.emitted("select");
        expect(emitted).toHaveLength(1);
        expect(emitted[0][0]).toMatchObject({
            id: "task-n-1",
            action_url: "/admin/projects/77",
            category: "task",
        });
    });
});
