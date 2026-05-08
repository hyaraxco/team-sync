import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useOptionStore = defineStore("option", {
    state: () => ({
        departments: [],
        employmentTypes: [],
        jobStatuses: [],
        leaveTypes: [],
        workLocations: [],
        religions: [],
        maritalStatuses: [],
        bloodTypes: [],
        ptkpStatuses: [],
        projectTaskTemplates: [],
        loading: false,
        error: null,
    }),

    actions: {
        async fetchDepartments() {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/options/departments");
                this.departments = response.data.data;
            } catch (error) {
                this.error = handleError(error);
                console.error("Failed to fetch departments:", error);
            } finally {
                this.loading = false;
            }
        },

        async fetchEmploymentTypes() {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/options/employment-types");
                this.employmentTypes = response.data.data;
            } catch (error) {
                this.error = handleError(error);
                console.error("Failed to fetch employment types:", error);
            } finally {
                this.loading = false;
            }
        },

        async fetchJobStatuses() {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/options/job-statuses");
                this.jobStatuses = response.data.data;
            } catch (error) {
                this.error = handleError(error);
                console.error("Failed to fetch job statuses:", error);
            } finally {
                this.loading = false;
            }
        },

        async fetchLeaveTypes() {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/options/leave-types");
                this.leaveTypes = response.data.data;
            } catch (error) {
                this.error = handleError(error);
                console.error("Failed to fetch leave types:", error);
            } finally {
                this.loading = false;
            }
        },

        async fetchWorkLocations() {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/options/work-locations");
                this.workLocations = response.data.data;
            } catch (error) {
                this.error = handleError(error);
                console.error("Failed to fetch work locations:", error);
            } finally {
                this.loading = false;
            }
        },

        async fetchReligions() {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/options/religions");
                this.religions = response.data.data;
            } catch (error) {
                this.error = handleError(error);
                console.error("Failed to fetch religions:", error);
            } finally {
                this.loading = false;
            }
        },

        async fetchMaritalStatuses() {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/options/marital-statuses");
                this.maritalStatuses = response.data.data;
            } catch (error) {
                this.error = handleError(error);
                console.error("Failed to fetch marital statuses:", error);
            } finally {
                this.loading = false;
            }
        },

        async fetchBloodTypes() {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/options/blood-types");
                this.bloodTypes = response.data.data;
            } catch (error) {
                this.error = handleError(error);
                console.error("Failed to fetch blood types:", error);
            } finally {
                this.loading = false;
            }
        },

        async fetchPtkpStatuses() {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/options/ptkp-statuses");
                this.ptkpStatuses = response.data.data;
            } catch (error) {
                this.error = handleError(error);
                console.error("Failed to fetch PTKP statuses:", error);
            } finally {
                this.loading = false;
            }
        },

        async fetchProjectTaskTemplates() {
            this.loading = true;

            try {
                const response = await axiosInstance.get("/options/project-task-templates");
                this.projectTaskTemplates = response.data.data;
            } catch (error) {
                this.error = handleError(error);
                console.error("Failed to fetch project task templates:", error);
            } finally {
                this.loading = false;
            }
        },
    },
});
