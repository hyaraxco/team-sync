import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useHolidayCalendarStore = defineStore("holidayCalendar", {
    state: () => ({
        holidays: [],
        paginatedHolidays: [],
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        },
        loading: false,
        error: null,
        success: null,
    }),

    actions: {
        async fetchAllPaginated(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get("holiday-calendars", {
                    params: {
                        page: params.page || 1,
                        search: params.search || "",
                        row_per_page: params.row_per_page || 10,
                    },
                });
                const paginator = response.data.data;
                this.paginatedHolidays = paginator.data;
                this.meta = {
                    current_page: paginator.current_page,
                    last_page: paginator.last_page,
                    per_page: paginator.per_page,
                    total: paginator.total,
                };
                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createHoliday(data) {
            this.loading = true;
            this.error = null;
            this.success = null;
            try {
                const response = await axiosInstance.post("holiday-calendars", data);
                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async updateHoliday(id, data) {
            this.loading = true;
            this.error = null;
            this.success = null;
            try {
                const response = await axiosInstance.put(`holiday-calendars/${id}`, data);
                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async deleteHoliday(id) {
            this.loading = true;
            this.error = null;
            this.success = null;
            try {
                const response = await axiosInstance.delete(`holiday-calendars/${id}`);
                this.success = response.data.message;
                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchHoliday(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get(`holiday-calendars/${id}`);
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
