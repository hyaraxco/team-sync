import { handleError } from "@/helpers/errorHelper";
import { axiosInstance } from "@/plugins/axios";
import Cookies from "js-cookie";
import { defineStore } from "pinia";
import router from "@/router";

export const useAuthStore = defineStore("auth", {
    state: () => ({
        user: null,
        loading: false,
        error: null,
        success: null,
    }),
    getters: {
        token: () => Cookies.get('token'),
        isAuthenticated: (state) => !!state.user,
    },
    actions: {
        async login(credentials) {
            this.loading = true
            this.error = null

            try {
                const response = await axiosInstance.post('/login', credentials)

                const token = response.data.data.token

                Cookies.set('token', token)

                this.success = response.data.message

                router.push({ name: 'admin.dashboard' })
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async checkAuth() {
            this.loading = true;
            try {
                const response = await axiosInstance.get('/me');
                this.user = response.data.data;
                return this.user;
            } catch (error) {
                if (error.response && error.response.status === 401) {
                    Cookies.remove('token');
                    throw new Error("Unauthorized");
                }
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            this.loading = true

            try {
                await axiosInstance.post('/logout')

                Cookies.remove('token')

                this.user = null
                this.error = null

                router.push({ name: 'login' })
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async updateProfile(payload) {
            this.loading = true
            this.error = null
            this.success = null

            try {
                const formData = new FormData()

                if (payload?.name) {
                    formData.append('name', payload.name)
                }

                if (payload?.password) {
                    formData.append('password', payload.password)
                    formData.append('password_confirmation', payload.password_confirmation || '')
                }

                if (payload?.profile_photo instanceof File) {
                    formData.append('profile_photo', payload.profile_photo)
                }

                formData.append('_method', 'PUT')

                const response = await axiosInstance.post('/me', formData)

                this.success = response.data.message || 'Profil berhasil diperbarui'
                // refresh user
                await this.checkAuth()
                return response.data.data
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        }
    }
})