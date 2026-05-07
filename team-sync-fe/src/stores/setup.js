import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useSetupStore = defineStore("setup", {
    state: () => ({
        // Setup status
        needsSetup: null,
        hasLicense: false,
        hasCompany: false,
        hasSuperadmin: false,
        statusLoading: false,

        // Doctor checks
        doctorResult: null,
        doctorLoading: false,

        // License verification
        licenseVerifyResult: null,
        licenseVerifyLoading: false,

        // Bootstrap
        bootstrapResult: null,
        bootstrapLoading: false,

        error: null,
    }),

    getters: {
        isDoctorHealthy: (state) => state.doctorResult?.healthy === true,
        doctorChecks: (state) => state.doctorResult?.checks || [],
    },

    actions: {
        async fetchSetupStatus() {
            this.statusLoading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get("/setup/status");
                const data = response.data.data;

                this.needsSetup = data.needs_setup;
                this.hasLicense = data.has_license;
                this.hasCompany = data.has_company;
                this.hasSuperadmin = data.has_superadmin;

                return data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.statusLoading = false;
            }
        },

        async fetchDoctor() {
            this.doctorLoading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get("/setup/doctor");
                this.doctorResult = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.doctorLoading = false;
            }
        },

        async verifyLicense(licenseKey) {
            this.licenseVerifyLoading = true;
            this.licenseVerifyResult = null;
            this.error = null;

            try {
                const response = await axiosInstance.post("/licenses/verify", {
                    license_key: licenseKey,
                });
                this.licenseVerifyResult = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.licenseVerifyLoading = false;
            }
        },

        async activateLicense(licenseKey, companyName = null, contactEmail = null) {
            this.error = null;

            try {
                const response = await axiosInstance.post("/licenses", {
                    license_key: licenseKey,
                    company_name: companyName,
                    contact_email: contactEmail,
                });

                this.hasLicense = true;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async bootstrap(name, email, password, passwordConfirmation) {
            this.bootstrapLoading = true;
            this.bootstrapResult = null;
            this.error = null;

            try {
                const response = await axiosInstance.post("/setup/bootstrap", {
                    name,
                    email,
                    password,
                    password_confirmation: passwordConfirmation,
                });

                this.bootstrapResult = response.data.data;
                this.hasSuperadmin = true;
                this.needsSetup = false;

                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.bootstrapLoading = false;
            }
        },

        resetError() {
            this.error = null;
        },
    },
});
