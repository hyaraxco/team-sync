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

describe('Payroll Store - Approval Matrix', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = usePayrollStore();
        vi.clearAllMocks();
    });

    it('fetchPayrollApprovals returns approval chain for a payroll', async () => {
        const mockApprovals = [
            { id: 1, role: 'hr', status: 'approved', approved_at: '2026-04-25' },
            { id: 2, role: 'finance', status: 'pending', approved_at: null },
        ];
        axiosInstance.get.mockResolvedValueOnce({
            data: { data: mockApprovals },
        });

        const result = await store.fetchPayrollApprovals(10);

        expect(axiosInstance.get).toHaveBeenCalledWith('/payrolls/10/approvals');
        expect(result).toEqual(mockApprovals);
    });

    it('fetchPayrollApprovals sets error on failure', async () => {
        const mockError = {
            response: {
                status: 404,
                data: { message: 'Payroll not found' },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchPayrollApprovals(999)).rejects.toEqual(mockError);

        expect(store.error).toBe('Payroll not found');
    });

    it('submitPayrollApproval sends approval decision', async () => {
        const payload = { decision: 'approved', notes: 'Looks good' };
        const mockData = { id: 3, role: 'finance', status: 'approved' };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Approval submitted',
                data: mockData,
            },
        });

        const result = await store.submitPayrollApproval(10, payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('/payrolls/10/approvals', payload);
        expect(result).toEqual(mockData);
        expect(store.success).toBe('Approval submitted');
    });

    it('submitPayrollApproval handles rejection decision', async () => {
        const payload = { decision: 'rejected', notes: 'Discrepancy found' };
        const mockData = { id: 4, role: 'finance', status: 'rejected' };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Approval rejected',
                data: mockData,
            },
        });

        const result = await store.submitPayrollApproval(10, payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('/payrolls/10/approvals', payload);
        expect(result).toEqual(mockData);
        expect(store.success).toBe('Approval rejected');
    });

    it('submitPayrollApproval sets error on failure', async () => {
        const mockError = {
            response: {
                status: 403,
                data: { message: 'Not authorized to approve this payroll' },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.submitPayrollApproval(10, { decision: 'approved' })).rejects.toEqual(
            mockError,
        );

        expect(store.error).toBe('Not authorized to approve this payroll');
    });


});
