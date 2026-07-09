<script setup>
import { computed, ref, watch } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import {
    ArrowRightStartOnRectangleIcon,
    Bars3Icon,
    ComputerDesktopIcon,
    HomeIcon,
    MoonIcon,
    SunIcon,
    UserCircleIcon,
    UsersIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { auth } from './stores/auth';
import { theme } from './stores/theme';

const route = useRoute();
const router = useRouter();
const isLogin = computed(() => route.name === 'login');
const mobileMenuOpen = ref(false);
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

function closeMobileMenu() {
    mobileMenuOpen.value = false;
}

async function logout() {
    await auth.logout();
    closeMobileMenu();
    router.push({ name: 'login' });
}

watch(() => route.fullPath, closeMobileMenu);
</script>

<template>
    <main class="min-h-screen bg-paper-50 text-slate-950 transition-colors dark:bg-black dark:text-neutral-100">
        <header
            v-if="!isLogin"
            class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur dark:border-neutral-800 dark:bg-black/95"
        >
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <div class="flex items-center gap-2 md:hidden">
                    <button
                        class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-700 hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:text-neutral-200"
                        type="button"
                        title="منو"
                        aria-label="منو"
                        @click="mobileMenuOpen = true"
                    >
                        <Bars3Icon class="h-6 w-6" />
                    </button>

                    <RouterLink :to="{ name: 'categories' }" class="grid h-10 w-10 place-items-center">
                        <img :src="logoUrl" alt="FC" class="h-10 w-10 rounded-lg shadow-sm">
                    </RouterLink>
                </div>

                <RouterLink :to="{ name: 'categories' }" class="hidden items-center gap-3 md:flex">
                    <img :src="logoUrl" alt="FC" class="h-10 w-10 rounded-lg shadow-sm">
                    <div>
                        <p class="text-sm font-bold text-primary-700 dark:text-primary-300">FlashCard</p>
                        <p class="text-xs text-slate-500 dark:text-neutral-400">سیستم مطالعه لایتنر</p>
                    </div>
                </RouterLink>

                <div class="hidden items-center gap-2 md:flex">
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

                <RouterLink
                    :to="{ name: 'profile' }"
                    class="grid h-10 w-10 place-items-center rounded-full border border-slate-200 bg-white dark:border-neutral-700 dark:bg-black md:hidden"
                    title="پروفایل"
                >
                    <img
                        v-if="auth.user?.avatar_url"
                        :src="auth.user.avatar_url"
                        alt=""
                        class="h-8 w-8 rounded-full bg-neutral-200"
                    >
                    <UserCircleIcon v-else class="h-6 w-6 text-slate-500 dark:text-neutral-400" />
                </RouterLink>
            </div>
        </header>

        <template v-if="!isLogin">
            <div
                v-if="mobileMenuOpen"
                class="fixed inset-0 z-50 bg-black/35 backdrop-blur-sm md:hidden"
                @click.self="closeMobileMenu"
            >
                <aside class="fixed right-0 top-0 flex h-full w-72 max-w-[85vw] flex-col border-l border-slate-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-black">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-4 dark:border-neutral-800">
                        <RouterLink :to="{ name: 'categories' }" class="flex items-center gap-3">
                            <img :src="logoUrl" alt="FC" class="h-10 w-10 rounded-lg shadow-sm">
                            <div>
                                <p class="text-sm font-bold text-primary-700 dark:text-primary-300">FlashCard</p>
                                <p class="text-xs text-slate-500 dark:text-neutral-400">سیستم مطالعه لایتنر</p>
                            </div>
                        </RouterLink>
                        <button
                            class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-600 dark:border-neutral-700 dark:text-neutral-300"
                            type="button"
                            aria-label="بستن"
                            @click="closeMobileMenu"
                        >
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </div>

                    <nav class="flex-1 space-y-2 px-3 py-4">
                        <RouterLink
                            :to="{ name: 'categories' }"
                            class="flex h-11 items-center gap-3 rounded-md px-3 text-sm font-bold text-slate-700 hover:bg-primary-50 hover:text-primary-700 dark:text-neutral-200 dark:hover:bg-neutral-900 dark:hover:text-primary-300"
                        >
                            <HomeIcon class="h-5 w-5" />
                            دسته‌ها
                        </RouterLink>
                        <RouterLink
                            v-if="auth.hasRole('manageUser')"
                            :to="{ name: 'users' }"
                            class="flex h-11 items-center gap-3 rounded-md px-3 text-sm font-bold text-slate-700 hover:bg-primary-50 hover:text-primary-700 dark:text-neutral-200 dark:hover:bg-neutral-900 dark:hover:text-primary-300"
                        >
                            <UsersIcon class="h-5 w-5" />
                            کاربران
                        </RouterLink>
                        <RouterLink
                            :to="{ name: 'profile' }"
                            class="flex h-11 items-center gap-3 rounded-md px-3 text-sm font-bold text-slate-700 hover:bg-primary-50 hover:text-primary-700 dark:text-neutral-200 dark:hover:bg-neutral-900 dark:hover:text-primary-300"
                        >
                            <UserCircleIcon class="h-5 w-5" />
                            پروفایل
                        </RouterLink>
                    </nav>

                    <div class="border-t border-slate-200 p-3 dark:border-neutral-800">
                        <div class="mb-3 flex items-center gap-3 rounded-md bg-slate-50 px-3 py-2 dark:bg-neutral-950">
                            <img
                                v-if="auth.user?.avatar_url"
                                :src="auth.user.avatar_url"
                                alt=""
                                class="h-9 w-9 rounded-full bg-neutral-200"
                            >
                            <UserCircleIcon v-else class="h-7 w-7 text-slate-500 dark:text-neutral-400" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-800 dark:text-neutral-100">{{ auth.user?.name }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-neutral-400">{{ auth.user?.email }}</p>
                            </div>
                        </div>
                        <button
                            class="flex h-11 w-full items-center gap-3 rounded-md px-3 text-sm font-bold text-rose-600 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/40"
                            type="button"
                            @click="logout"
                        >
                            <ArrowRightStartOnRectangleIcon class="h-5 w-5" />
                            خروج
                        </button>
                    </div>
                </aside>
            </div>
        </template>

        <RouterView />
    </main>
</template>
