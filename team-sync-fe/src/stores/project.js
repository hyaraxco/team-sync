import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useProjectStore = defineStore("project", {
    state: () => ({
        projects: [],
        squadSummary: null,
        statistics: {
            total: 0,
            active: 0,
        },
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        },
        loading: false,
        loadingSummary: false,
        loadingStatistics: false,
        error: null,
        success: null,
    }),

    actions: {
        async fetchProjects(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`projects`, { params });

                this.projects = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchProjectsPaginated(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/projects/all/paginated", { params });

                this.projects = response.data.data.data;
                this.meta = response.data.data.meta;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchProjectById(id) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`projects/${id}`);

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchProject(id) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`projects/${id}`);

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchProjectSquadSummary(id) {
            this.loadingSummary = true;

            try {
                const response = await axiosInstance.get(`projects/${id}/squad-summary`);
                this.squadSummary = response.data?.data || null;

                return this.squadSummary;
            } catch (error) {
                this.error = handleError(error);
                this.squadSummary = null;

                return null;
            } finally {
                this.loadingSummary = false;
            }
        },

        async createProject(payload) {
            this.loading = true;

            try {
                const formData = new FormData();

                Object.entries(payload).forEach(([key, value]) => {
                    if (key === "photo_url") return; // skip local preview URL

                    if (key === "teams" && Array.isArray(value)) {
                        value.forEach((id) => formData.append("teams[]", id));
                    } else if (value !== null && value !== undefined && value !== "") {
                        formData.append(key, value);
                    }
                });

                const response = await axiosInstance.post("projects", formData, {
                    headers: { "Content-Type": "multipart/form-data" },
                });

                this.success = response.data.message;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async updateProject(id, payload) {
            this.loading = true;

            try {
                const formData = new FormData();
                formData.append("_method", "PUT");

                Object.entries(payload).forEach(([key, value]) => {
                    if (key === "photo_url") return; // skip local preview URL

                    if (key === "teams" && Array.isArray(value)) {
                        value.forEach((teamId) => formData.append("teams[]", teamId));
                    } else if (value !== null && value !== undefined && value !== "") {
                        formData.append(key, value);
                    }
                });

                const response = await axiosInstance.post(`projects/${id}`, formData, {
                    headers: { "Content-Type": "multipart/form-data" },
                });

                this.success = response.data.message;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchStatistics() {
            this.loadingStatistics = true;
            this.error = null;

            try {
                const response = await axiosInstance.get("/projects/statistics");

                this.statistics = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loadingStatistics = false;
            }
        },

        async deleteProject(id) {
            this.loading = true;
            this.error = null;
            this.success = null;

            try {
                const response = await axiosInstance.delete(`projects/${id}`);

                this.success = response.data.message;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchEligibleLeaders(id, params = {}) {
            try {
                const response = await axiosInstance.get(`projects/${id}/eligible-leaders`, { params });

                return response.data?.data || [];
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchProjectMembers(projectId) {
            this.error = null;

            try {
                const response = await axiosInstance.get(`projects/${projectId}/members`);

                return response.data?.data || [];
            } catch (error) {
                if (error.response?.status !== 403) {
                    this.error = handleError(error);
                }

                return [];
            }
        },

        async updateProjectLeader(id, leaderId) {
            this.error = null;
            this.success = null;

            try {
                const response = await axiosInstance.put(`projects/${id}/leader`, {
                    project_leader_id: leaderId,
                });

                this.success = response.data.message;

                return response.data?.data || null;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },
    },
});
