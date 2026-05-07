import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useAttendancePeriodStore } from '@/stores/attendancePeriod';
import { axiosInstance } from '@/plugins/axios';

vi.mock('@/plugins/axios', () => ({
    axiosInstance: {
        get: vi.fn(),
    },
}));

describe('Attendance Period Store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useAttendancePeriodStore();
        vi.clearAllMocks();
    });

    it('fetchAllPaginated populates paginatedPeriods and meta', async () => {
        const paginator = {
            data: [{ id: 1, name: 'Jan 2026' }],
            current_page: 2,
            last_page: 3,
            per_page: 25,
            total: 60,
        };
        const params = { page: 2, search: 'Jan', row_per_page: 25 };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: paginator, message: 'ok' } });

        const result = await store.fetchAllPaginated(params);

        expect(axiosInstance.get).toHaveBeenCalledWith('attendance-periods', {
            params,
        });
        expect(result).toEqual({ data: paginator, message: 'ok' });
        expect(store.paginatedPeriods).toEqual(paginator.data);
        expect(store.meta).toEqual({
            current_page: 2,
            last_page: 3,
            per_page: 25,
            total: 60,
        });
        expect(store.loading).toBe(false);
        expect(store.error).toBe(null);
    });

    it('fetchAllPaginated uses default params when omitted', async () => {
        axiosInstance.get.mockResolvedValueOnce({
            data: {
                data: {
                    data: [],
                    current_page: 1,
                    last_page: 1,
                    per_page: 10,
                    total: 0,
                },
            },
        });

        await store.fetchAllPaginated();

        expect(axiosInstance.get).toHaveBeenCalledWith('attendance-periods', {
            params: {
                page: 1,
                search: '',
                row_per_page: 10,
            },
        });
    });

    it('fetchAllPaginated sets error and rethrows on failure', async () => {
        const mockError = {
            response: {
                status: 500,
                data: { message: 'Failed to fetch periods' },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchAllPaginated()).rejects.toEqual(mockError);

        expect(store.error).toBe('Failed to fetch periods');
        expect(store.loading).toBe(false);
    });

    it('fetchReadiness populates readinessSummary and returns payload', async () => {
        const readiness = {
            salary_month: '2026-05',
            ready: true,
            blockers: [],
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: readiness } });

        const result = await store.fetchReadiness({ start_date: '2026-05-01' });

        expect(axiosInstance.get).toHaveBeenCalledWith('payrolls/generate-readiness', {
            params: { salary_month: '2026-05' },
        });
        expect(result).toEqual(readiness);
        expect(store.readinessSummary).toEqual(readiness);
        expect(store.loading).toBe(false);
    });

    it('fetchReadiness sets error and rethrows on failure', async () => {
        const mockError = {
            response: {
                status: 404,
                data: { message: 'Period not found' },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchReadiness(999)).rejects.toEqual(mockError);

        expect(store.error).toBe('Period not found');
        expect(store.loading).toBe(false);
    });
});
