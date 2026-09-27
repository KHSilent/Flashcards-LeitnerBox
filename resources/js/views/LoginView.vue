<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { auth } from '../stores/auth';
import { apiError, t } from '../i18n';
import LanguageSwitcher from '../components/LanguageSwitcher.vue';

const router = useRouter();
const form = reactive({
    email: '',
    password: '',
});
const loading = ref(false);
const error = ref('');
const logoUrl = '/fc-logo.svg';

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        await auth.login(form);
        router.push({ name: 'categories' });
    } catch (exception) {
        error.value = apiError(exception, 'auth.failed');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <section class="grid min-h-screen place-items-center px-4 py-10">
        <div class="w-full max-w-sm">
            <div class="mb-5 flex justify-center">
                <LanguageSwitcher />
            </div>
            <div class="mb-8 flex items-center justify-center">
                <img :src="logoUrl" alt="FC" class="h-16 w-16 rounded-2xl shadow-lg shadow-primary-500/20">
            </div>

            <form
                class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                @submit.prevent="submit"
            >
                <h1 class="mb-1 text-center text-xl font-bold">{{ t('auth.title') }}</h1>
                <p class="mb-6 text-center text-sm text-slate-500 dark:text-neutral-400">{{ t('auth.hint') }}</p>

                <label class="mb-4 block">
                    <span class="mb-1 block text-sm font-medium text-slate-700 dark:text-neutral-200">{{ t('common.email') }}</span>
                    <input
                        v-model="form.email"
                        class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black"
                        dir="ltr"
                        type="email"
                        autocomplete="email"
                        required
                    >
                </label>

                <label class="mb-5 block">
                    <span class="mb-1 block text-sm font-medium text-slate-700 dark:text-neutral-200">{{ t('common.password') }}</span>
                    <input
                        v-model="form.password"
                        class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black"
                        dir="ltr"
                        type="password"
                        autocomplete="current-password"
                        required
                    >
                </label>

                <p v-if="error" class="mb-4 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/40 dark:text-rose-200">
                    {{ error }}
                </p>

                <button
                    class="h-11 w-full rounded-md bg-primary-600 px-4 text-sm font-bold text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60"
                    type="submit"
                    :disabled="loading"
                >
                    {{ loading ? t('auth.submitting') : t('auth.submit') }}
                </button>
            </form>
        </div>
    </section>
</template>
