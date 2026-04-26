import { defineStore } from "pinia";
import { axiosInstance } from '@/plugins/axios';
import { handleError } from "@/helpers/errorHelper";

export const useLeaveRequestStore = defineStore("leaveRequest", {
    state: () => ({
        leaveRequests: [],
        myLeaveRequests: [],
        myLeaveBalances: [],
        calendarData: [],
        currentLeaveRequest: null,
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0
        },
        loading: false,
        error: null,
        success: null,
    }),

    actions: {
        async fetchMyLeaveRequests() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('my-leave-requests');

                this.myLeaveRequests = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchMyLeaveBalances() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('my-leave-balances');

                this.myLeaveBalances = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async createLeaveRequest(payload) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.post('leave-requests', payload);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async getLeaveRequestById(id) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get(`leave-requests/${id}`);

                this.currentLeaveRequest = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchLatestLeaveRequests(limit = 5) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('leave-requests', {
                    params: { limit }
                });

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchLeaveRequestsPaginated(params = {}) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('leave-requests/all/paginated', { params });
                this.leaveRequests = response.data.data.items;
                this.meta = response.data.data.meta;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchCalendarData(month) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('leave-requests/all/calendar', {
                    params: { month }
                });
                this.calendarData = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async approveLeaveRequest(id) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.post(`leave-requests/approve/${id}`);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async rejectLeaveRequest(id) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.post(`leave-requests/reject/${id}`);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async uploadProof(id, file) {
            this.loading = true;
            this.error = null;

            try {
                const formData = new FormData();
                formData.append('proof_file', file);
                
                const response = await axiosInstance.post(`leave-requests/${id}/proof`, formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
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

        async reviewProof(id, payload) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.post(`leave-requests/${id}/proof-review`, payload);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
    }
})
