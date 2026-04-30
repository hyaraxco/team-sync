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

describe('Payroll Store - Phase 3 Features', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = usePayrollStore();
        vi.clearAllMocks();
    });

    describe('#4 - Settings Version Diff', () => {
        it('fetchSettingVersionDiff calls correct endpoint', async () => {
            const mockDiff = {
                version_id: 5,
                version_number: 3,
                has_previous: true,
                changes: [
                    { field: 'payday_day', old_value: 25, new_value: 28 },
                ],
            };
            axiosInstance.get.mockResolvedValueOnce({
                data: { data: mockDiff },
            });

            const result = await store.fetchSettingVersionDiff(5);

            expect(axiosInstance.get).toHaveBeenCalledWith('/payroll-settings/versions/5/diff');
            expect(result).toEqual(mockDiff);
        });
    });

    describe('#5 - Correction Tracking', () => {
        it('reopenPayroll returns correction_count in response', async () => {
            const payload = { reason: 'Bank account correction needed.' };
            axiosInstance.post.mockResolvedValueOnce({
                data: {
                    message: 'Payroll reopened',
                    data: { id: 10, status: 'pending', correction_count: 1 },
                },
            });

            const result = await store.reopenPayroll(10, payload);

            expect(result.correction_count).toBe(1);
        });
    });

    describe('#6 - Approval Matrix', () => {
        it('fetchApprovalPolicies calls correct endpoint', async () => {
            const mockPolicies = [
                { id: 1, name: 'Director Approval', min_amount: 1000000 },
            ];
            axiosInstance.get.mockResolvedValueOnce({
                data: { data: mockPolicies },
            });

            const result = await store.fetchApprovalPolicies();

            expect(axiosInstance.get).toHaveBeenCalledWith('/payroll-approval-policies');
            expect(result).toEqual(mockPolicies);
        });

        it('createApprovalPolicy calls POST', async () => {
            const payload = {
                name: 'Manager Approval',
                min_amount: 5000000,
                required_role: 'manager',
                approval_order: 1,
            };
            axiosInstance.post.mockResolvedValueOnce({
                data: {
                    message: 'Created',
                    data: { id: 2, ...payload },
                },
            });

            const result = await store.createApprovalPolicy(payload);

            expect(axiosInstance.post).toHaveBeenCalledWith('/payroll-approval-policies', payload);
            expect(result.id).toBe(2);
        });

        it('updateApprovalPolicy calls PUT', async () => {
            const payload = { name: 'Updated Policy' };
            axiosInstance.put.mockResolvedValueOnce({
                data: {
                    message: 'Updated',
                    data: { id: 3, name: 'Updated Policy' },
                },
            });

            const result = await store.updateApprovalPolicy(3, payload);

            expect(axiosInstance.put).toHaveBeenCalledWith('/payroll-approval-policies/3', payload);
            expect(result.name).toBe('Updated Policy');
        });

        it('deleteApprovalPolicy calls DELETE', async () => {
            axiosInstance.delete.mockResolvedValueOnce({
                data: { message: 'Deleted' },
            });

            await store.deleteApprovalPolicy(4);

            expect(axiosInstance.delete).toHaveBeenCalledWith('/payroll-approval-policies/4');
            expect(store.success).toBe('Deleted');
        });

        it('fetchPayrollApprovals calls correct endpoint', async () => {
            const mockApprovals = {
                payroll_id: 10,
                is_multi_step: true,
                all_approved: false,
                approvals: [{ id: 1, status: 'pending' }],
            };
            axiosInstance.get.mockResolvedValueOnce({
                data: { data: mockApprovals },
            });

            const result = await store.fetchPayrollApprovals(10);

            expect(axiosInstance.get).toHaveBeenCalledWith('/payrolls/10/approvals');
            expect(result.is_multi_step).toBe(true);
        });

        it('submitPayrollApproval calls POST', async () => {
            const payload = { status: 'approved', notes: 'LGTM' };
            axiosInstance.post.mockResolvedValueOnce({
                data: {
                    message: 'Submitted',
                    data: { payroll_id: 10, all_approved: true },
                },
            });

            const result = await store.submitPayrollApproval(10, payload);

            expect(axiosInstance.post).toHaveBeenCalledWith('/payrolls/10/approvals', payload);
            expect(result.all_approved).toBe(true);
        });
    });

    describe('#7 - Enhanced Analytics', () => {
        it('fetchPayrollAnalytics returns enhanced metrics', async () => {
            const mockAnalytics = {
                periods_returned: 3,
                trends: [
                    {
                        salary_month: '2026-03-01',
                        label: 'Mar 2026',
                        total_amount: 50000000,
                        bpjs_employee_total: 1000000,
                        bpjs_employer_total: 2000000,
                        bpjs_combined_total: 3000000,
                    },
                ],
                average_salary_trend: [
                    { salary_month: '2026-03-01', label: 'Mar 2026', average_salary: 10000000 },
                ],
                total_deductions_trend: [
                    { salary_month: '2026-03-01', label: 'Mar 2026', total_deductions: 500000 },
                ],
                headcount_vs_payroll_growth: [
                    { salary_month: '2026-03-01', label: 'Mar 2026', employee_count: 5, total_amount: 50000000 },
                ],
                bpjs_contribution_trend: [
                    { salary_month: '2026-03-01', label: 'Mar 2026', bpjs_employee_total: 1000000, bpjs_employer_total: 2000000 },
                ],
                top_deduction_reasons: [
                    { reason: 'absent', days: 10 },
                    { reason: 'half_day', days: 5 },
                    { reason: 'unpaid_leave', days: 2 },
                ],
                summary: {
                    total_amount: 50000000,
                    total_deductions: 500000,
                },
                growth_metrics: {
                    salary_growth_percentage: 5.2,
                },
            };

            axiosInstance.get.mockResolvedValueOnce({
                data: { data: mockAnalytics },
            });

            const result = await store.fetchPayrollAnalytics(6);

            expect(axiosInstance.get).toHaveBeenCalledWith('/payrolls/analytics', { params: { months: 6 } });
            expect(store.analytics.average_salary_trend).toBeDefined();
            expect(store.analytics.bpjs_contribution_trend).toBeDefined();
            expect(store.analytics.top_deduction_reasons).toHaveLength(3);
            expect(store.analytics.trends[0].bpjs_combined_total).toBe(3000000);
        });
    });
});
