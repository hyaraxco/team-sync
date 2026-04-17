import { defineStore } from "pinia";
import { axiosInstance } from '@/plugins/axios';
import { handleError } from "@/helpers/errorHelper";

export const useAnalyticsStore = defineStore("analytics", {
    state: () => ({
        // Global filters
        period: '6m',
        department: null,
        teamId: null,

        // Executive Summary
        executiveSummary: null,
        executiveSummaryLoading: false,

        // Workforce
        workforce: null,
        workforceLoading: false,

        // Attendance
        attendance: null,
        attendanceLoading: false,

        // Leave
        leave: null,
        leaveLoading: false,

        // Payroll
        payroll: null,
        payrollLoading: false,

        // Projects
        projects: null,
        projectsLoading: false,

        error: null,
    }),

    actions: {
        setFilters({ period, department, teamId }) {
            if (period !== undefined) this.period = period;
            if (department !== undefined) this.department = department;
            if (teamId !== undefined) this.teamId = teamId;
        },

        buildParams(extra = {}) {
            const params = { period: this.period };
            if (this.department) params.department = this.department;
            if (this.teamId) params.team_id = this.teamId;
            return { ...params, ...extra };
        },

        async fetchExecutiveSummary() {
            this.executiveSummaryLoading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('/analytics/executive-summary', {
                    params: this.buildParams(),
                });
                this.executiveSummary = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.executiveSummaryLoading = false;
            }
        },

        async fetchWorkforceAnalytics() {
            this.workforceLoading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('/analytics/workforce', {
                    params: this.buildParams(),
                });
                this.workforce = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.workforceLoading = false;
            }
        },

        async fetchAttendanceAnalytics() {
            this.attendanceLoading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('/analytics/attendance', {
                    params: this.buildParams(),
                });
                this.attendance = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.attendanceLoading = false;
            }
        },

        async fetchLeaveAnalytics() {
            this.leaveLoading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('/analytics/leave', {
                    params: this.buildParams(),
                });
                this.leave = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.leaveLoading = false;
            }
        },

        async fetchPayrollAnalytics() {
            this.payrollLoading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('/analytics/payroll', {
                    params: this.buildParams(),
                });
                this.payroll = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.payrollLoading = false;
            }
        },

        async fetchProjectAnalytics(projectId = null) {
            this.projectsLoading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('/analytics/projects', {
                    params: this.buildParams(projectId ? { project_id: projectId } : {}),
                });
                this.projects = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.projectsLoading = false;
            }
        },
    }
});
