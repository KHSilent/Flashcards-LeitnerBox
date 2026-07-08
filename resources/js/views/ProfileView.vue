<script setup>
import { reactive, ref, watchEffect } from 'vue';
import { CheckCircleIcon, KeyIcon, UserIcon } from '@heroicons/vue/24/outline';
import { auth } from '../stores/auth';

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

watchEffect(() => {
    if (auth.user) {
        generalForm.name = auth.user.name;
        generalForm.email = auth.user.email;
    }
});

function firstError(exception, fallback) {
    return exception.response?.data?.message || Object.values(exception.response?.data?.errors || {})?.flat()?.[0] || fallback;
}

async function saveGeneral() {
    saving.value = true;
    message.value = '';
    error.value = '';

    try {
        const { data } = await window.axios.put('/api/profile', generalForm);
        auth.user = data.user;
        message.value = 'اطلاعات عمومی ذخیره شد.';
    } catch (exception) {
        error.value = firstError(exception, 'ذخیره اطلاعات انجام نشد.');
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
        message.value = 'رمز عبور تغییر کرد.';
    } catch (exception) {
        error.value = firstError(exception, 'تغییر رمز انجام نشد.');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <section class="mx-auto max-w-4xl px-4 py-8">
        <div class="mb-6 flex items-center gap-4">
            <img :src="auth.user?.avatar_url" alt="" class="h-16 w-16 rounded-full bg-neutral-200 ring-1 ring-slate-200 dark:ring-neutral-800">
            <div>
                <h1 class="text-2xl font-bold">پروفایل</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400">{{ auth.user?.email }}</p>
            </div>
        </div>

        <div class="mb-5 flex gap-2 border-b border-slate-200 dark:border-neutral-800">
            <button
                class="inline-flex h-11 items-center gap-2 border-b-2 px-3 text-sm font-bold"
                :class="activeTab === 'general' ? 'border-primary-600 text-primary-700 dark:text-primary-300' : 'border-transparent text-slate-500 dark:text-neutral-400'"
                type="button"
                @click="activeTab = 'general'"
            >
                <UserIcon class="h-5 w-5" />
                عمومی
            </button>
            <button
                class="inline-flex h-11 items-center gap-2 border-b-2 px-3 text-sm font-bold"
                :class="activeTab === 'security' ? 'border-primary-600 text-primary-700 dark:text-primary-300' : 'border-transparent text-slate-500 dark:text-neutral-400'"
                type="button"
                @click="activeTab = 'security'"
            >
                <KeyIcon class="h-5 w-5" />
                امنیت
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
                    <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">نام</span>
                    <input v-model="generalForm.name" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" required>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">ایمیل</span>
                    <input v-model="generalForm.email" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" type="email" required>
                </label>
            </div>
            <button class="mt-5 h-11 rounded-md bg-primary-600 px-5 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50" type="submit" :disabled="saving">
                ذخیره
            </button>
        </form>

        <form
            v-else
            class="rounded-lg border border-slate-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-950"
            @submit.prevent="savePassword"
        >
            <div class="grid gap-4">
                <label class="block">
                    <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">رمز فعلی</span>
                    <input v-model="passwordForm.current_password" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" type="password" required>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">رمز جدید</span>
                    <input v-model="passwordForm.password" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" type="password" minlength="8" required>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">تکرار رمز جدید</span>
                    <input v-model="passwordForm.password_confirmation" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" type="password" minlength="8" required>
                </label>
            </div>
            <button class="mt-5 h-11 rounded-md bg-primary-600 px-5 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50" type="submit" :disabled="saving">
                تغییر رمز
            </button>
        </form>
    </section>
</template>
