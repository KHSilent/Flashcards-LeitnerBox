import { reactive } from 'vue';

const storedTheme = localStorage.getItem('fc-theme') || 'system';
const media = window.matchMedia('(prefers-color-scheme: dark)');

export const theme = reactive({
    mode: storedTheme,

    apply() {
        localStorage.setItem('fc-theme', this.mode);
        const shouldBeDark = this.mode === 'dark' || (this.mode === 'system' && media.matches);
        document.documentElement.classList.toggle('dark', shouldBeDark);
    },

    set(mode) {
        this.mode = mode;
        this.apply();
    },
});

media.addEventListener('change', () => theme.apply());
theme.apply();
