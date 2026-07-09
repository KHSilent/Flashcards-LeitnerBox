<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowRightIcon, CheckCircleIcon, KeyIcon, ShieldCheckIcon, UserIcon } from '@heroicons/vue/24/outline';
import { auth } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const savingUser = ref(false);
const savingAccesses = ref(false);
const error = ref('');
const message = ref('');
const roles = ref([]);
const categories = ref([]);
const user = ref(null);

const form = reactive({
    name: '',
    email: '',
    password: '',
    is_active: true,
    roles: [],
});

const hasGlobalCategoryAccess = computed(() => form.roles.includes('accessAllCategories'));

const categoryRows = computed(() => {
    const byId = new Map(categories.value.map((category) => [category.id, { ...category, children: [] }]));
    const roots = [];

    byId.forEach((category) => {
        if (category.parent_id && byId.has(category.parent_id)) {
            byId.get(category.parent_id).children.push(category);
        } else {
            roots.push(category);
        }
    });

    const rows = [];
    const pushRows = (items, depth = 0) => {
        items.forEach((item) => {
            rows.push({ ...item, depth });
            pushRows(item.children, depth + 1);
        });
    };

    pushRows(roots);

    return rows;
});

function firstError(exception, fallback) {
    return exception.response?.data?.message || Object.values(exception.response?.data?.errors || {})?.flat()?.[0] || fallback;
}

function categoryState(category) {
    return categories.value.find((item) => item.id === category.id) || category;
}

function fillForm(payload) {
    user.value = payload.user;
    roles.value = payload.roles;
    categories.value = payload.categories;
    form.name = payload.user.name;
    form.email = payload.user.email;
    form.password = '';
    form.is_active = payload.user.is_active;
    form.roles = [...(payload.user.roles || [])];
}

async function fetchUser() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get(`/api/users/${route.params.id}`);
        fillForm(data);
    } catch (exception) {
        error.value = firstError(exception, 'دریافت اطلاعات کاربر انجام نشد.');
    } finally {
        loading.value = false;
    }
}

async function saveUser() {
    savingUser.value = true;
    error.value = '';
    message.value = '';

    const payload = {
        name: form.name,
        email: form.email,
        is_active: form.is_active,
        roles: form.roles,
    };

    if (form.password) {
        payload.password = form.password;
    }

    try {
        const { data } = await window.axios.put(`/api/users/${route.params.id}`, payload);
        if (auth.user?.id === data.user.id) {
            auth.user = data.user;
        }
        user.value = data.user;
        form.password = '';
        message.value = 'اطلاعات کاربر ذخیره شد.';
    } catch (exception) {
        error.value = firstError(exception, 'ذخیره کاربر انجام نشد.');
    } finally {
        savingUser.value = false;
    }
}

function setHasAccess(category, value) {
    const target = categoryState(category);
    target.has_access = value;

    if (!value) {
        target.can_edit = false;
    }
}

function setCanEdit(category, value) {
    const target = categoryState(category);
    target.can_edit = value;

    if (value) {
        target.has_access = true;
    }
}

async function saveAccesses() {
    savingAccesses.value = true;
    error.value = '';
    message.value = '';

    try {
        const { data } = await window.axios.put(`/api/users/${route.params.id}/category-accesses`, {
            accesses: categories.value.map((category) => ({
                category_id: category.id,
                has_access: category.has_access,
                can_edit: category.can_edit,
            })),
        });
        categories.value = data.categories;
        message.value = 'دسترسی دسته‌ها ذخیره شد.';
    } catch (exception) {
        error.value = firstError(exception, 'ذخیره دسترسی دسته‌ها انجام نشد.');
    } finally {
        savingAccesses.value = false;
    }
}

onMounted(fetchUser);
</script>

