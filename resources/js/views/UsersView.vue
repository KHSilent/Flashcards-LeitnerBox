<script setup>
import { onMounted, reactive, ref } from 'vue';
import { PencilSquareIcon, PlusIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { auth } from '../stores/auth';
import { apiError, roleLabel, t } from '../i18n';

const loading = ref(true);
const saving = ref(false);
const modalOpen = ref(false);
const users = ref([]);
const roles = ref([]);
const error = ref('');
const message = ref('');
const pagination = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: null,
    to: null,
});
const pageInput = ref(1);

const form = reactive({
    name: '',
    email: '',
    password: '',
    is_active: true,
    roles: [],
});

function resetForm() {
    form.name = '';
    form.email = '';
    form.password = '';
    form.is_active = true;
    form.roles = [];
}

function openCreateModal() {
    resetForm();
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    resetForm();
}

async function fetchUsers(page = pagination.current_page) {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get('/api/users', {
            params: {
                page,
                per_page: pagination.per_page,
            },
        });
        users.value = data.users;
        roles.value = data.roles;
        Object.assign(pagination, data.meta);
        pageInput.value = pagination.current_page;
    } catch (exception) {
        error.value = apiError(exception, 'users.fetchFailed');
    } finally {
        loading.value = false;
    }
}

function normalizedPage(value) {
    const page = Number.parseInt(value, 10);

    if (Number.isNaN(page)) {
        return pagination.current_page;
    }

    return Math.min(Math.max(page, 1), pagination.last_page || 1);
}

function goToPage() {
    fetchUsers(normalizedPage(pageInput.value));
}

async function saveUser() {
    saving.value = true;
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
        await window.axios.post('/api/users', { ...payload, password: form.password });
        message.value = t('users.saved');
        await fetchUsers(1);
        closeModal();
    } catch (exception) {
        error.value = apiError(exception, 'users.saveFailed');
    } finally {
        saving.value = false;
    }
}

async function toggleUser(user) {
    saving.value = true;
    error.value = '';
    message.value = '';

    try {
        const { data } = await window.axios.put(`/api/users/${user.id}`, {
            name: user.name,
            email: user.email,
            is_active: !user.is_active,
            roles: user.roles || [],
        });
        users.value = users.value.map((item) => (item.id === data.user.id ? data.user : item));
        message.value = t(data.user.is_active ? 'users.activated' : 'users.deactivated');
    } catch (exception) {
        error.value = apiError(exception, 'users.statusFailed');
    } finally {
        saving.value = false;
    }
}

async function deleteUser(user) {
    if (user.id === auth.user?.id) {
        error.value = t('users.cannotDeleteSelf');
        return;
    }

    saving.value = true;
    error.value = '';
    message.value = '';

    try {
        await window.axios.delete(`/api/users/${user.id}`);
        message.value = t('users.deleted');

        const nextPage = users.value.length === 1 && pagination.current_page > 1
            ? pagination.current_page - 1
            : pagination.current_page;
        await fetchUsers(nextPage);
    } catch (exception) {
        error.value = apiError(exception, 'users.deleteFailed');
    } finally {
        saving.value = false;
    }
}

onMounted(() => fetchUsers(1));
</script>

