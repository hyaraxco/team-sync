import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useStaffMemberStore } from '@/stores/staffMember';
import { axiosInstance } from '@/plugins/axios';

vi.mock('@/plugins/axios', () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe('Staff Member Store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useStaffMemberStore();
        vi.clearAllMocks();
    });

    it('fetchStaffMembers populates staffMembers state', async () => {
        const params = { search: 'andi' };
        const mockResponse = {
            data: {
                data: [{ id: 1, name: 'Andi' }, { id: 2, name: 'Sari' }],
            },
        };
        axiosInstance.get.mockResolvedValueOnce(mockResponse);

        await store.fetchStaffMembers(params);

        expect(axiosInstance.get).toHaveBeenCalledWith('staff-members', { params });
        expect(store.staffMembers).toEqual(mockResponse.data.data);
        expect(store.loading).toBe(false);
    });

    it('fetchStaffMember returns a single staff member by id', async () => {
        const mockStaffMember = { id: 7, name: 'Budi' };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: mockStaffMember } });

        const result = await store.fetchStaffMember(7);

        expect(axiosInstance.get).toHaveBeenCalledWith('staff-members/7');
        expect(result).toEqual(mockStaffMember);
    });

    it('createStaffMember calls POST multipart and sets success', async () => {
        const payload = new FormData();
        payload.append('name', 'Citra');
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Staff member created',
                data: { id: 10, name: 'Citra' },
            },
        });

        await store.createStaffMember(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('staff-members', payload, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        expect(store.success).toBe('Staff member created');
        expect(store.loading).toBe(false);
    });

    it('updateStaffMember calls POST with multipart payload and sets success', async () => {
        const payload = new FormData();
        payload.append('name', 'Updated Name');
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Staff member updated',
                data: { id: 12, name: 'Updated Name' },
            },
        });

        await store.updateStaffMember(12, payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('staff-members/12', payload, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        expect(store.success).toBe('Staff member updated');
        expect(store.loading).toBe(false);
    });

    it('deleteStaffMember calls DELETE and sets success message', async () => {
        axiosInstance.delete.mockResolvedValueOnce({
            data: {
                message: 'Staff member deleted',
            },
        });

        await store.deleteStaffMember(15);

        expect(axiosInstance.delete).toHaveBeenCalledWith('staff-members/15');
        expect(store.success).toBe('Staff member deleted');
        expect(store.loading).toBe(false);
    });

    it('fetchPerformanceStatistics sets and returns statistics', async () => {
        const mockStats = {
            tasks_completed: 24,
            attendance_rate: 98,
            projects_count: 3,
            performance_score: 90,
        };
        axiosInstance.get.mockResolvedValueOnce({
            data: {
                data: mockStats,
            },
        });

        const result = await store.fetchPerformanceStatistics(9);

        expect(axiosInstance.get).toHaveBeenCalledWith('/staff-members/9/performance-statistics');
        expect(result).toEqual(mockStats);
        expect(store.performanceStatistics).toEqual(mockStats);
        expect(store.loadingStatistics).toBe(false);
    });

    it('sets error on failure', async () => {
        const mockError = {
            response: {
                status: 400,
                data: { message: 'Invalid staff member request' },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchStaffMembers({});

        expect(store.error).toBe('Invalid staff member request');
        expect(store.loading).toBe(false);
    });
});
