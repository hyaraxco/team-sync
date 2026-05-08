import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useHybridScheduleStore = defineStore("hybridSchedule", {
    state: () => ({
        schedules: [],
        paginatedSchedules: [],
        overrides: [],
        mySchedule: null,
        myOverrides: [],
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        },
        loading: false,
        error: null,
        success: null,
    }),

    actions: {
        async fetchAllPaginated(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get("hybrid-schedules", {
                    params: {
                        page: params.page || 1,
                        search: params.search || "",
                        row_per_page: params.row_per_page || 10,
                    },
                });
                const paginator = response.data.data;
                this.paginatedSchedules = paginator.data;
                this.meta = {
                    current_page: paginator.current_page,
                    last_page: paginator.last_page,
                    per_page: paginator.per_page,
                    total: paginator.total,
                };
                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchMySchedule() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get("my-hybrid-schedule");
                this.mySchedule = response.data.data;
                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchMyOverrides() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get("my-hybrid-overrides");
                this.myOverrides = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async createOverride(data) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post("hybrid-schedule-overrides", data);
                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async approveOverride(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post(`hybrid-schedule-overrides/${id}/approve`);
                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async rejectOverride(id, notes) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post(`hybrid-schedule-overrides/${id}/reject`, {
                    review_notes: notes,
                });
                this.success = response.data.message;
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
