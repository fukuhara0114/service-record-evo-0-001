import axios from 'axios';
import {
    ensureSession,
    handleUnauthorizedStatus,
    installFetchAuthGuard,
    isLoginUrl,
} from './utils/auth';

const baseUrl = document.querySelector('meta[name="app-base-url"]')?.getAttribute('content');
if (baseUrl) {
    axios.defaults.baseURL = baseUrl.replace(/\/$/, '');
}

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

installFetchAuthGuard();

window.axios.interceptors.request.use(async (config) => {
    const method = (config.method || 'get').toUpperCase();
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
        if (!(await ensureSession())) {
            const error = new Error('Session expired');
            error.isSessionExpired = true;
            return Promise.reject(error);
        }
    }
    return config;
});

window.axios.interceptors.response.use(
    (response) => {
        // 認証切れで /login（非 Inertia HTML）へ着地した場合
        const finalUrl = response.request?.responseURL;
        if (typeof finalUrl === 'string' && isLoginUrl(finalUrl)) {
            handleUnauthorizedStatus(401);
            return new Promise(() => {});
        }

        return response;
    },
    (error) => {
        if (error?.isSessionExpired) {
            return new Promise(() => {});
        }

        if (handleUnauthorizedStatus(error.response?.status)) {
            return new Promise(() => {});
        }

        return Promise.reject(error);
    },
);
