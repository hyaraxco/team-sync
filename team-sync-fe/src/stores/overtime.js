import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useOvertimeStore = defineStore("overtime", {
    state: () => ({
        records: [],
        myRecords: [],
        summary: null,
        loading: false,
        error: null,
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 15,
            total: 0,
        },
    }),

    actions: {
        async fetchOvertimeRecords(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get("overtime", {
                    params: {
                        page: params.page || 1,
                        per_page: params.per_page || params.row_per_page || 15,
                        search: params.search || "",
                        status: params.status || "",
                        staff_member_id: params.staff_member_id || "",
                        overtime_type: params.overtime_type || "",
                        date_from: params.date_from || "",
                        date_to: params.date_to || "",
                    },
                });
                const paginator = response.data.data;
                this.records = paginator.data;
                this.meta = paginator.meta;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async createOvertime(payload) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post("overtime", payload);
                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async approveOvertime(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post(`overtime/${id}/approve`);
                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async rejectOvertime(id, reason) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post(`overtime/${id}/reject`, {
                    rejection_reason: reason,
                });
                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchMyOvertime(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get("overtime/my-overtime", {
                    params: {
                        page: params.page || 1,
                        per_page: params.per_page || 15,
                        status: params.status || "",
                    },
                });
                const paginator = response.data.data;
                this.myRecords = paginator.data;
                this.meta = paginator.meta;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchOvertimeSummary() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get("overtime/summary");
                this.summary = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchOvertimeDetail(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get(`overtime/${id}`);
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
