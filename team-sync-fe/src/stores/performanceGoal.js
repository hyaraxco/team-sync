import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const usePerformanceGoalStore = defineStore("performanceGoal", {
    state: () => ({
        // Goals
        myGoals: [],
        teamGoals: [],
        currentGoal: null,
        goalsLoading: false,

        // Goal Updates
        goalUpdates: [],
        updatesLoading: false,

        // Pagination
        pagination: {
            current_page: 1,
            per_page: 15,
            total: 0,
            last_page: 1,
        },

        error: null,
        success: false,
    }),

    actions: {
        async fetchMyGoals(filters = {}) {
            this.goalsLoading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get("/performance/goals/my-goals", { params: filters });
                this.myGoals = response.data.data.data || [];
                this.pagination = {
                    current_page: response.data.data.current_page,
                    per_page: response.data.data.per_page,
                    total: response.data.data.total,
                    last_page: response.data.data.last_page,
                };
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.goalsLoading = false;
            }
        },

        async fetchGoals() {
            this.goalsLoading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get("/performance/goals");
                this.myGoals = response.data.data.data || [];
                this.pagination = {
                    current_page: response.data.data.current_page,
                    per_page: response.data.data.per_page,
                    total: response.data.data.total,
                    last_page: response.data.data.last_page,
                };
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.goalsLoading = false;
            }
        },

        async fetchTeamGoals(filters = {}) {
            this.goalsLoading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get("/performance/goals/team-goals", { params: filters });
                this.teamGoals = response.data.data.data || [];
                this.pagination = {
                    current_page: response.data.data.current_page,
                    per_page: response.data.data.per_page,
                    total: response.data.data.total,
                    last_page: response.data.data.last_page,
                };
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.goalsLoading = false;
            }
        },

        async fetchGoalById(id) {
            this.goalsLoading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get(`/performance/goals/${id}`);
                this.currentGoal = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.goalsLoading = false;
            }
        },

        async createGoal(data) {
            this.goalsLoading = true;
            this.error = null;
            this.success = false;
            try {
                const response = await axiosInstance.post("/performance/goals", data);
                this.myGoals.unshift(response.data.data);
                this.success = true;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.goalsLoading = false;
            }
        },

        async updateGoal(id, data) {
            this.goalsLoading = true;
            this.error = null;
            this.success = false;
            try {
                const response = await axiosInstance.put(`/performance/goals/${id}`, data);
                const myIndex = this.myGoals.findIndex((g) => g.id === id);
                if (myIndex !== -1) {
                    this.myGoals[myIndex] = response.data.data;
                }
                const teamIndex = this.teamGoals.findIndex((g) => g.id === id);
                if (teamIndex !== -1) {
                    this.teamGoals[teamIndex] = response.data.data;
                }
                if (this.currentGoal?.id === id) {
                    this.currentGoal = response.data.data;
                }
                this.success = true;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.goalsLoading = false;
            }
        },

        async deleteGoal(id) {
            this.goalsLoading = true;
            this.error = null;
            try {
                await axiosInstance.delete(`/performance/goals/${id}`);
                this.myGoals = this.myGoals.filter((g) => g.id !== id);
                this.teamGoals = this.teamGoals.filter((g) => g.id !== id);
                return true;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.goalsLoading = false;
            }
        },

        async addProgressUpdate(goalId, data) {
            this.updatesLoading = true;
            this.error = null;
            this.success = false;
            try {
                const response = await axiosInstance.post(`/performance/goals/${goalId}/update-progress`, data);
                this.goalUpdates.unshift(response.data.data);
                // Refresh the goal to get updated values
                await this.fetchGoalById(goalId);
                this.success = true;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.updatesLoading = false;
            }
        },

        async fetchProgressUpdates(goalId) {
            this.updatesLoading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get(`/performance/goals/${goalId}/updates`);
                this.goalUpdates = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.updatesLoading = false;
            }
        },

        resetState() {
            this.error = null;
            this.success = false;
        },
    },
});
