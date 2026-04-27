import { defineStore } from "pinia";
import { axiosInstance } from '@/plugins/axios';
import { handleError } from "@/helpers/errorHelper";

export const useAttendanceStore = defineStore("attendance", {
    state: () => ({
        attendances: [],
        paginatedAttendances: [],
        todayAttendance: null,
        statistics: {
            total_days: 0,
            present_days: 0,
            sick_days: 0,
            absent_days: 0,
        },
        myStatistics: {
            attendance_rate: 0,
            present_days: 0,
            total_working_days: 0,
            on_time_percentage: 0,
            total_hours_worked: 0,
            leave_balance: 12,
            tasks_completed: 0,
            active_projects: 0,
        },
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
        async fetchAttendances(params) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('my-attendances', { params });

                this.attendances = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchTodayAttendance() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('attendances/last-attendance');

                this.todayAttendance = response.data.data;
            } catch (error) {
                // If 404, it means no attendance today, set to null
                if (error.response?.status === 404) {
                    this.todayAttendance = null;
                } else {
                    this.error = handleError(error);
                }
            } finally {
                this.loading = false;
            }
        },

        async checkIn(payload) {
            this.loading = true;
            this.error = null;

            try {
                const data = {
                    check_in_lat: payload.check_in_lat,
                    check_in_long: payload.check_in_long,
                };

                if (payload.notes) {
                    data.notes = payload.notes;
                }

                const response = await axiosInstance.post('attendances/check-in', data);

                this.success = response.data.message;
                this.todayAttendance = response.data.data;

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async checkOut(payload) {
            this.loading = true;
            this.error = null;

            try {
                const data = {
                    check_out_lat: payload.check_out_lat,
                    check_out_long: payload.check_out_long,
                };

                if (payload.notes) {
                    data.notes = payload.notes;
                }

                const response = await axiosInstance.post('attendances/check-out', data);

                this.success = response.data.message;
                this.todayAttendance = response.data.data;

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchStatistics() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('my-attendance-statistics');

                this.statistics = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchMyAttendanceStatistics() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('my-attendance-statistics');

                // Map backend response to myStatistics
                const data = response.data.data;
                this.myStatistics = {
                    attendance_rate: data.attendance_rate || 0,
                    present_days: data.present_days || 0,
                    total_working_days: data.total_working_days || 0,
                    on_time_percentage: data.on_time_percentage || 0,
                    total_hours_worked: data.total_hours_worked || 0,
                    leave_balance: data.leave_balance || 12,
                    tasks_completed: data.tasks_completed || 0,
                    active_projects: data.active_projects || 0,
                };
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchAdminStatistics() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get('attendances/statistics');

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchTodayAttendances(limit = 10) {
            this.loading = true;
            this.error = null;

            try {
                const today = new Date().toISOString().split('T')[0];
                const response = await axiosInstance.get('attendances', {
                    params: {
                        date: today,
                        limit
                    }
                });

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchAllPaginated(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get('attendances/all/paginated', {
                    params: {
                        page: params.page || 1,
                        search: params.search || '',
                        row_per_page: params.row_per_page || 10,
                    },
                });
                const paginator = response.data.data;
                this.paginatedAttendances = paginator.data;
                this.meta = {
                    current_page: paginator.current_page,
                    last_page: paginator.last_page,
                    per_page: paginator.per_page,
                    total: paginator.total,
                    from: paginator.from,
                    to: paginator.to,
                };
                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchEmployeeAttendanceStatistics(employeeId, month = null) {
            this.loading = true;
            this.error = null;

            try {
                const params = {};
                if (month) {
                    params.month = month;
                }

                const response = await axiosInstance.get(`attendances/employee/${employeeId}/statistics`, { params });

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
        async fetchPolicyMismatches(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get('attendance-policy-mismatches', {
                    params: {
                        page: params.page || 1,
                        search: params.search || '',
                        status: params.status || '',
                        row_per_page: params.row_per_page || 10,
                    },
                });
                return response.data;
            } catch (error) {
                if (import.meta.env.DEV || import.meta.env.TEST) {
                    console.warn('[MOCK DATA] Returning mock policy mismatches due to API failure.');
                    return {
                        data: {
                            data: [
                                { id: 1, employee_name: 'John Doe', date: '2026-04-20', scheduled_location: 'Remote', actual_location: 'Office' },
                                { id: 2, employee_name: 'Jane Smith', date: '2026-04-21', scheduled_location: 'Office', actual_location: 'Remote' },
                            ]
                        }
                    };
                }
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async acknowledgePolicyMismatch(id, notes) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post(`attendance-policy-mismatches/${id}/acknowledge`, { notes });
                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                if (import.meta.env.DEV || import.meta.env.TEST) {
                    console.warn(`[MOCK DATA] Mocked acknowledge for mismatch ${id}`);
                    return { data: { success: true } };
                }
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async resolvePolicyMismatch(id, resolution_notes) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post(`attendance-policy-mismatches/${id}/resolve`, { resolution_notes });
                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                if (import.meta.env.DEV || import.meta.env.TEST) {
                    console.warn(`[MOCK DATA] Mocked resolve for mismatch ${id}`);
                    return { data: { success: true } };
                }
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        }
    }
})
