import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";

export const useAttendancePolicyStore = defineStore("attendancePolicy", {
    state: () => ({
        policies: [],
        loading: false,
        error: null,
    }),
    actions: {
        async fetchPolicies() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get("attendance-policies");
                this.policies = response.data.data;
            } catch (error) {
                this.error = error.message || "Failed to fetch attendance policies";
            } finally {
                this.loading = false;
            }
        },
        async updatePolicy(id, data) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.put(`attendance-policies/${id}`, data);
                const index = this.policies.findIndex((p) => p.id === id);
                if (index !== -1) {
                    this.policies[index] = response.data.data;
                }
                return response.data.data;
            } catch (error) {
                this.error = error.message || "Failed to update attendance policy";
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
