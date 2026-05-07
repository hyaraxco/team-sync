import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { usePayrollStore } from '@/stores/payroll';
import { axiosInstance } from '@/plugins/axios';

vi.mock('@/plugins/axios', () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe('Payroll Store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = usePayrollStore();
        vi.clearAllMocks();
    });

    it('fetchPayrolls populates payrolls and meta state', async () => {
        const params = { page: 1, row_per_page: 10 };
        const mockResponse = {
            data: {
                data: {
                    data: [{ id: 1, status: 'pending' }, { id: 2, status: 'approved' }],
                    meta: { current_page: 1, last_page: 2, per_page: 10, total: 15 },
                },
            },
        };
        axiosInstance.get.mockResolvedValueOnce(mockResponse);

        await store.fetchPayrolls(params);

        expect(axiosInstance.get).toHaveBeenCalledWith('/payrolls/all/paginated', { params });
        expect(store.payrolls).toEqual(mockResponse.data.data.data);
        expect(store.meta).toEqual(mockResponse.data.data.meta);
        expect(store.loading).toBe(false);
    });

    it('generatePayroll calls POST and returns data', async () => {
        const payload = { salary_month: '2026-04' };
        const mockData = { payroll_id: 11 };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Payroll generated',
                data: mockData,
            },
        });

        const result = await store.generatePayroll(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('/payrolls/generate', payload);
        expect(result).toEqual(mockData);
        expect(store.success).toBe('Payroll generated');
        expect(store.loading).toBe(false);
    });

    it('approvePayroll calls POST and returns data', async () => {
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Payroll approved',
                data: { id: 22, status: 'approved' },
            },
        });

        const result = await store.approvePayroll(22);

        expect(axiosInstance.post).toHaveBeenCalledWith('/payrolls/22/approve');
        expect(result).toEqual({ id: 22, status: 'approved' });
        expect(store.success).toBe('Payroll approved');
    });

    it('markAsPaid calls POST and returns data', async () => {
        const payload = { paid_date: '2026-04-30', payment_method: 'bank_transfer' };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Payroll marked as paid',
                data: { id: 33, status: 'paid' },
            },
        });

        const result = await store.markAsPaid(33, payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('/payrolls/33/mark-as-paid', payload);
        expect(result).toEqual({ id: 33, status: 'paid' });
        expect(store.success).toBe('Payroll marked as paid');
    });

    it('fetchPayrollAdjustments populates adjustment queue and meta state', async () => {
        const params = { page: 2, per_page: 15, status: 'pending' };
        const paginator = {
            data: [{ id: 7, status: 'pending', amount_delta: '250000.00' }],
            current_page: 2,
            last_page: 4,
            per_page: 15,
            total: 46,
        };
        axiosInstance.get.mockResolvedValueOnce({
            data: {
                data: paginator,
            },
        });

        const result = await store.fetchPayrollAdjustments(params);

        expect(axiosInstance.get).toHaveBeenCalledWith('/payroll-adjustments', { params });
        expect(result).toEqual(paginator);
        expect(store.payrollAdjustments).toEqual(paginator.data);
        expect(store.meta).toEqual({
            current_page: 2,
            last_page: 4,
            per_page: 15,
            total: 46,
        });
        expect(store.loading).toBe(false);
    });

    it('approvePayrollAdjustment calls existing approve endpoint', async () => {
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Payroll Adjustment Approved Successfully',
                data: { id: 7, status: 'approved' },
            },
        });

        const result = await store.approvePayrollAdjustment(7);

        expect(axiosInstance.post).toHaveBeenCalledWith('/payroll-adjustments/7/approve', {});
        expect(result).toEqual({ id: 7, status: 'approved' });
        expect(store.success).toBe('Payroll Adjustment Approved Successfully');
        expect(store.loading).toBe(false);
    });

    it('fetchPayrollStatistics returns statistics payload', async () => {
        const mockStats = { total_employees: 48, net_amount: 125000000 };
        axiosInstance.get.mockResolvedValueOnce({
            data: {
                data: mockStats,
            },
        });

        const result = await store.fetchPayrollStatistics(44);

        expect(axiosInstance.get).toHaveBeenCalledWith('/payrolls/44/statistics');
        expect(result).toEqual(mockStats);
    });

    it('reopenPayroll calls POST and returns data', async () => {
        const payload = { reason: 'Correction needed' };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Payroll reopened',
                data: { id: 55, status: 'draft' },
            },
        });

        const result = await store.reopenPayroll(55, payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('/payrolls/55/reopen', payload);
        expect(result).toEqual({ id: 55, status: 'draft' });
        expect(store.success).toBe('Payroll reopened');
    });

    it('sets error on fetch failure', async () => {
        const mockError = {
            response: {
                status: 500,
                data: { message: 'Server payroll failure' },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchPayrolls({ page: 1 });

        expect(store.error).toBe('Server payroll failure');
        expect(store.loading).toBe(false);
    });
});
