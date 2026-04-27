import { defineStore } from "pinia";
import { axiosInstance } from '@/plugins/axios';
import { handleError } from "@/helpers/errorHelper";

export const useAttendancePeriodStore = defineStore("attendancePeriod", {
    state: () => ({
        periods: [],
        paginatedPeriods: [],
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0
        },
        readinessSummary: null,
        loading: false,
        error: null,
        success: null,
    }),

    actions: {
        async fetchAllPaginated(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get('attendance-periods', {
                    params: {
                        page: params.page || 1,
                        search: params.search || '',
                        row_per_page: params.row_per_page || 10,
                    },
                });
                const paginator = response.data.data;
                this.paginatedPeriods = paginator.data;
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

        async fetchReadiness(periodId) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get(`payrolls/generate-readiness`, { params: { period_id: periodId } });
                this.readinessSummary = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        }
    }
});
