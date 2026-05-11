import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

const triggerBlobDownload = (response, fallbackFilename) => {
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = response.headers["content-disposition"];
    let filename = fallbackFilename;
    if (contentDisposition) {
        const utf8FilenameMatch = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i);
        const quotedFilenameMatch = contentDisposition.match(/filename="([^"]+)"/i);
        const plainFilenameMatch = contentDisposition.match(/filename=([^;]+)/i);

        if (utf8FilenameMatch && utf8FilenameMatch[1]) {
            filename = decodeURIComponent(utf8FilenameMatch[1]);
        } else if (quotedFilenameMatch && quotedFilenameMatch[1]) {
            filename = quotedFilenameMatch[1];
        } else if (plainFilenameMatch && plainFilenameMatch[1]) {
            filename = plainFilenameMatch[1].trim();
        }
    }

    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
};

const buildPayrollReportFallbackFilename = (params = {}) => {
    const status = (params.status || "all").toString().replace(/^\w/, (char) => char.toUpperCase());
    const periodLabel =
        params.period_type === "yearly"
            ? params.year || new Date().getFullYear()
            : params.month || new Date().toISOString().slice(0, 7);
    const detailSuffix = params.report_type === "detail" ? "_Detail" : "";

    return `Payroll_Report_${periodLabel}_${status}${detailSuffix}.xlsx`;
};

const getDefaultAnalyticsState = () => ({
    periods_requested: 6,
    periods_returned: 0,
    status_scope: [],
    reporting_period: {
        start_month: null,
        end_month: null,
        as_of_timestamp: null,
    },
    summary: {
        total_payroll_batches: 0,
        total_employee_entries: 0,
        total_amount: 0,
        total_deductions: 0,
        average_salary_across_periods: 0,
        average_deduction_rate: 0,
    },
    growth_metrics: {
        salary_growth_percentage: 0,
        headcount_change: 0,
        deduction_rate_change: 0,
    },
    trends: [],
});

