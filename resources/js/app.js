import './bootstrap';
import { createApp } from 'vue';
import { router } from './router';
import App from './App.vue';
import { t } from './i18n';

const app = createApp(App);
app.config.globalProperties.$t = t;
app.use(router).mount('#app');
