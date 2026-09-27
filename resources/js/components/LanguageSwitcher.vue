<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { locale, setLocale, t } from '../i18n';

function changeLocale(event) {
    setLocale(event.target.value);
}
</script>

<template>
    <label class="relative inline-flex h-10 min-w-24 items-center overflow-hidden rounded-md border border-slate-200 bg-white text-slate-600 transition hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:bg-black dark:text-neutral-300">
        <span class="sr-only">{{ t('language.label') }}</span>
        <select
            class="h-full w-full cursor-pointer appearance-none bg-transparent ps-3 pe-8 text-sm font-bold outline-none"
            :aria-label="t('language.label')"
            :value="locale"
            @change="changeLocale"
        >
            <option value="fa" lang="fa" dir="rtl">فارسی</option>
            <option value="en" lang="en" dir="ltr">English</option>
        </select>
        <ChevronDownIcon class="pointer-events-none absolute end-2 h-4 w-4" aria-hidden="true" />
    </label>
</template>
