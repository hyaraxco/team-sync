import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useNotificationStore } from '@/stores/notifications';
import { axiosInstance } from '@/plugins/axios';

vi.mock('@/plugins/axios', () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

describe('Notifications Store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useNotificationStore();
        vi.clearAllMocks();
    });

    it('fetchUnreadCount sets unreadCount on success', async () => {
        axiosInstance.get.mockResolvedValueOnce({
            data: {
                data: {
                    unread_count: '4.9',
                },
            },
        });

        const result = await store.fetchUnreadCount();

        expect(axiosInstance.get).toHaveBeenCalledWith('/my-notifications/unread-count');
        expect(result).toBe(4);
        expect(store.unreadCount).toBe(4);
        expect(store.unreadCountLoading).toBe(false);
    });

    it('fetchUnreadCount keeps current unreadCount on failure', async () => {
        store.unreadCount = 7;
        axiosInstance.get.mockRejectedValueOnce(new Error('network'));

        const result = await store.fetchUnreadCount();

        expect(result).toBe(7);
        expect(store.unreadCount).toBe(7);
        expect(store.unreadCountLoading).toBe(false);
    });

    it('fetchLatestNotifications stores latest notifications and timestamp', async () => {
        const payload = [
            { id: 1, title: 'A' },
            { id: 2, title: 'B' },
            { id: 3, title: 'C' },
        ];
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        const result = await store.fetchLatestNotifications(2);

        expect(axiosInstance.get).toHaveBeenCalledWith('/my-notifications', {
            params: { limit: 2 },
        });
        expect(result).toEqual(payload.slice(0, 2));
        expect(store.notifications).toEqual(payload.slice(0, 2));
        expect(store.lastFetchedAt).not.toBe(null);
        expect(store.loading).toBe(false);
        expect(store.error).toBe(null);
    });

    it('fetchLatestNotifications falls back to safe default limit', async () => {
        const payload = new Array(7).fill(null).map((_, index) => ({ id: index + 1 }));
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        await store.fetchLatestNotifications(0);

        expect(axiosInstance.get).toHaveBeenCalledWith('/my-notifications', {
            params: { limit: 5 },
        });
        expect(store.notifications).toHaveLength(5);
    });

    it('fetchLatestNotifications sets error and empties notifications on failure', async () => {
        const mockError = {
            response: {
                status: 500,
                data: { message: 'Failed to fetch notifications' },
            },
        };
        store.notifications = [{ id: 1 }];
        axiosInstance.get.mockRejectedValueOnce(mockError);

        const result = await store.fetchLatestNotifications(5);

        expect(result).toEqual([]);
        expect(store.error).toBe('Failed to fetch notifications');
        expect(store.notifications).toEqual([]);
        expect(store.loading).toBe(false);
    });

    it('markNotificationAsRead returns null when notification id is empty', async () => {
        const result = await store.markNotificationAsRead(null);

        expect(result).toBe(null);
        expect(axiosInstance.post).not.toHaveBeenCalled();
    });

    it('markNotificationAsRead returns existing notification when already read', async () => {
        store.notifications = [{ id: 10, is_read: true }];

        const result = await store.markNotificationAsRead(10);

        expect(result).toEqual({ id: 10, is_read: true });
        expect(axiosInstance.post).not.toHaveBeenCalled();
    });

    it('markNotificationAsRead calls api, updates notification, and decreases unread count', async () => {
        store.notifications = [{ id: 15, is_read: false, title: 'Before' }];
        store.unreadCount = 3;
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                data: { id: 15, title: 'After', read_at: '2026-01-01T00:00:00.000Z' },
            },
        });

        const result = await store.markNotificationAsRead(15);

        expect(axiosInstance.post).toHaveBeenCalledWith('/my-notifications/15/mark-as-read');
        expect(result).toEqual({ id: 15, title: 'After', read_at: '2026-01-01T00:00:00.000Z' });
        expect(store.notifications[0]).toEqual({
            id: 15,
            is_read: true,
            title: 'After',
            read_at: '2026-01-01T00:00:00.000Z',
        });
        expect(store.unreadCount).toBe(2);
        expect(store.markingReadIds).toEqual([]);
        expect(store.isMarkingAsRead(15)).toBe(false);
    });

    it('markNotificationAsRead sets error and cleans loading ids on failure', async () => {
        const mockError = {
            response: {
                status: 404,
                data: { message: 'Notification not found' },
            },
        };
        store.notifications = [{ id: 88, is_read: false }];
        axiosInstance.post.mockRejectedValueOnce(mockError);

        const result = await store.markNotificationAsRead(88);

        expect(result).toBe(null);
        expect(store.error).toBe('Notification not found');
        expect(store.markingReadIds).toEqual([]);
    });
});
