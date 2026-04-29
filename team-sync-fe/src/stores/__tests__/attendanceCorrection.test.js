import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useAttendanceCorrectionStore } from '@/stores/attendanceCorrection';
import { axiosInstance } from '@/plugins/axios';

vi.mock('@/plugins/axios', () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

describe('Attendance Correction Store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useAttendanceCorrectionStore();
        vi.clearAllMocks();
    });

    it('fetchMyCorrections populates myCorrections', async () => {
        const payload = [{ id: 1, status: 'pending' }];
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        await store.fetchMyCorrections();

        expect(axiosInstance.get).toHaveBeenCalledWith('my-attendance-corrections');
        expect(store.myCorrections).toEqual(payload);
        expect(store.loading).toBe(false);
        expect(store.error).toBe(null);
    });

    it('fetchMyCorrections sets error on failure', async () => {
        const mockError = {
            response: {
                status: 500,
                data: { message: 'Unable to fetch' },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchMyCorrections();

        expect(store.error).toBe('Unable to fetch');
        expect(store.loading).toBe(false);
    });

    it('fetchAllPaginated populates paginatedCorrections and meta', async () => {
        const paginator = {
            data: [{ id: 10 }],
            current_page: 2,
            last_page: 5,
            per_page: 25,
            total: 100,
            from: 26,
            to: 50,
        };
        const params = { page: 2, search: 'john', row_per_page: 25, status: 'pending' };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: paginator } });

        await store.fetchAllPaginated(params);

        expect(axiosInstance.get).toHaveBeenCalledWith('attendance-corrections/all/paginated', {
            params,
        });
        expect(store.paginatedCorrections).toEqual(paginator.data);
        expect(store.meta).toEqual({
            current_page: 2,
            last_page: 5,
            per_page: 25,
            total: 100,
            from: 26,
            to: 50,
        });
        expect(store.loading).toBe(false);
    });

    it('fetchAllPaginated uses default params when not provided', async () => {
        axiosInstance.get.mockResolvedValueOnce({
            data: {
                data: {
                    data: [],
                    current_page: 1,
                    last_page: 1,
                    per_page: 10,
                    total: 0,
                    from: null,
                    to: null,
                },
            },
        });

        await store.fetchAllPaginated();

        expect(axiosInstance.get).toHaveBeenCalledWith('attendance-corrections/all/paginated', {
            params: {
                page: 1,
                search: '',
                row_per_page: 10,
                status: '',
            },
        });
    });

    it('requestCorrection creates correction and prepends it to myCorrections', async () => {
        const payload = { reason: 'Forgot tap out' };
        const created = { id: 33, reason: 'Forgot tap out' };
        store.myCorrections = [{ id: 1 }];
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Created',
                data: created,
            },
        });

        const result = await store.requestCorrection(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('attendance-corrections', payload);
        expect(result).toEqual({ message: 'Created', data: created });
        expect(store.myCorrections[0]).toEqual(created);
        expect(store.loading).toBe(false);
    });

    it('requestCorrection sets error and rethrows on failure', async () => {
        const mockError = {
            response: {
                status: 400,
                data: { message: 'Bad request' },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.requestCorrection({})).rejects.toEqual(mockError);
        expect(store.error).toBe('Bad request');
        expect(store.loading).toBe(false);
    });

    it('approveCorrection posts approval payload and returns response', async () => {
        const payload = { review_notes: 'Approved' };
        const responseData = { message: 'Approved' };
        axiosInstance.post.mockResolvedValueOnce({ data: responseData });

        const result = await store.approveCorrection(9, payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('attendance-corrections/9/approve', payload);
        expect(result).toEqual(responseData);
        expect(store.loading).toBe(false);
    });

    it('approveCorrection sets error and rethrows on failure', async () => {
        const mockError = {
            response: {
                status: 403,
                data: { message: 'Not allowed' },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.approveCorrection(1, {})).rejects.toEqual(mockError);
        expect(store.error).toBe('Not allowed');
        expect(store.loading).toBe(false);
    });

    it('rejectCorrection posts rejection payload and returns response', async () => {
        const payload = { review_notes: 'Need evidence' };
        const responseData = { message: 'Rejected' };
        axiosInstance.post.mockResolvedValueOnce({ data: responseData });

        const result = await store.rejectCorrection(7, payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('attendance-corrections/7/reject', payload);
        expect(result).toEqual(responseData);
        expect(store.loading).toBe(false);
    });

    it('rejectCorrection sets error and rethrows on failure', async () => {
        const mockError = {
            response: {
                status: 404,
                data: { message: 'Correction not found' },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.rejectCorrection(99, {})).rejects.toEqual(mockError);
        expect(store.error).toBe('Correction not found');
        expect(store.loading).toBe(false);
    });
});
