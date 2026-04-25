import axios from 'axios'
import Cookies from 'js-cookie'

const token = Cookies.get('token')

axios.defaults.baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1'
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.headers.common['Accept'] = 'application/json'
if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
}

axios.interceptors.request.use(
    config => {
        const token = Cookies.get('token')
        if (token) {
            config.headers.Authorization = `Bearer ${token}`
        }

        return config
    },
)

axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response && error.response.status === 401) {
            Cookies.remove('token')
            delete axios.defaults.headers.common['Authorization']
            if (window.location.pathname !== '/auth/login') {
                window.location.href = '/auth/login'
            }
        }
        return Promise.reject(error)
    }
)

export const axiosInstance = axios
