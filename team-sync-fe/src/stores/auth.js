import { handleError } from "@/helpers/errorHelper";
import { axiosInstance } from "@/plugins/axios";
import Cookies from "js-cookie";
import { defineStore, getActivePinia } from "pinia";
import router from "@/router";

/**
 * Reset all non-auth Pinia stores to their initial state.
 * Called on logout to prevent stale data leaking into the next session.
 */
const resetAllStores = () => {
    const pinia = getActivePinia();
    if (!pinia) return;
    Object.values(pinia.state.value).forEach((storeState, index) => {
        const storeId = Object.keys(pinia.state.value)[index];
        if (storeId === "auth") return; // auth store resets itself
        try {
            pinia._s.get(storeId)?.$reset();
        } catch {
            // Some stores may not implement $reset — ignore silently
        }
    });
};

export const useAuthStore = defineStore("auth", {
    state: () => ({
        user: null,
        loading: false,
        error: null,
        success: null,
    }),
    getters: {
        token: () => Cookies.get("token"),
        isAuthenticated: (state) => !!state.user,
    },
    actions: {
        async login(credentials) {
            this.loading = true;
            this.error = null;
            this.success = null;

            try {
                const { remember = false, ...authPayload } = credentials || {};
                const response = await axiosInstance.post("/login", authPayload);

                const token = response.data.data.token;

                if (remember) {
                    Cookies.set("token", token, { expires: 30 });
                } else {
                    Cookies.set("token", token);
                }

                this.success = response.data.message;

                // Populate user immediately so the sidebar renders the correct
                // role/permissions before the router guard fires.
                await this.checkAuth();

                router.push({ name: "admin.dashboard" });
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async checkAuth() {
            this.loading = true;
            try {
                const response = await axiosInstance.get("/me");
                this.user = response.data.data;
                return this.user;
            } catch (error) {
                if (error.response && error.response.status === 401) {
                    Cookies.remove("token");
                    throw new Error("Unauthorized");
                }
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            this.loading = true;

            const token = this.token;
            Cookies.remove("token");

            if (axiosInstance?.defaults?.headers?.common) {
                delete axiosInstance.defaults.headers.common.Authorization;
            }

            // Reset all stores before navigation so the next login starts clean.
            this.user = null;
            this.error = null;
            this.loading = false;
            resetAllStores();

            const loginRoute = { name: "login" };
            const loginPath = "/auth/login";

            // Trigger SPA navigation without blocking the logout flow.
            void router.replace(loginRoute).catch(() => {});

            // Hard redirect fallback prevents "stuck" UI when SPA navigation hangs.
            if (typeof window !== "undefined") {
                try {
                    if (window.location.pathname !== loginPath) {
                        window.location.replace(loginPath);
                    }
                } catch {
                    // Ignore jsdom navigation errors in tests.
                }
            }

            if (!token) {
                return;
            }

            // Revoke token on server as a best-effort background call.
            void axiosInstance
                .post("/logout", null, {
                    timeout: 5000,
                    headers: {
                        Authorization: `Bearer ${token}`,
                    },
                })
                .catch(() => {});
        },

        async updateProfile(payload) {
            this.loading = true;
            this.error = null;
            this.success = null;

            try {
                const formData = new FormData();

                if (payload?.name) {
                    formData.append("name", payload.name);
                }

                if (payload?.password) {
                    formData.append("password", payload.password);
                    formData.append("password_confirmation", payload.password_confirmation || "");
                }

                if (payload?.profile_photo instanceof File) {
                    formData.append("profile_photo", payload.profile_photo);
                }

                formData.append("_method", "PUT");

                const response = await axiosInstance.post("/me", formData);

                this.success = response.data.message || "Profil berhasil diperbarui";
                // refresh user
                await this.checkAuth();
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async forgotPassword(payload) {
            this.loading = true;
            this.error = null;
            this.success = null;

            try {
                const response = await axiosInstance.post("/forgot-password", payload);
                this.success = response.data.message;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async resetPassword(payload) {
            this.loading = true;
            this.error = null;
            this.success = null;

            try {
                const response = await axiosInstance.post("/reset-password", payload);
                this.success = response.data.message;
                router.push({ name: "login" });
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async sendVerificationEmail(payload = {}) {
            this.loading = true;
            this.error = null;
            this.success = null;

            try {
                const endpoint = this.token ? "/email/verify/send" : "/email/verification-notification";
                const response = await axiosInstance.post(endpoint, payload);
                this.success = response.data.message;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },
    },
});
