import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useNotificationStore = defineStore("notifications", {
    state: () => ({
        notifications: [],
        loading: false,
        error: null,
        lastFetchedAt: null,
        unreadCount: 0,
        unreadCountLoading: false,
        markingReadIds: [],
        meta: null,
        markingAllRead: false,
    }),

    getters: {
        isMarkingAsRead: (state) => (notificationId) => state.markingReadIds.includes(String(notificationId)),
    },

    actions: {
        async fetchUnreadCount() {
            this.unreadCountLoading = true;

            try {
                const response = await axiosInstance.get("/my-notifications/unread-count");
                const count = Number(response.data?.data?.unread_count ?? 0);
                this.unreadCount = Number.isFinite(count) && count >= 0 ? Math.floor(count) : 0;

                return this.unreadCount;
            } catch {
                return this.unreadCount;
            } finally {
                this.unreadCountLoading = false;
            }
        },

        async fetchLatestNotifications(limit = 5) {
            this.loading = true;
            this.error = null;

            const parsedLimit = Number(limit);
            const safeLimit = Number.isFinite(parsedLimit) && parsedLimit > 0 ? parsedLimit : 5;

            try {
                const response = await axiosInstance.get("/my-notifications", {
                    params: { per_page: safeLimit, page: 1 },
                });

                const data = response.data?.data;
                // Support both paginated and legacy response shapes
                const items = Array.isArray(data?.items) ? data.items : Array.isArray(data) ? data : [];

                this.notifications = items.slice(0, safeLimit);
                this.meta = data?.meta ?? null;
                this.lastFetchedAt = new Date().toISOString();

                return this.notifications;
            } catch (error) {
                this.error = handleError(error);
                this.notifications = [];

                return [];
            } finally {
                this.loading = false;
            }
        },

        async fetchNotificationsPaginated({ page = 1, perPage = 10 } = {}) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get("/my-notifications", {
                    params: { per_page: perPage, page },
                });

                const data = response.data?.data;
                const items = Array.isArray(data?.items) ? data.items : [];

                this.notifications = items;
                this.meta = data?.meta ?? null;
                this.lastFetchedAt = new Date().toISOString();

                return { items, meta: this.meta };
            } catch (error) {
                this.error = handleError(error);
                this.notifications = [];

                return { items: [], meta: null };
            } finally {
                this.loading = false;
            }
        },

        async markAllAsRead() {
            this.markingAllRead = true;
            this.error = null;

            try {
                const response = await axiosInstance.post("/my-notifications/mark-all-read");

                // Update local state
                this.notifications = this.notifications.map((n) => ({
                    ...n,
                    is_read: true,
                    read_at: n.read_at || new Date().toISOString(),
                }));
                this.unreadCount = 0;

                return response.data?.data;
            } catch (error) {
                this.error = handleError(error);
                return null;
            } finally {
                this.markingAllRead = false;
            }
        },

        async markNotificationAsRead(notificationId) {
            const notificationKey = String(notificationId ?? "");
            if (!notificationKey) {
                return null;
            }

            const existing = this.notifications.find((notification) => String(notification.id) === notificationKey);
            const wasUnread = Boolean(existing && !existing.is_read);

            if (existing?.is_read) {
                return existing;
            }

            this.error = null;
            this.markingReadIds = Array.from(new Set([...this.markingReadIds, notificationKey]));

            try {
                const response = await axiosInstance.post(`/my-notifications/${notificationKey}/mark-as-read`);

                const updatedNotification = response.data?.data ?? null;

                this.notifications = this.notifications.map((notification) => {
                    if (String(notification.id) !== notificationKey) {
                        return notification;
                    }

                    if (updatedNotification) {
                        return {
                            ...notification,
                            ...updatedNotification,
                            is_read: true,
                        };
                    }

                    return {
                        ...notification,
                        is_read: true,
                        read_at: new Date().toISOString(),
                    };
                });

                if (wasUnread && this.unreadCount > 0) {
                    this.unreadCount -= 1;
                }

                return updatedNotification;
            } catch (error) {
                this.error = handleError(error);

                return null;
            } finally {
                this.markingReadIds = this.markingReadIds.filter((id) => id !== notificationKey);
            }
        },
    },
});
