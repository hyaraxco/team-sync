import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
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
        teamPulse: null,
        teamPulseLoading: false,
        teamPulseNudgingIds: [],
        myStatistics: null,
        myStatisticsLoading: false,
    }),

    actions: {
        async fetchMyStatistics() {
            this.myStatisticsLoading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get("/dashboard/my-statistics");
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
                const response = await axiosInstance.get("/dashboard/statistics");

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
                const response = await axiosInstance.get("/dashboard/today-attendance-overview");
                this.todayAttendance = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.todayAttendanceLoading = false;
            }
        },

        async fetchTeamPulse() {
            this.teamPulseLoading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get("/dashboard/team-pulse");
                this.teamPulse = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.teamPulseLoading = false;
            }
        },

        async sendTeamPulseNudge(staffMemberId, message = null) {
            const id = String(staffMemberId);
            this.teamPulseNudgingIds = Array.from(new Set([...this.teamPulseNudgingIds, id]));
            this.error = null;

            try {
                const response = await axiosInstance.post(`/dashboard/team-pulse/${staffMemberId}/nudge`, {
                    message,
                });

                const payload = response.data.data;

                if (this.teamPulse?.staff_members) {
                    this.teamPulse.staff_members = this.teamPulse.staff_members.map((member) => {
                        if (String(member.id) !== id) {
                            return member;
                        }

                        return {
                            ...member,
                            nudge: {
                                ...(member.nudge || {}),
                                status: "sent",
                                last_sent_at: payload.sent_at,
                            },
                        };
                    });
                }

                return payload;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.teamPulseNudgingIds = this.teamPulseNudgingIds.filter((item) => item !== id);
            }
        },
    },
});
