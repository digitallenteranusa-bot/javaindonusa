import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Handle session expired (401) and CSRF token mismatch (419)
window.axios.interceptors.response.use(
    response => response,
    error => {
        const status = error.response?.status;

        if (status === 419) {
            // CSRF token expired - reload page to get fresh token
            window.location.reload();
            return new Promise(() => {});
        }

        if (status === 401) {
            // Session expired - redirect to login
            const currentPath = window.location.pathname;
            if (currentPath.startsWith('/portal')) {
                window.location.href = '/portal/login';
            } else {
                window.location.href = '/login';
            }
            return new Promise(() => {});
        }

        return Promise.reject(error);
    }
);
