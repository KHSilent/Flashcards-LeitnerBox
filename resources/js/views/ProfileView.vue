<script setup>
import { computed, reactive, ref, watchEffect } from 'vue';
import {
    CheckCircleIcon,
    ComputerDesktopIcon,
    KeyIcon,
    MoonIcon,
    SunIcon,
    UserIcon,
} from '@heroicons/vue/24/outline';
import { auth } from '../stores/auth';
import { theme } from '../stores/theme';
import { apiError, t } from '../i18n';

const activeTab = ref('general');
const saving = ref(false);
const message = ref('');
const error = ref('');

const generalForm = reactive({
    name: '',
    email: '',
});

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const themeOptions = [
    { mode: 'light', labelKey: 'common.light', icon: SunIcon },
    { mode: 'dark', labelKey: 'common.dark', icon: MoonIcon },
    { mode: 'system', labelKey: 'common.system', icon: ComputerDesktopIcon },
];
const currentThemeOption = computed(() => themeOptions.find((option) => option.mode === theme.mode) || themeOptions[0]);

function cycleTheme() {
    const index = themeOptions.findIndex((option) => option.mode === theme.mode);
    const next = themeOptions[(index + 1) % themeOptions.length];
    theme.set(next.mode);
}

watchEffect(() => {
    if (auth.user) {
        generalForm.name = auth.user.name;
        generalForm.email = auth.user.email;
    }
});

async function saveGeneral() {
    saving.value = true;
    message.value = '';
    error.value = '';

    try {
        const { data } = await window.axios.put('/api/profile', generalForm);
        auth.user = data.user;
        message.value = t('profile.saved');
    } catch (exception) {
        error.value = apiError(exception, 'profile.saveFailed');
    } finally {
        saving.value = false;
    }
}

async function savePassword() {
    saving.value = true;
    message.value = '';
    error.value = '';

    try {
        await window.axios.put('/api/profile/password', passwordForm);
        passwordForm.current_password = '';
        passwordForm.password = '';
        passwordForm.password_confirmation = '';
        message.value = t('profile.passwordChanged');
    } catch (exception) {
        error.value = apiError(exception, 'profile.passwordFailed');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <section class="mx-auto max-w-4xl px-4 py-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-4">
                <img :src="auth.user?.avatar_url" alt="" class="h-16 w-16 rounded-full bg-neutral-200 ring-1 ring-slate-200 dark:ring-neutral-800">
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold">{{ t('profile.title') }}</h1>
                    <p class="mt-1 truncate text-sm text-slate-500 dark:text-neutral-400">{{ auth.user?.email }}</p>
                </div>
            </div>
            <button
                class="grid h-10 w-10 shrink-0 place-items-center rounded-md border border-slate-200 text-slate-600 hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:text-neutral-300"
                type="button"
                :title="t('common.theme', { theme: t(currentThemeOption.labelKey) })"
                :aria-label="t('common.theme', { theme: t(currentThemeOption.labelKey) })"
                @click="cycleTheme"
            >
                <component :is="currentThemeOption.icon" class="h-5 w-5" />
            </button>
        </div>

        <div class="mb-5 flex gap-2 border-b border-slate-200 dark:border-neutral-800">
            <button
                class="inline-flex h-11 items-center gap-2 border-b-2 px-3 text-sm font-bold"
                :class="activeTab === 'general' ? 'border-primary-600 text-primary-700 dark:text-primary-300' : 'border-transparent text-slate-500 dark:text-neutral-400'"
                type="button"
                @click="activeTab = 'general'"
            >
                <UserIcon class="h-5 w-5" />
                {{ t('profile.general') }}
            </button>
            <button
                class="inline-flex h-11 items-center gap-2 border-b-2 px-3 text-sm font-bold"
                :class="activeTab === 'security' ? 'border-primary-600 text-primary-700 dark:text-primary-300' : 'border-transparent text-slate-500 dark:text-neutral-400'"
                type="button"
                @click="activeTab = 'security'"
            >
                <KeyIcon class="h-5 w-5" />
                {{ t('profile.security') }}
            </button>
        </div>

        <p v-if="message" class="mb-4 flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            <CheckCircleIcon class="h-5 w-5" />
            {{ message }}
        </p>
        <p v-if="error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
            {{ error }}
        </p>

        <form
            v-if="activeTab === 'general'"
            class="rounded-lg border border-slate-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-950"
            @submit.prevent="saveGeneral"
        >
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('common.name') }}</span>
                    <input v-model="generalForm.name" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" required>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('common.email') }}</span>
                    <input v-model="generalForm.email" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" type="email" required>
                </label>
            </div>
            <button class="mt-5 h-11 rounded-md bg-primary-600 px-5 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50" type="submit" :disabled="saving">
                {{ t('profile.save') }}
            </button>
        </form>

        <form
            v-else
            class="rounded-lg border border-slate-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-950"
            @submit.prevent="savePassword"
        >
            <div class="grid gap-4">
                <label class="block">
                    <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('profile.currentPassword') }}</span>
                    <input v-model="passwordForm.current_password" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" type="password" required>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('profile.newPassword') }}</span>
                    <input v-model="passwordForm.password" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" type="password" minlength="8" required>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('profile.confirmPassword') }}</span>
                    <input v-model="passwordForm.password_confirmation" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" type="password" minlength="8" required>
                </label>
            </div>
            <button class="mt-5 h-11 rounded-md bg-primary-600 px-5 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50" type="submit" :disabled="saving">
                {{ t('profile.changePassword') }}
            </button>
        </form>
    </section>
</template>
