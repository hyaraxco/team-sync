import { defineStore } from "pinia";
import { axiosInstance } from '@/plugins/axios';
import { handleError } from "@/helpers/errorHelper";

export const useDashboardStore = defineStore("dashboard", {
    state: () => ({
        statistics: {
            employees: {
                total: 0,
                added_this_month: 0,
            },
            teams: {
                total: 0,
                new_teams: 0,
            },
            attendance: {
                rate: 0,
                change: 0,
            },
            tasks: {
                completed: 0,
                change: 0,
            },
            projects: {
                active: 0,
                new_projects: 0,
            },
            performance: {
                promotion_eligible: 0,
                pip_required: 0,
            },
        },
        loading: false,
        error: null,
        todayAttendance: null,
        todayAttendanceLoading: false,
        myStatistics: null,
        myStatisticsLoading: false,
    }),

    actions: {
        async fetchMyStatistics() {
            this.myStatisticsLoading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('/dashboard/my-statistics');
                this.myStatistics = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.myStatisticsLoading = false;
            }
        },

        async fetchStatistics() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('/dashboard/statistics');

                this.statistics = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchTodayAttendance() {
            this.todayAttendanceLoading = true;
            try {
                const response = await axiosInstance.get('/dashboard/today-attendance-overview');
                this.todayAttendance = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.todayAttendanceLoading = false;
            }
        },
    }
});
