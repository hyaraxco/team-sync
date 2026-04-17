import { defineStore } from "pinia";
import { axiosInstance } from '@/plugins/axios';
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
            total: 0
        },
        loading: false,
        loadingSummary: false,
        loadingStatistics: false,
        error: null,
        success: null,
    }),

    actions: {
        async fetchProjects(params) {
            this.loading = true

            try {
                const response = await axiosInstance.get(`projects`, { params })

                this.projects = response.data.data
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async fetchProjectsPaginated(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get('/projects/all/paginated', { params });

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
                const response = await axiosInstance.post('projects', payload);

                this.success = response.data.message;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async updateProject(id, payload) {
            this.loading = true;

            try {
                const response = await axiosInstance.post(`projects/${id}`, {
                    ...payload,
                    _method: 'PUT',
                });

                this.success = response.data.message;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchStatistics() {
            this.loadingStatistics = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('/projects/statistics');

                this.statistics = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loadingStatistics = false;
            }
        },

    }
})