<template>
    <section class="mx-auto max-w-6xl px-4 py-8">
        <button
            class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-primary-700 hover:text-primary-800 dark:text-primary-300"
            type="button"
            @click="router.push({ name: 'users' })"
        >
            <ArrowRightIcon class="h-4 w-4" />
            بازگشت به کاربران
        </button>

        <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-neutral-800 dark:bg-neutral-950">
            در حال دریافت اطلاعات کاربر...
        </div>

        <template v-else>
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <img :src="user?.avatar_url" alt="" class="h-14 w-14 rounded-full bg-neutral-200">
                    <div class="min-w-0">
                        <h1 class="truncate text-2xl font-bold">ویرایش کاربر</h1>
                        <p class="mt-1 truncate text-sm text-slate-500 dark:text-neutral-400">{{ user?.email }}</p>
                    </div>
                </div>
            </div>

            <p v-if="message" class="mb-4 flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                <CheckCircleIcon class="h-5 w-5" />
                {{ message }}
            </p>
            <p v-if="error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                {{ error }}
            </p>

            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)]">
                <form class="rounded-lg border border-slate-200 bg-white dark:border-neutral-800 dark:bg-neutral-950" @submit.prevent="saveUser">
                    <header class="flex items-center gap-2 border-b border-slate-200 px-5 py-4 dark:border-neutral-800">
                        <UserIcon class="h-5 w-5 text-primary-600" />
                        <h2 class="font-bold">اطلاعات کاربر</h2>
                    </header>

                    <div class="space-y-4 p-5">
                        <label class="block">
                            <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">نام</span>
                            <input v-model="form.name" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" required>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">ایمیل</span>
                            <input v-model="form.email" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" type="email" required>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">رمز جدید</span>
                            <input v-model="form.password" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" type="password" minlength="8">
                        </label>

                        <label class="flex items-center gap-2 text-sm font-semibold">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600">
                            فعال باشد
                        </label>

                        <div>
                            <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-neutral-200">
                                <KeyIcon class="h-4 w-4" />
                                نقش‌ها
                            </p>
                            <label v-for="role in roles" :key="role" class="mb-2 flex items-center gap-2 text-sm">
                                <input v-model="form.roles" :value="role" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600">
                                {{ role }}
                            </label>
                        </div>
                    </div>

                    <footer class="flex justify-end border-t border-slate-200 px-5 py-4 dark:border-neutral-800">
                        <button class="h-10 rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50" type="submit" :disabled="savingUser">
                            ذخیره کاربر
                        </button>
                    </footer>
                </form>

                <section class="rounded-lg border border-slate-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
                    <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-neutral-800">
                        <div class="flex items-center gap-2">
                            <ShieldCheckIcon class="h-5 w-5 text-primary-600" />
                            <h2 class="font-bold">دسترسی دسته‌ها</h2>
                        </div>
                        <button class="h-10 rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50" type="button" :disabled="savingAccesses" @click="saveAccesses">
                            ذخیره دسترسی
                        </button>
                    </header>

                    <div v-if="hasGlobalCategoryAccess" class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm font-semibold text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                        این کاربر نقش accessAllCategories دارد؛ برای گرفتن دسترسی کامل به دسته‌ها، این نقش را از بخش نقش‌ها بردارید.
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-neutral-800">
                        <article v-for="category in categoryRows" :key="category.id" class="grid min-h-14 grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-3 px-4 py-3">
                            <div class="min-w-0" :style="{ paddingRight: `${category.depth * 20}px` }">
                                <p class="truncate text-sm font-bold text-slate-800 dark:text-neutral-100">{{ category.name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-neutral-400">{{ category.flashcards_count }} کارت</p>
                            </div>
                            <label class="flex items-center gap-2 whitespace-nowrap text-sm font-semibold text-slate-600 dark:text-neutral-300">
                                <input :checked="category.has_access" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" @change="setHasAccess(category, $event.target.checked)">
                                دسترسی
                            </label>
                            <label class="flex items-center gap-2 whitespace-nowrap text-sm font-semibold text-slate-600 dark:text-neutral-300">
                                <input :checked="category.can_edit" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" @change="setCanEdit(category, $event.target.checked)">
                                ویرایش
                            </label>
                        </article>

                        <div v-if="!categoryRows.length" class="p-5 text-center text-sm text-slate-500 dark:text-neutral-400">
                            دسته‌ای وجود ندارد.
                        </div>
                    </div>
                </section>
            </div>
        </template>
    </section>
</template>
