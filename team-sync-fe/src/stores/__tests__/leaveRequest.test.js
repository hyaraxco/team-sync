import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useLeaveRequestStore } from '@/stores/leaveRequest';
import { axiosInstance } from '@/plugins/axios';

vi.mock('@/plugins/axios', () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe('Leave Request Store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useLeaveRequestStore();
        vi.clearAllMocks();
    });

    it('fetchLeaveRequests (paginated) populates leaveRequests and meta', async () => {
        const params = { page: 1, status: 'pending' };
        const mockResponse = {
            data: {
                data: {
                    data: [{ id: 1, status: 'pending' }, { id: 2, status: 'approved' }],
                    meta: { current_page: 1, last_page: 2, per_page: 10, total: 15 },
                },
            },
        };
        axiosInstance.get.mockResolvedValueOnce(mockResponse);

        const result = await store.fetchLeaveRequestsPaginated(params);

        expect(axiosInstance.get).toHaveBeenCalledWith('leave-requests/all/paginated', { params });
        expect(store.leaveRequests).toEqual(mockResponse.data.data.data);
        expect(store.meta).toEqual(mockResponse.data.data.meta);
        expect(result).toEqual(mockResponse.data.data);
        expect(store.loading).toBe(false);
    });

    it('createLeaveRequest calls POST, sets success, and returns data', async () => {
        const payload = { leave_type: 'sick', reason: 'Flu', start_date: '2026-05-01', end_date: '2026-05-01' };
        const mockData = { id: 12, status: 'pending' };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Leave request submitted',
                data: mockData,
            },
        });

        const result = await store.createLeaveRequest(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('leave-requests', payload);
        expect(result).toEqual(mockData);
        expect(store.success).toBe('Leave request submitted');
        expect(store.loading).toBe(false);
    });

    it('approveLeaveRequest calls POST and returns data', async () => {
        const mockData = { id: 33, status: 'approved' };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Leave request approved',
                data: mockData,
            },
        });

        const result = await store.approveLeaveRequest(33);

        expect(axiosInstance.post).toHaveBeenCalledWith('leave-requests/approve/33');
        expect(result).toEqual(mockData);
        expect(store.success).toBe('Leave request approved');
        expect(store.loading).toBe(false);
    });

    it('rejectLeaveRequest calls POST and returns data', async () => {
        const mockData = { id: 44, status: 'rejected' };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Leave request rejected',
                data: mockData,
            },
        });

        const result = await store.rejectLeaveRequest(44);

        expect(axiosInstance.post).toHaveBeenCalledWith('leave-requests/reject/44');
        expect(result).toEqual(mockData);
        expect(store.success).toBe('Leave request rejected');
        expect(store.loading).toBe(false);
    });

    it('uploadProof sends multipart payload and returns data', async () => {
        const file = new File(['proof'], 'proof.png', { type: 'image/png' });
        const mockData = { id: 55, proof_status: 'pending_review' };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Proof uploaded',
                data: mockData,
            },
        });

        const result = await store.uploadProof(55, file);

        expect(axiosInstance.post).toHaveBeenCalledWith(
            'leave-requests/55/proof',
            expect.any(FormData),
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            },
        );
        expect(result).toEqual(mockData);
        expect(store.success).toBe('Proof uploaded');
        expect(store.loading).toBe(false);
    });

    it('sets error on failure', async () => {
        const mockError = {
            response: {
                status: 400,
                data: { message: 'Invalid leave request payload' },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.createLeaveRequest({})).rejects.toEqual(mockError);

        expect(store.error).toBe('Invalid leave request payload');
        expect(store.loading).toBe(false);
    });
});