export const usePayrollStore = defineStore("payroll", {
    state: () => ({
        payrolls: [],
        payslips: [],
        payrollAdjustments: [],
        statistics: {
            total_payroll: 0,
            pending_review: 0,
            finalized: 0,
            total_amount: 0,
            average_salary: 0,
            deductions: 0,
        },
        analytics: getDefaultAnalyticsState(),
        settings: null,
        settingsHistory: [],
        bpjsRateHistory: [],
        payrollComparison: null,
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        },
        loading: false,
        loadingStatistics: false,
        loadingAnalytics: false,
        error: null,
        success: null,
    }),

    actions: {
        async fetchPayrolls(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/payrolls/all/paginated", { params });

                this.payrolls = response.data.data.data;
                this.meta = response.data.data.meta;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchPayrollsIndex() {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/payrolls");

                this.payrolls = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchBpjsValidation() {
            this.error = null;

            try {
                const response = await axiosInstance.get("/payroll-settings/bpjs-validation");
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchPayroll(id) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`/payrolls/${id}`);

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchPayrollDetails(id, page = 1, perPage = 50, params = {}) {
            try {
                const response = await axiosInstance.get(`/payrolls/${id}/details`, {
                    params: {
                        page,
                        per_page: perPage,
                        ...params,
                    },
                });

                this.meta = response.data.data.meta;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchPayrollActivityLogs(id) {
            try {
                const response = await axiosInstance.get(`/payrolls/${id}/activity-logs`);

                return response.data.data ?? [];
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchPayrollNotificationDeliveries(id) {
            try {
                const response = await axiosInstance.get(`/payrolls/${id}/notification-deliveries`);

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchPayrollReconciliation(id, params = null) {
            try {
                const response = await axiosInstance.get(`/payrolls/${id}/reconciliation`, {
                    params: params ?? undefined,
                });

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async resolveReconciliationException(payrollId, payload) {
            try {
                const response = await axiosInstance.post(`/payrolls/${payrollId}/reconciliation/resolve`, payload);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchReconciliationResolutions(payrollId) {
            try {
                const response = await axiosInstance.get(`/payrolls/${payrollId}/reconciliation/resolutions`);

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async generatePayroll(payload) {
            this.loading = true;

            try {
                const response = await axiosInstance.post("/payrolls/generate", payload);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchGenerateReadiness(salaryMonth) {
            try {
                const response = await axiosInstance.get("/payrolls/generate-readiness", {
                    params: {
                        salary_month: salaryMonth,
                    },
                });

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchReadinessDashboard(salaryMonth) {
            try {
                const response = await axiosInstance.get("/payrolls/readiness-dashboard", {
                    params: {
                        salary_month: salaryMonth,
                    },
                });

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchReadinessTeamSummary(salaryMonth) {
            try {
                const response = await axiosInstance.get("/payrolls/readiness-dashboard/team-summary", {
                    params: {
                        salary_month: salaryMonth,
                    },
                });

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async updatePayrollDetail(id, payload) {
            this.loading = true;

            try {
                const response = await axiosInstance.put(`/payroll-details/${id}`, payload);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async approvePayroll(id) {
            this.loading = true;

            try {
                const response = await axiosInstance.post(`/payrolls/${id}/approve`);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async markAsPaid(id, payload) {
            this.loading = true;

            try {
                const response = await axiosInstance.post(`/payrolls/${id}/mark-as-paid`, payload);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async reopenPayroll(id, payload) {
            this.loading = true;

            try {
                const response = await axiosInstance.post(`/payrolls/${id}/reopen`, payload);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async resendNotifications(id) {
            this.loading = true;

            try {
                const response = await axiosInstance.post(`/payrolls/${id}/resend-notifications`);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchMyPayslips(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/my-payslips", { params });

                this.payslips = response.data.data.data;
                this.meta = response.data.data.meta;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchMyPayslip(id) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`/my-payslips/${id}`);

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async downloadPayslip(id) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`/payslips/${id}/download`, {
                    responseType: "blob",
                });

                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async emailPayslip(id) {
            this.loading = true;

            try {
                const response = await axiosInstance.post(`/payslips/${id}/email`);

                this.success = response.data.message;
                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchStatistics() {
            this.loadingStatistics = true;

            try {
                const response = await axiosInstance.get("/payrolls/statistics");

                this.statistics = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loadingStatistics = false;
            }
        },

        async fetchPayrollStatistics(id) {
            try {
                const response = await axiosInstance.get(`/payrolls/${id}/statistics`);

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchPayrollAnalytics(months = 6) {
            this.loadingAnalytics = true;

            try {
                const response = await axiosInstance.get("/payrolls/analytics", {
                    params: {
                        months,
                    },
                });

                this.analytics = response.data.data ?? getDefaultAnalyticsState();

                return this.analytics;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loadingAnalytics = false;
            }
        },

        async fetchPayrollComparison(month1, month2) {
            this.loadingAnalytics = true;
            this.error = null;

            try {
                const response = await axiosInstance.get("/payrolls/compare", {
                    params: {
                        month1,
                        month2,
                    },
                });

                this.payrollComparison = response.data.data;
                return this.payrollComparison;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loadingAnalytics = false;
            }
        },

        async fetchPayrollAdjustments(params = {}) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get("/payroll-adjustments", { params });
                const paginator = response.data.data;

                this.payrollAdjustments = paginator.data ?? [];
                this.meta = {
                    current_page: paginator.current_page,
                    last_page: paginator.last_page,
                    per_page: paginator.per_page,
                    total: paginator.total,
                };

                return paginator;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async approvePayrollAdjustment(id, payload = {}) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.post(`/payroll-adjustments/${id}/approve`, payload);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchSettings() {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/payroll-settings");

                this.settings = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async updateSettings(payload) {
            this.loading = true;

            try {
                const response = await axiosInstance.put("/payroll-settings", payload);

                this.settings = response.data.data;
                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchSettingsHistory() {
            try {
                const response = await axiosInstance.get("/payroll-settings/history");

                this.settingsHistory = response.data.data ?? [];

                return this.settingsHistory;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchSettingVersionDiff(versionId) {
            try {
                const response = await axiosInstance.get(`/payroll-settings/versions/${versionId}/diff`);

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchApprovalPolicies() {
            try {
                const response = await axiosInstance.get("/payroll-approval-policies");

                return response.data.data ?? [];
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async createApprovalPolicy(payload) {
            try {
                const response = await axiosInstance.post("/payroll-approval-policies", payload);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async updateApprovalPolicy(id, payload) {
            try {
                const response = await axiosInstance.put(`/payroll-approval-policies/${id}`, payload);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async deleteApprovalPolicy(id) {
            try {
                const response = await axiosInstance.delete(`/payroll-approval-policies/${id}`);

                this.success = response.data.message;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchPayrollApprovals(payrollId) {
            try {
                const response = await axiosInstance.get(`/payrolls/${payrollId}/approvals`);

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async submitPayrollApproval(payrollId, payload) {
            try {
                const response = await axiosInstance.post(`/payrolls/${payrollId}/approvals`, payload);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchBpjsRateHistory() {
            try {
                const response = await axiosInstance.get("/payroll-settings/bpjs-rate-history");

                this.bpjsRateHistory = response.data.data ?? [];

                return this.bpjsRateHistory;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async exportExcel(id) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`/payrolls/${id}/export-excel`, {
                    responseType: "blob",
                });

                triggerBlobDownload(response, "Payroll_Export.xlsx");

                this.success = "Excel file downloaded successfully";
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async exportPdf(id) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`/payrolls/${id}/export-pdf`, {
                    responseType: "blob",
                });

                triggerBlobDownload(response, "Payroll_Payslips.zip");

                this.success = "Payslip ZIP downloaded successfully";
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async exportPayrollReport(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/payrolls/export-report", {
                    params,
                    responseType: "blob",
                });

                triggerBlobDownload(response, buildPayrollReportFallbackFilename(params));

                this.success = "Payroll report downloaded successfully";
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
