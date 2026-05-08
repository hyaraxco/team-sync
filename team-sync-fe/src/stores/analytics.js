import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useAnalyticsStore = defineStore("analytics", {
    state: () => ({
        // Global filters
        period: "6m",
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

        // Enhanced Metrics
        turnoverRate: null,
        averageTenure: null,
        newHireTrends: null,
        complianceRate: null,
        attendancePatterns: null,
        remoteOfficeRatio: null,
        leaveUtilization: null,
        leaveBalanceTrends: null,
        peakLeavePeriods: null,
        payrollCostTrends: null,
        salaryDistribution: null,
        deductionAnalysis: null,
        timelineAdherence: null,
        taskVelocity: null,
        overdueTrends: null,

        // Gap-fill Metrics
        workforceDemographics: null,
        correctionFrequency: null,
        leaveApprovalTurnaround: null,
        leaveTypeDistribution: null,
        payrollCostPerEmployee: null,
        payrollProcessingTime: null,
        projectResourceUtilization: null,

        // Performance Analytics
        teamPerformanceSummary: null,
        companyPerformanceSummary: null,
        ratingDistribution: null,
        goalCompletionRate: null,
        feedbackMetrics: null,

        enhancedMetricsLoading: false,

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
                const response = await axiosInstance.get("/analytics/executive-summary", {
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
                const response = await axiosInstance.get("/analytics/workforce", {
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
                const response = await axiosInstance.get("/analytics/attendance", {
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
                const response = await axiosInstance.get("/analytics/leave", {
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
                const response = await axiosInstance.get("/analytics/payroll", {
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
                const response = await axiosInstance.get("/analytics/projects", {
                    params: this.buildParams(projectId ? { project_id: projectId } : {}),
                });
                this.projects = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.projectsLoading = false;
            }
        },

        async exportExcel(tab) {
            try {
                const response = await axiosInstance.get("/analytics/export/excel", {
                    params: { ...this.buildParams(), tab },
                    responseType: "blob",
                });
                const url = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement("a");
                link.href = url;
                link.setAttribute("download", `analytics-${tab}-${new Date().toISOString().slice(0, 10)}.xlsx`);
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);
            } catch (error) {
                this.error = handleError(error);
            }
        },

        async exportPdf(tab) {
            try {
                const response = await axiosInstance.get("/analytics/export/pdf", {
                    params: { ...this.buildParams(), tab },
                    responseType: "blob",
                });
                const url = window.URL.createObjectURL(new Blob([response.data], { type: "application/pdf" }));
                const link = document.createElement("a");
                link.href = url;
                link.setAttribute("download", `analytics-${tab}-${new Date().toISOString().slice(0, 10)}.pdf`);
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);
            } catch (error) {
                this.error = handleError(error);
            }
        },

        // Enhanced Workforce Analytics
        async fetchTurnoverRate() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/workforce/turnover-rate", {
                    params: this.buildParams(),
                });
                this.turnoverRate = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchAverageTenure() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/workforce/average-tenure", {
                    params: this.buildParams(),
                });
                this.averageTenure = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchNewHireTrends() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/workforce/new-hire-trends", {
                    params: this.buildParams(),
                });
                this.newHireTrends = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        // Enhanced Attendance Analytics
        async fetchComplianceRate() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/attendance/compliance-rate", {
                    params: this.buildParams(),
                });
                this.complianceRate = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchAttendancePatterns() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/attendance/patterns", {
                    params: this.buildParams(),
                });
                this.attendancePatterns = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchRemoteOfficeRatio() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/attendance/remote-office-ratio", {
                    params: this.buildParams(),
                });
                this.remoteOfficeRatio = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        // Enhanced Leave Analytics
        async fetchLeaveUtilization() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/leave/utilization-rate", {
                    params: this.buildParams(),
                });
                this.leaveUtilization = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchLeaveBalanceTrends() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/leave/balance-trends", {
                    params: this.buildParams(),
                });
                this.leaveBalanceTrends = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchPeakLeavePeriods() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/leave/peak-periods", {
                    params: this.buildParams(),
                });
                this.peakLeavePeriods = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        // Enhanced Payroll Analytics
        async fetchPayrollCostTrends() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/payroll/cost-trends", {
                    params: this.buildParams(),
                });
                this.payrollCostTrends = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchSalaryDistribution() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/payroll/salary-distribution", {
                    params: this.buildParams(),
                });
                this.salaryDistribution = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchDeductionAnalysis() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/payroll/deduction-analysis", {
                    params: this.buildParams(),
                });
                this.deductionAnalysis = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        // Enhanced Project Analytics
        async fetchTimelineAdherence() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/projects/timeline-adherence", {
                    params: this.buildParams(),
                });
                this.timelineAdherence = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchTaskVelocity() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/projects/task-velocity", {
                    params: this.buildParams(),
                });
                this.taskVelocity = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchOverdueTrends() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/projects/overdue-trends", {
                    params: this.buildParams(),
                });
                this.overdueTrends = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        // ─── Gap-fill Endpoints ────────────────────────────────────────────

        async fetchWorkforceDemographics() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/workforce/demographics", {
                    params: this.buildParams(),
                });
                this.workforceDemographics = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchCorrectionFrequency() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/attendance/correction-frequency", {
                    params: this.buildParams(),
                });
                this.correctionFrequency = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchLeaveApprovalTurnaround() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/leave/approval-turnaround", {
                    params: this.buildParams(),
                });
                this.leaveApprovalTurnaround = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchLeaveTypeDistribution() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/leave/type-distribution", {
                    params: this.buildParams(),
                });
                this.leaveTypeDistribution = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchPayrollCostPerEmployee() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/payroll/cost-per-employee", {
                    params: this.buildParams(),
                });
                this.payrollCostPerEmployee = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchPayrollProcessingTime() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/payroll/processing-time", {
                    params: this.buildParams(),
                });
                this.payrollProcessingTime = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchProjectResourceUtilization() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/project/resource-utilization", {
                    params: this.buildParams(),
                });
                this.projectResourceUtilization = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchPerformanceAnalytics() {
            this.enhancedMetricsLoading = true;
            this.error = null;

            try {
                const [team, company, rating, goals, feedback] = await Promise.all([
                    axiosInstance.get("/analytics/performance/team-summary", {
                        params: this.buildParams(),
                    }),
                    axiosInstance.get("/analytics/performance/company-summary", {
                        params: this.buildParams(),
                    }),
                    axiosInstance.get("/analytics/performance/rating-distribution", {
                        params: this.buildParams(),
                    }),
                    axiosInstance.get("/analytics/performance/goal-completion-rate", {
                        params: this.buildParams(),
                    }),
                    axiosInstance.get("/analytics/performance/feedback-metrics", {
                        params: this.buildParams(),
                    }),
                ]);

                this.teamPerformanceSummary = team.data.data;
                this.companyPerformanceSummary = company.data.data;
                this.ratingDistribution = rating.data.data;
                this.goalCompletionRate = goals.data.data;
                this.feedbackMetrics = feedback.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        // ─── Performance Analytics ─────────────────────────────────────────

        async fetchTeamPerformanceSummary() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/performance/team-summary", {
                    params: this.buildParams(),
                });
                this.teamPerformanceSummary = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchCompanyPerformanceSummary() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/performance/company-summary", {
                    params: this.buildParams(),
                });
                this.companyPerformanceSummary = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchRatingDistribution() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/performance/rating-distribution", {
                    params: this.buildParams(),
                });
                this.ratingDistribution = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchGoalCompletionRate() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/performance/goal-completion-rate", {
                    params: this.buildParams(),
                });
                this.goalCompletionRate = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },

        async fetchFeedbackMetrics() {
            this.enhancedMetricsLoading = true;
            try {
                const response = await axiosInstance.get("/analytics/performance/feedback-metrics", {
                    params: this.buildParams(),
                });
                this.feedbackMetrics = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.enhancedMetricsLoading = false;
            }
        },
    },
});
