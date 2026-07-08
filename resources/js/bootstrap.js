import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common.Accept = 'application/json';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

function setCsrfToken(token) {
    if (!token) {
        return;
    }

    let meta = document.querySelector('meta[name="csrf-token"]');

    if (!meta) {
        meta = document.createElement('meta');
        meta.setAttribute('name', 'csrf-token');
        document.head.appendChild(meta);
    }

    meta.setAttribute('content', token);
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

async function refreshCsrfToken() {
    const { data } = await window.axios.get('/api/csrf-token', {
        _skipCsrfRetry: true,
    });

    setCsrfToken(data.csrf_token);

    return data.csrf_token;
}

setCsrfToken(csrfToken);

window.setCsrfToken = setCsrfToken;
window.refreshCsrfToken = refreshCsrfToken;

window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const request = error.config;

        if (error.response?.status !== 419 || !request || request._csrfRetried || request._skipCsrfRetry) {
            return Promise.reject(error);
        }

        request._csrfRetried = true;
        const token = await refreshCsrfToken();
        request.headers = {
            ...request.headers,
            'X-CSRF-TOKEN': token,
        };

        return window.axios(request);
    },
);
