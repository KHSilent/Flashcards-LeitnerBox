import { reactive } from 'vue';

export const auth = reactive({
    user: null,
    loaded: false,

    async ready() {
        if (this.loaded) {
            return;
        }

        try {
            const { data } = await window.axios.get('/api/me');
            this.user = data.user;
        } catch {
            this.user = null;
        } finally {
            this.loaded = true;
        }
    },

    async login(credentials) {
        const { data } = await window.axios.post('/api/login', credentials);
        window.setCsrfToken(data.csrf_token);
        this.user = data.user;
        this.loaded = true;
    },

    async logout() {
        const { data } = await window.axios.post('/api/logout');
        window.setCsrfToken(data.csrf_token);
        this.user = null;
    },

    hasRole(role) {
        return this.user?.roles?.includes(role) || false;
    },
});
