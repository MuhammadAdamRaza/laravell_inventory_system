import axios from 'axios';
import { useToastStore } from '../stores/toast';

const api = axios.create({
    baseURL: '',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

const TAB_TOKEN_KEY = 'inventory_tab_token';

export const setTabToken = (token) => window.sessionStorage.setItem(TAB_TOKEN_KEY, token);
export const clearTabToken = () => window.sessionStorage.removeItem(TAB_TOKEN_KEY);
export const getCsrfCookie = () => api.get('/sanctum/csrf-cookie');

api.interceptors.request.use((config) => {
    const token = window.sessionStorage.getItem(TAB_TOKEN_KEY);

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    } else {
        delete config.headers.Authorization;
    }

    return config;
});

// Response interceptor for professional enterprise error resilience
api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const toastStore = useToastStore();
        const status = error.response?.status;
        const message = error.response?.data?.message;

        if (status === 401) {
            const publicAuthPaths = ['/login', '/register', '/forgot-password', '/reset-password'];

            if (!publicAuthPaths.includes(window.location.pathname)) {
                window.location.href = '/login';
            }
        } else if (status === 403) {
            toastStore.error(
                message || 'Access Denied: You do not have permission for this action.',
            );
        } else if (status === 419) {
            // CSRF expired - retry token fetch
            await getCsrfCookie();
            toastStore.info('Session refreshed. Please retry your action.');
        } else if (status === 429) {
            toastStore.error(message || 'Too many requests. Please slow down.');
        } else if (status >= 500) {
            toastStore.error('A server error occurred. Please contact the administrator.');
        }

        return Promise.reject(error);
    },
);

export default api;
