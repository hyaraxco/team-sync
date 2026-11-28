import axios from "axios";
import Cookies from "js-cookie";
import { getActivePinia } from "pinia";

const axiosInstance = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || "http://localhost:8000/api/v1",
    headers: {
        "X-Requested-With": "XMLHttpRequest",
        "Content-Type": "application/json",
        Accept: "application/json",
    },
});

axiosInstance.interceptors.request.use((config) => {
    const token = Cookies.get("token");
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

axiosInstance.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (error.response && error.response.status === 401) {
            const pinia = getActivePinia();
            try {
                if (pinia) {
                    const { useAuthStore } = await import("@/stores/auth");
                    useAuthStore(pinia).clearSession();
                }
            } catch {
                // A failed auth chunk must not prevent cookie cleanup or redirect.
            } finally {
                Cookies.remove("token");
            }
            if (window.location.pathname !== "/auth/login") {
                window.location.href = "/auth/login";
            }
        }
        return Promise.reject(error);
    },
);

export { axiosInstance };
