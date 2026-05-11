import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useAttendanceCorrectionStore = defineStore("attendanceCorrection", {
    state: () => ({
        myCorrections: [],
        paginatedCorrections: [],
        loading: false,
        error: null,
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        },
    }),

    actions: {
        async fetchMyCorrections() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get("my-attendance-corrections");
                this.myCorrections = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchAllPaginated(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get("attendance-corrections/all/paginated", {
                    params: {
                        page: params.page || 1,
                        search: params.search || "",
                        row_per_page: params.row_per_page || 10,
                        status: params.status || "",
                    },
                });
                const paginator = response.data.data;
                this.paginatedCorrections = paginator.data;
                this.meta = {
                    current_page: paginator.current_page,
                    last_page: paginator.last_page,
                    per_page: paginator.per_page,
                    total: paginator.total,
                    from: paginator.from,
                    to: paginator.to,
                };
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async requestCorrection(payload) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post("attendance-corrections", payload);
                if (this.myCorrections) {
                    this.myCorrections.unshift(response.data.data);
                }
                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async approveCorrection(id, payload) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post(`attendance-corrections/${id}/approve`, payload);
                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async rejectCorrection(id, payload) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post(`attendance-corrections/${id}/reject`, payload);
                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchCorrection(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get(`attendance-corrections/${id}`);
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
