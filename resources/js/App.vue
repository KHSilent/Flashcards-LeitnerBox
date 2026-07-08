<script setup>
import { computed } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import {
    ArrowRightStartOnRectangleIcon,
    ComputerDesktopIcon,
    HomeIcon,
    MoonIcon,
    SunIcon,
    UserCircleIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { auth } from './stores/auth';
import { theme } from './stores/theme';

const route = useRoute();
const router = useRouter();
const isLogin = computed(() => route.name === 'login');
const logoUrl = '/fc-logo.svg';
const themeOptions = [
    { mode: 'light', label: 'روشن', icon: SunIcon },
    { mode: 'dark', label: 'تاریک', icon: MoonIcon },
    { mode: 'system', label: 'سیستم', icon: ComputerDesktopIcon },
];
const currentThemeOption = computed(() => themeOptions.find((option) => option.mode === theme.mode) || themeOptions[0]);

function cycleTheme() {
    const index = themeOptions.findIndex((option) => option.mode === theme.mode);
    const next = themeOptions[(index + 1) % themeOptions.length];
    theme.set(next.mode);
}

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <main class="min-h-screen bg-paper-50 text-slate-950 transition-colors dark:bg-black dark:text-neutral-100">
        <header
            v-if="!isLogin"
            class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur dark:border-neutral-800 dark:bg-black/95"
        >
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <RouterLink :to="{ name: 'categories' }" class="flex items-center gap-3">
                    <img :src="logoUrl" alt="FC" class="h-10 w-10 rounded-lg shadow-sm">
                    <div>
                        <p class="text-sm font-bold text-primary-700 dark:text-primary-300">FlashCard</p>
                        <p class="text-xs text-slate-500 dark:text-neutral-400">سیستم مطالعه لایتنر</p>
                    </div>
                </RouterLink>

                <div class="flex items-center gap-2">
                    <RouterLink
                        :to="{ name: 'categories' }"
                        class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-600 hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:text-neutral-300"
                        title="دسته‌ها"
                    >
                        <HomeIcon class="h-5 w-5" />
                    </RouterLink>

                    <RouterLink
                        v-if="auth.hasRole('manageUser')"
                        :to="{ name: 'users' }"
                        class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-600 hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:text-neutral-300"
                        title="کاربران"
                    >
                        <UsersIcon class="h-5 w-5" />
                    </RouterLink>

                    <button
                        class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-600 hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:text-neutral-300"
                        type="button"
                        :title="`تم: ${currentThemeOption.label}`"
                        :aria-label="`تم: ${currentThemeOption.label}`"
                        @click="cycleTheme"
                    >
                        <component :is="currentThemeOption.icon" class="h-5 w-5" />
                    </button>

                    <RouterLink
                        :to="{ name: 'profile' }"
                        class="flex h-10 items-center gap-2 rounded-md border border-slate-200 px-2 hover:border-primary-400 dark:border-neutral-700"
                        title="پروفایل"
                    >
                        <img
                            v-if="auth.user?.avatar_url"
                            :src="auth.user.avatar_url"
                            alt=""
                            class="h-7 w-7 rounded-full bg-neutral-200"
                        >
                        <UserCircleIcon v-else class="h-6 w-6 text-slate-500 dark:text-neutral-400" />
                        <span class="hidden max-w-28 truncate text-sm font-semibold text-slate-700 dark:text-neutral-200 sm:inline">
                            {{ auth.user?.name }}
                        </span>
                    </RouterLink>

                    <button
                        class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-700 hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:text-neutral-200"
                        type="button"
                        title="خروج"
                        aria-label="خروج"
                        @click="logout"
                    >
                        <ArrowRightStartOnRectangleIcon class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </header>

        <RouterView />
    </main>
</template>
