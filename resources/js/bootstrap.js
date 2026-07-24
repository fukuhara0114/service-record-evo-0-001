import axios from 'axios';
import { handleUnauthorizedStatus } from './utils/auth';

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

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (handleUnauthorizedStatus(error.response?.status)) {
            return new Promise(() => {});
        }

        return Promise.reject(error);
    },
);
