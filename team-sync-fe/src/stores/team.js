import { defineStore } from "pinia";
import { axiosInstance } from '@/plugins/axios';
import { handleError } from "@/helpers/errorHelper";

export const useTeamStore = defineStore("team", {
    state: () => ({
        teams: [],
        latestTeams: [],
        statistics: {
            total: 0,
            added_this_month: 0,
            active: 0,
            active_change: 0,
            members: 0,
            members_change: 0,
            average_size: 0,
            new_teams: 0,
        },
        teamStatistics: {
            active_members: 0,
            projects_assigned: 0,
            projects_in_progress: 0,
            team_performance: 0,
            completion_rate: 0,
        },
        teamChartData: {
            months: [],
            member_growth: [],
            task_growth: [],
        },
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0
        },
        loading: false,
        loadingLatest: false,
        loadingStatistics: false,
        loadingTeamStatistics: false,
        loadingTeamChart: false,
        error: null,
        success: null,
    }),

    actions: {
        async fetchTeams(params) {
            this.loading = true

            try {
                const response = await axiosInstance.get(`teams`, { params })

                this.teams = response.data.data
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async fetchTeamsPaginated(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get('/teams/all/paginated', { params });

                this.teams = response.data.data.data;
                this.meta = response.data.data.meta;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchTeam(id) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`teams/${id}`);

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async createTeam(payload) {
            this.loading = true;

            try {
                const response = await axiosInstance.post('teams', payload);

                this.success = response.data.message;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async updateTeam(id, payload) {
            this.loading = true;

            try {
                const response = await axiosInstance.post(`teams/${id}`, {
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

        async deleteTeam(id) {
            this.loading = true;
            this.error = null;
            this.success = null;

            try {
                const response = await axiosInstance.delete(`teams/${id}`);

                this.success = response.data.message;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },


        async fetchLatestTeams(params = {}) {
            this.loadingLatest = true;

            try {
                const response = await axiosInstance.get('/teams', {
                    params: {
                        limit: 5,
                        ...params,
                    }
                });

                this.latestTeams = response.data.data;
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
                const response = await axiosInstance.get('/teams/statistics');

                this.statistics = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loadingStatistics = false;
            }
        },

        async fetchTeamStatistics(teamId) {
            this.loadingTeamStatistics = true;
            this.error = null;

            try {
                const response = await axiosInstance.get(`/teams/${teamId}/statistics`);

                this.teamStatistics = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loadingTeamStatistics = false;
            }
        },

        async fetchTeamChartData(teamId) {
            this.loadingTeamChart = true;
            this.error = null;

            try {
                const response = await axiosInstance.get(`/teams/${teamId}/chart-data`);

                this.teamChartData = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loadingTeamChart = false;
            }
        },

        async addMember(teamId, employeeId) {
            this.loading = true;
            this.error = null;
            this.success = null;

            try {
                const response = await axiosInstance.post(`/teams/${teamId}/add-member`, {
                    employee_id: employeeId
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

        async removeMember(teamId, employeeId) {
            this.loading = true;
            this.error = null;
            this.success = null;

            try {
                const response = await axiosInstance.post(`/teams/${teamId}/remove-member`, {
                    employee_id: employeeId
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

        selectLeadPayload(employee) {
            if (!employee) return { selectedLead: null, team_lead_id: null };
            return { selectedLead: employee, team_lead_id: employee.user?.id };
        },

        removeLeadPayload() {
            return { selectedLead: null, team_lead_id: null };
        },

        addResponsibility(list) {
            const arr = Array.isArray(list) ? [...list] : [];
            arr.push("");
            return arr;
        },

        removeResponsibility(list, idx) {
            const arr = Array.isArray(list) ? [...list] : [];
            if (idx >= 0 && idx < arr.length) {
                arr.splice(idx, 1);
            }
            return arr;
        },

    }
})
