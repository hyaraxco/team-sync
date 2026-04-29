import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useAnalyticsStore } from '@/stores/analytics';
import { axiosInstance } from '@/plugins/axios';

vi.mock('@/plugins/axios', () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe('Analytics Store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useAnalyticsStore();
        vi.clearAllMocks();
    });

    it('fetchExecutiveSummary stores executive summary data', async () => {
        const summary = { total_staff: 200, active_projects: 12 };
        store.setFilters({ period: '12m', department: 'Engineering', teamId: 7 });
        axiosInstance.get.mockResolvedValueOnce({ data: { data: summary } });

        await store.fetchExecutiveSummary();

        expect(axiosInstance.get).toHaveBeenCalledWith('/analytics/executive-summary', {
            params: {
                period: '12m',
                department: 'Engineering',
                team_id: 7,
            },
        });
        expect(store.executiveSummary).toEqual(summary);
        expect(store.executiveSummaryLoading).toBe(false);
    });

    it('fetchExecutiveSummary sets error on failure', async () => {
        axiosInstance.get.mockRejectedValueOnce({
            response: {
                data: {
                    message: 'Executive summary failed',
                },
            },
        });

        await store.fetchExecutiveSummary();

        expect(store.error).toBe('Executive summary failed');
        expect(store.executiveSummaryLoading).toBe(false);
    });

    it('fetchWorkforceAnalytics stores workforce analytics data', async () => {
        const workforce = { by_department: [{ name: 'Engineering', count: 45 }] };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: workforce } });

        await store.fetchWorkforceAnalytics();

        expect(axiosInstance.get).toHaveBeenCalledWith('/analytics/workforce', {
            params: {
                period: '6m',
            },
        });
        expect(store.workforce).toEqual(workforce);
        expect(store.workforceLoading).toBe(false);
    });

    it('fetchWorkforceAnalytics sets error on failure', async () => {
        axiosInstance.get.mockRejectedValueOnce({
            response: {
                data: {
                    message: 'Workforce analytics failed',
                },
            },
        });

        await store.fetchWorkforceAnalytics();

        expect(store.error).toBe('Workforce analytics failed');
        expect(store.workforceLoading).toBe(false);
    });

    it('fetchAttendanceAnalytics stores attendance analytics data', async () => {
        const attendance = { present_rate: 96.5 };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: attendance } });

        await store.fetchAttendanceAnalytics();

        expect(axiosInstance.get).toHaveBeenCalledWith('/analytics/attendance', {
            params: {
                period: '6m',
            },
        });
        expect(store.attendance).toEqual(attendance);
        expect(store.attendanceLoading).toBe(false);
    });

    it('fetchAttendanceAnalytics sets error on failure', async () => {
        axiosInstance.get.mockRejectedValueOnce({
            response: {
                data: {
                    message: 'Attendance analytics failed',
                },
            },
        });

        await store.fetchAttendanceAnalytics();

        expect(store.error).toBe('Attendance analytics failed');
        expect(store.attendanceLoading).toBe(false);
    });

    it('fetchLeaveAnalytics stores leave analytics data', async () => {
        const leave = { utilization_rate: 72 };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: leave } });

        await store.fetchLeaveAnalytics();

        expect(axiosInstance.get).toHaveBeenCalledWith('/analytics/leave', {
            params: {
                period: '6m',
            },
        });
        expect(store.leave).toEqual(leave);
        expect(store.leaveLoading).toBe(false);
    });

    it('fetchLeaveAnalytics sets error on failure', async () => {
        axiosInstance.get.mockRejectedValueOnce({
            response: {
                data: {
                    message: 'Leave analytics failed',
                },
            },
        });

        await store.fetchLeaveAnalytics();

        expect(store.error).toBe('Leave analytics failed');
        expect(store.leaveLoading).toBe(false);
    });

    it('fetchPayrollAnalytics stores payroll analytics data', async () => {
        const payroll = { monthly_total: 1500000000 };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payroll } });

        await store.fetchPayrollAnalytics();

        expect(axiosInstance.get).toHaveBeenCalledWith('/analytics/payroll', {
            params: {
                period: '6m',
            },
        });
        expect(store.payroll).toEqual(payroll);
        expect(store.payrollLoading).toBe(false);
    });

    it('fetchPayrollAnalytics sets error on failure', async () => {
        axiosInstance.get.mockRejectedValueOnce({
            response: {
                data: {
                    message: 'Payroll analytics failed',
                },
            },
        });

        await store.fetchPayrollAnalytics();

        expect(store.error).toBe('Payroll analytics failed');
        expect(store.payrollLoading).toBe(false);
    });

    it('fetchProjectAnalytics stores project analytics data', async () => {
        const projects = { completion_rate: 89 };
        store.setFilters({ period: '3m', department: 'Product' });
        axiosInstance.get.mockResolvedValueOnce({ data: { data: projects } });

        await store.fetchProjectAnalytics(123);

        expect(axiosInstance.get).toHaveBeenCalledWith('/analytics/projects', {
            params: {
                period: '3m',
                department: 'Product',
                project_id: 123,
            },
        });
        expect(store.projects).toEqual(projects);
        expect(store.projectsLoading).toBe(false);
    });

    it('fetchProjectAnalytics sets error on failure', async () => {
        axiosInstance.get.mockRejectedValueOnce({
            response: {
                data: {
                    message: 'Project analytics failed',
                },
            },
        });

        await store.fetchProjectAnalytics();

        expect(store.error).toBe('Project analytics failed');
        expect(store.projectsLoading).toBe(false);
    });
});