<template>
    <section class="mx-auto max-w-6xl px-4 py-8">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">{{ t('users.title') }}</h1>
            </div>
            <button
                class="inline-flex h-10 items-center gap-2 rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700"
                type="button"
                @click="openCreateModal"
            >
                <PlusIcon class="h-5 w-5" />
                {{ t('users.add') }}
            </button>
        </div>

        <p v-if="message" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ message }}
        </p>
        <p v-if="error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
            {{ error }}
        </p>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
            <div v-if="loading" class="p-5 text-sm text-slate-500 dark:text-neutral-400">{{ t('users.loading') }}</div>
            <div v-else class="divide-y divide-slate-100 dark:divide-neutral-800">
                <article v-for="user in users" :key="user.id" class="grid gap-3 p-4 md:grid-cols-[1fr_auto] md:items-center">
                    <div class="flex min-w-0 items-center gap-3">
                        <img :src="user.avatar_url" alt="" class="h-11 w-11 rounded-full bg-neutral-200">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-bold">{{ user.name }}</p>
                                <span class="rounded-md px-2 py-0.5 text-xs font-semibold" :class="user.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200' : 'bg-neutral-200 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300'">
                                    {{ t(user.is_active ? 'common.active' : 'common.inactive') }}
                                </span>
                            </div>
                            <p class="mt-1 truncate text-sm text-slate-500 dark:text-neutral-400">{{ user.email }}</p>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <span v-for="role in user.roles" :key="role" class="rounded-md bg-primary-50 px-2 py-1 text-xs font-semibold text-primary-700 dark:bg-neutral-900 dark:text-primary-300">{{ roleLabel(role) }}</span>
                                <span v-if="!user.roles.length" class="text-xs text-slate-400 dark:text-neutral-500">{{ t('common.noRole') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <RouterLink class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-600 hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:text-neutral-300" :to="{ name: 'user-edit', params: { id: user.id } }" :title="t('common.edit')">
                            <PencilSquareIcon class="h-5 w-5" />
                        </RouterLink>
                        <button class="h-10 rounded-md border border-slate-200 px-3 text-sm font-semibold text-slate-600 hover:border-primary-400 hover:text-primary-700 disabled:opacity-40 dark:border-neutral-700 dark:text-neutral-300" type="button" :disabled="user.id === auth.user?.id || saving" @click="toggleUser(user)">
                            {{ t(user.is_active ? 'common.inactive' : 'common.active') }}
                        </button>
                        <button class="grid h-10 w-10 place-items-center rounded-md border border-rose-200 text-rose-600 hover:bg-rose-50 disabled:opacity-40 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40" type="button" :title="t('common.delete')" :disabled="user.id === auth.user?.id || saving" @click="deleteUser(user)">
                            <TrashIcon class="h-5 w-5" />
                        </button>
                    </div>
                </article>

                <div v-if="!users.length" class="p-5 text-center text-sm text-slate-500 dark:text-neutral-400">
                    {{ t('users.empty') }}
                </div>
            </div>

            <footer v-if="pagination.total" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-sm dark:border-neutral-800">
                <p class="text-slate-500 dark:text-neutral-400">
                    {{ t('common.fromToTotal', { from: pagination.from, to: pagination.to, total: pagination.total }) }}
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        class="h-9 rounded-md border border-slate-200 px-3 font-semibold disabled:opacity-40 dark:border-neutral-700"
                        type="button"
                        :disabled="pagination.current_page <= 1 || loading"
                        @click="fetchUsers(pagination.current_page - 1)"
                    >
                        {{ t('common.previous') }}
                    </button>
                    <span class="min-w-20 text-center text-slate-600 dark:text-neutral-300">
                        {{ pagination.current_page }} / {{ pagination.last_page }}
                    </span>
                    <div class="flex items-center gap-2">
                        <input
                            v-model.number="pageInput"
                            class="h-9 w-20 rounded-md border border-slate-200 bg-white px-2 text-center outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black"
                            dir="ltr"
                            type="number"
                            min="1"
                            :max="pagination.last_page"
                            :disabled="loading"
                            @keyup.enter="goToPage"
                        >
                        <button
                            class="h-9 rounded-md border border-slate-200 px-3 font-semibold text-primary-700 disabled:opacity-40 dark:border-neutral-700 dark:text-primary-300"
                            type="button"
                            :disabled="loading"
                            @click="goToPage"
                        >
                            {{ t('common.go') }}
                        </button>
                    </div>
                    <button
                        class="h-9 rounded-md border border-slate-200 px-3 font-semibold disabled:opacity-40 dark:border-neutral-700"
                        type="button"
                        :disabled="pagination.current_page >= pagination.last_page || loading"
                        @click="fetchUsers(pagination.current_page + 1)"
                    >
                        {{ t('common.next') }}
                    </button>
                </div>
            </footer>
        </div>

        <div v-if="modalOpen" class="fixed inset-0 z-50 grid place-items-center bg-black/70 px-4 py-8">
            <form class="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-xl dark:border-neutral-700 dark:bg-neutral-950" @submit.prevent="saveUser">
                <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-neutral-800">
                    <div class="flex items-center gap-2">
                        <PlusIcon class="h-5 w-5 text-primary-600" />
                        <h2 class="text-lg font-bold">{{ t('users.new') }}</h2>
                    </div>
                    <button class="grid h-9 w-9 place-items-center rounded-md text-slate-500 hover:bg-slate-100 dark:hover:bg-neutral-900" type="button" :aria-label="t('common.close')" @click="closeModal">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </header>

                <div class="space-y-3 p-5">
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('common.name') }}</span>
                        <input v-model="form.name" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" required>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('common.email') }}</span>
                        <input v-model="form.email" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" type="email" required>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('common.password') }}</span>
                        <input v-model="form.password" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" type="password" required minlength="8">
                    </label>

                    <label class="flex items-center gap-2 text-sm font-semibold">
                        <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600">
                        {{ t('users.activeLabel') }}
                    </label>

                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('common.roles') }}</p>
                        <label v-for="role in roles" :key="role" class="mb-2 flex items-center gap-2 text-sm">
                            <input v-model="form.roles" :value="role" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600">
                            {{ roleLabel(role) }}
                        </label>
                    </div>

                </div>

                <footer class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-neutral-800">
                    <button class="h-10 rounded-md border border-slate-200 px-4 text-sm font-semibold dark:border-neutral-700" type="button" @click="closeModal">
                        {{ t('common.cancel') }}
                    </button>
                    <button class="h-10 rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50" type="submit" :disabled="saving">
                        {{ t('common.save') }}
                    </button>
                </footer>
            </form>
        </div>
    </section>
</template>
