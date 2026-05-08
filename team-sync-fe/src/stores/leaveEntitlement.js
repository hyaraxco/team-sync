import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";

export const useLeaveEntitlementStore = defineStore("leaveEntitlement", {
    state: () => ({
        entitlements: [],
        groupedEntitlements: {},
        loading: false,
        error: null,
    }),
    actions: {
        async fetchEntitlements() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.get("leave-entitlements");
                this.entitlements = response.data.data.items;
                this.groupedEntitlements = response.data.data.grouped;
            } catch (error) {
                this.error = error.message || "Failed to fetch leave entitlements";
                console.error("Error fetching leave entitlements:", error);
            } finally {
                this.loading = false;
            }
        },
        async updateEntitlement(id, data) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axiosInstance.put(`leave-entitlements/${id}`, data);

                // Update local state arrays
                const index = this.entitlements.findIndex((e) => e.id === id);
                if (index !== -1) {
                    this.entitlements[index] = response.data.data;
                }

                // Refresh full state to easily re-group
                await this.fetchEntitlements();

                return response.data.data;
            } catch (error) {
                this.error = error.message || "Failed to update leave entitlement";
                console.error("Error updating leave entitlement:", error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
