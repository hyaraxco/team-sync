import { defineStore } from 'pinia';
import { axiosInstance } from '@/plugins/axios';

export const useThrStore = defineStore('thr', {
    state: () => ({
        thrPayrolls: [],
        thrPayroll: null,
        thrDetails: [],
        yearSummary: null,
        simulation: null,
        meta: null,
        detailsMeta: null,
        loading: false,
        error: null,
    }),

    actions: {
        async fetchThrPayrolls(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get('/thr', { params });
                this.thrPayrolls = response.data.data?.data || [];
                this.meta = response.data.data?.meta || null;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch THR payrolls';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchThrPayroll(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get(`/thr/${id}`);
                this.thrPayroll = response.data.data;
                return this.thrPayroll;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch THR payroll';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchThrDetails(id, params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get(`/thr/${id}/details`, { params });
                this.thrDetails = response.data.data?.data || [];
                this.detailsMeta = response.data.data?.meta || null;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch THR details';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchYearSummary(year) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get('/thr/year-summary', { params: { year } });
                this.yearSummary = response.data.data;
                return this.yearSummary;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch year summary';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async simulate(data) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post('/thr/simulate', data);
                this.simulation = response.data.data;
                return this.simulation;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to simulate THR';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async generate(data) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post('/thr/generate', data);
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to generate THR';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async approve(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post(`/thr/${id}/approve`);
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to approve THR';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async markAsPaid(id, paymentDate) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.post(`/thr/${id}/mark-as-paid`, {
                    payment_date: paymentDate,
                });
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to mark THR as paid';
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
