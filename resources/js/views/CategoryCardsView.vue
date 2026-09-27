<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { TrashIcon } from '@heroicons/vue/24/outline';
import CardManager from '../components/CardManager.vue';
import { apiError, t } from '../i18n';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const deleting = ref(false);
const error = ref('');
const page = reactive({
    category: null,
    access: null,
});

async function fetchCategory() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get(`/api/categories/${route.params.id}`);
        page.category = data.category;
        page.access = data.access;

        if (!data.access.can_edit) {
            router.replace({ name: 'category', params: { id: route.params.id } });
        }
    } catch (exception) {
        error.value = apiError(exception, 'categories.fetchInfoFailed');
    } finally {
        loading.value = false;
    }
}

async function deleteCategory() {
    if (!window.confirm(t('categories.deleteConfirm'))) {
        return;
    }

    deleting.value = true;
    error.value = '';

    try {
        await window.axios.delete(`/api/categories/${route.params.id}`);
        router.replace({ name: 'categories' });
    } catch (exception) {
        error.value = apiError(exception, 'categories.deleteFailed');
        await fetchCategory();
    } finally {
        deleting.value = false;
    }
}

onMounted(fetchCategory);
</script>

<template>
    <section class="mx-auto max-w-6xl px-4 py-8">
        <RouterLink :to="{ name: 'category', params: { id: route.params.id } }" class="mb-4 inline-flex text-sm font-semibold text-primary-700 hover:text-primary-800 dark:text-primary-300">
            {{ t('categories.backToSteps') }}
        </RouterLink>

        <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-neutral-800 dark:bg-neutral-950">
            {{ t('categories.loadingInfo') }}
        </div>

        <template v-else>
            <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p v-if="page.category?.parent_name" class="mb-1 text-sm text-slate-500 dark:text-neutral-400">{{ page.category.parent_name }}</p>
                    <h1 class="text-2xl font-bold">{{ t('categories.cardsTitle', { name: page.category?.name }) }}</h1>
                </div>

                <button
                    v-if="page.category?.can_delete"
                    class="inline-flex h-10 items-center gap-2 rounded-md border border-rose-200 px-3 text-sm font-bold text-rose-600 hover:bg-rose-50 disabled:opacity-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                    type="button"
                    :disabled="deleting"
                    @click="deleteCategory"
                >
                    <TrashIcon class="h-5 w-5" />
                    {{ t('common.delete') }}
                </button>
            </div>

            <p v-if="error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                {{ error }}
            </p>

            <CardManager
                v-if="page.access?.can_edit"
                :category-id="Number(route.params.id)"
                @changed="fetchCategory"
            />
        </template>
    </section>
</template>
