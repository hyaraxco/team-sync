import { defineStore } from "pinia";
import { axiosInstance } from '@/plugins/axios';
import { handleError } from "@/helpers/errorHelper";

export const useMeetingStore = defineStore("meeting", {
    state: () => ({
        meetings: [],
        upcomingMeetings: [],
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0
        },
        loading: false,
        loadingUpcoming: false,
        error: null,
        success: null,
    }),

    actions: {
        async fetchMeetingsPaginated(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get('/meetings/all/paginated', { params });

                this.meetings = response.data.data.data;
                this.meta = response.data.data.meta;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchUpcomingMeetings(params = {}) {
            this.loadingUpcoming = true;

            try {
                const response = await axiosInstance.get('/meetings/upcoming', { params });

                this.upcomingMeetings = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loadingUpcoming = false;
            }
        },

        async createMeeting(payload) {
            this.loading = true;

            try {
                const response = await axiosInstance.post('meetings', payload);

                this.success = response.data.message;
                return response;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchMeeting(id) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`meetings/${id}`);

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },
    }
})
