import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useMeetingStore } from '@/stores/meeting';
import { axiosInstance } from '@/plugins/axios';
import { handleError } from '@/helpers/errorHelper';

vi.mock('@/plugins/axios', () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

vi.mock('@/helpers/errorHelper', () => ({
    handleError: vi.fn(),
}));

describe('Meeting Store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useMeetingStore();
        vi.clearAllMocks();
    });

    it('initializes with correct default state', () => {
        expect(store.meetings).toEqual([]);
        expect(store.upcomingMeetings).toEqual([]);
        expect(store.meta).toEqual({
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        });
        expect(store.loading).toBe(false);
        expect(store.loadingUpcoming).toBe(false);
        expect(store.error).toBe(null);
        expect(store.success).toBe(null);
    });

    it('fetchMeetingsPaginated calls endpoint and sets meetings + meta', async () => {
        const params = { row_per_page: 10, page: 2, search: 'sync' };
        const mockMeetings = [{ id: 1, title: 'Sprint Sync' }];
        const mockMeta = {
            current_page: 2,
            last_page: 5,
            per_page: 10,
            total: 50,
        };

        axiosInstance.get.mockResolvedValueOnce({
            data: {
                data: {
                    data: mockMeetings,
                    meta: mockMeta,
                },
            },
        });

        await store.fetchMeetingsPaginated(params);

        expect(axiosInstance.get).toHaveBeenCalledWith('/meetings/all/paginated', { params });
        expect(store.meetings).toEqual(mockMeetings);
        expect(store.meta).toEqual(mockMeta);
        expect(store.loading).toBe(false);
    });

    it('fetchUpcomingMeetings calls endpoint and sets upcomingMeetings', async () => {
        const params = { limit: 5 };
        const mockUpcoming = [{ id: 3, title: 'Townhall' }];

        axiosInstance.get.mockResolvedValueOnce({
            data: {
                data: mockUpcoming,
            },
        });

        await store.fetchUpcomingMeetings(params);

        expect(axiosInstance.get).toHaveBeenCalledWith('/meetings/upcoming', { params });
        expect(store.upcomingMeetings).toEqual(mockUpcoming);
        expect(store.loadingUpcoming).toBe(false);
    });

    it('createMeeting calls POST and sets success', async () => {
        const payload = {
            title: 'Company All Hands',
            scheduled_at: '2026-04-30 10:00:00',
            duration_minutes: 60,
        };

        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Meeting scheduled successfully',
                data: { id: 10 },
            },
        });

        const result = await store.createMeeting(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('meetings', payload);
        expect(store.success).toBe('Meeting scheduled successfully');
        expect(store.loading).toBe(false);
        expect(result).toEqual({
            data: {
                message: 'Meeting scheduled successfully',
                data: { id: 10 },
            },
        });
    });

    it('createMeeting handles error correctly', async () => {
        const payload = {
            title: '',
            scheduled_at: '',
        };
        const mockError = { response: { data: { message: 'Validation failed' } } };
        handleError.mockReturnValueOnce('Validation failed');
        axiosInstance.post.mockRejectedValueOnce(mockError);

        const result = await store.createMeeting(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('meetings', payload);
        expect(handleError).toHaveBeenCalledWith(mockError);
        expect(store.error).toBe('Validation failed');
        expect(store.loading).toBe(false);
        expect(result).toBeUndefined();
    });

    it('fetchMeeting calls endpoint and returns data', async () => {
        const meeting = {
            id: 77,
            title: '1:1 Review',
        };
        axiosInstance.get.mockResolvedValueOnce({
            data: {
                data: meeting,
            },
        });

        const result = await store.fetchMeeting(77);

        expect(axiosInstance.get).toHaveBeenCalledWith('meetings/77');
        expect(result).toEqual(meeting);
        expect(store.loading).toBe(false);
    });
});
