import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useStaffMemberStore = defineStore("staffMember", {
    state: () => ({
        staffMembers: [],
        latestStaffMembers: [],
        statistics: {
            total: 0,
            added_this_month: 0,
            active: 0,
            active_change: 0,
            on_leave: 0,
            on_leave_change: 0,
            average_salary: 0,
            new_employees: 0,
        },
        performanceStatistics: {
            tasks_completed: 0,
            attendance_rate: 0,
            projects_count: 0,
            performance_score: 0,
        },
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        },
        loading: false,
        loadingLatest: false,
        loadingStatistics: false,
        error: null,
        success: null,
    }),

    actions: {
        async fetchStaffMembers(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`staff-members`, { params });

                this.staffMembers = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchStaffMembersPaginated(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/staff-members/all/paginated", { params });

                this.staffMembers = response.data.data.data;
                this.meta = response.data.data.meta;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchStaffMember(id) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`staff-members/${id}`);

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async createStaffMember(payload) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.post("staff-members", payload, {
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                });

                this.success = response.data.message;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async checkAvailability(payload) {
            try {
                const response = await axiosInstance.post("staff-members/check-availability", payload);

                return response.data?.data;
            } catch (error) {
                throw error;
            }
        },

        async updateStaffMember(id, payload) {
            this.loading = true;

            try {
                // Add _method PUT for Laravel to handle multipart form data
                payload.append("_method", "PUT");

                const response = await axiosInstance.post(`staff-members/${id}`, payload, {
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                });

                this.success = response.data.message;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async deleteStaffMember(id) {
            this.loading = true;
            this.error = null;
            this.success = null;

            try {
                const response = await axiosInstance.delete(`staff-members/${id}`);

                this.success = response.data.message;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchLatestStaffMembers(params = {}) {
            this.loadingLatest = true;

            try {
                const response = await axiosInstance.get("/staff-members", {
                    params: {
                        limit: 5,
                        ...params,
                    },
                });

                this.latestStaffMembers = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loadingLatest = false;
            }
        },

        async fetchStatistics() {
            this.loadingStatistics = true;
            this.error = null;

            try {
                const response = await axiosInstance.get("/staff-members/statistics");

                this.statistics = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loadingStatistics = false;
            }
        },

        async fetchPerformanceStatistics(employeeId) {
            this.loadingStatistics = true;
            this.error = null;

            try {
                const response = await axiosInstance.get(`/staff-members/${employeeId}/performance-statistics`);

                this.performanceStatistics = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loadingStatistics = false;
            }
        },

        async fetchMyProfile() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get("/my-profile");

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchMyTeam() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get("/my-team");

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchMyTeamMembers() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get("/my-team/members");

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchMyTeamProjects() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get("/my-team/projects");

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
