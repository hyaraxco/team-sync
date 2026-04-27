import { defineStore } from "pinia";
import { axiosInstance } from '@/plugins/axios';
import { handleError } from "@/helpers/errorHelper";

export const useHolidayCalendarStore = defineStore("holidayCalendar", {
    state: () => ({
        holidays: [],
        paginatedHolidays: [],
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
        async fetchAllPaginated(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get('holiday-calendars', {
                    params: {
                        page: params.page || 1,
                        search: params.search || '',
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
                if (import.meta.env.DEV || import.meta.env.TEST) {
                    console.warn('[MOCK DATA] Returning mock Indonesian holidays due to API failure.');
                    const mockData = [
                        { id: 1, date: '2026-01-01', description: 'Tahun Baru', type: 'national_holiday' },
                        { id: 2, date: '2026-01-16', description: 'Isra\' Mi\'raj Nabi Muhammad SAW', type: 'national_holiday' },
                        { id: 3, date: '2026-02-16', description: 'Cuti Bersama Tahun Baru Imlek', type: 'collective_leave' },
                        { id: 4, date: '2026-02-17', description: 'Tahun Baru Imlek', type: 'national_holiday' },
                        { id: 5, date: '2026-03-18', description: 'Cuti Bersama Hari Raya Nyepi', type: 'collective_leave' },
                        { id: 6, date: '2026-03-19', description: 'Hari Raya Nyepi', type: 'national_holiday' },
                        { id: 7, date: '2026-03-20', description: 'Hari Raya Idul Fitri', type: 'national_holiday' },
                        { id: 8, date: '2026-03-21', description: 'Hari Raya Idul Fitri', type: 'national_holiday' }
                    ];
                    this.paginatedHolidays = mockData;
                    this.meta = { current_page: 1, last_page: 1, per_page: 10, total: mockData.length };
                    return { data: { data: mockData, current_page: 1, last_page: 1, total: mockData.length } };
                }
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
                const response = await axiosInstance.post('holiday-calendars', data);
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
        }
    }
});
