<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { ArrowPathIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import CategoryTreeNode from '../components/CategoryTreeNode.vue';
import { apiError, locale, t } from '../i18n';

const loading = ref(true);
const saving = ref(false);
const modalOpen = ref(false);
const categories = ref([]);
const error = ref('');
const message = ref('');
const form = reactive({
    name: '',
    parent_id: '',
});

const tree = computed(() => {
    const byId = new Map(categories.value.map((category) => [category.id, { ...category, children: [] }]));
    const roots = [];

    byId.forEach((category) => {
        if (category.parent_id && byId.has(category.parent_id)) {
            byId.get(category.parent_id).children.push(category);
        } else {
            roots.push(category);
        }
    });

    function visibleNodes(category) {
        const children = category.children.flatMap((child) => visibleNodes(child));

        if (!category.access) {
            return children;
        }

        return [{ ...category, children }];
    }

    return roots.flatMap((category) => visibleNodes(category));
});

const parentOptions = computed(() => {
    const byId = new Map(categories.value.map((category) => [category.id, category]));

    function pathFor(category) {
        const names = [category.name];
        let parent = byId.get(category.parent_id);

        while (parent) {
            names.unshift(parent.name);
            parent = byId.get(parent.parent_id);
        }

        return names.join(' / ');
    }

    return categories.value
        .filter((category) => category.access?.can_edit)
        .map((category) => ({
            id: category.id,
            path: pathFor(category),
        }))
        .sort((a, b) => a.path.localeCompare(b.path, locale.value));
});

function openCreateModal() {
    form.name = '';
    form.parent_id = '';
    error.value = '';
    message.value = '';
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    form.name = '';
    form.parent_id = '';
}

async function fetchCategories() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get('/api/categories');
        categories.value = data.categories;
    } catch (exception) {
        error.value = apiError(exception, 'categories.fetchFailed');
    } finally {
        loading.value = false;
    }
}

async function saveCategory() {
    saving.value = true;
    error.value = '';
    message.value = '';

    try {
        await window.axios.post('/api/categories', {
            name: form.name,
            parent_id: form.parent_id || null,
        });
        message.value = t('categories.saved');
        closeModal();
        await fetchCategories();
    } catch (exception) {
        error.value = apiError(exception, 'categories.saveFailed');
    } finally {
        saving.value = false;
    }
}

onMounted(fetchCategories);
</script>

<template>
    <section class="mx-auto max-w-6xl px-4 py-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">{{ t('categories.title') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <button
                    class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-600 hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:text-neutral-300"
                    type="button"
                    :title="t('categories.add')"
                    :aria-label="t('categories.add')"
                    @click="openCreateModal"
                >
                    <PlusIcon class="h-5 w-5" />
                </button>
                <button
                    class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-600 hover:border-primary-400 hover:text-primary-700 disabled:opacity-50 dark:border-neutral-700 dark:text-neutral-300"
                    type="button"
                    :title="t('common.refresh')"
                    :aria-label="t('common.refresh')"
                    :disabled="loading"
                    @click="fetchCategories"
                >
                    <ArrowPathIcon class="h-5 w-5" :class="loading ? 'animate-spin' : ''" />
                </button>
            </div>
        </div>

        <p v-if="message" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ message }}
        </p>

        <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-neutral-800 dark:bg-neutral-950">
            {{ t('categories.loading') }}
        </div>

        <div v-else-if="error" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
            {{ error }}
        </div>

        <div v-else class="overflow-visible rounded-lg border border-slate-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
            <CategoryTreeNode v-for="category in tree" :key="category.id" :category="category" :depth="0" />
        </div>

        <div v-if="modalOpen" class="fixed inset-0 z-50 grid place-items-center bg-black/70 px-4 py-8">
            <form class="w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-xl dark:border-neutral-700 dark:bg-neutral-950" @submit.prevent="saveCategory">
                <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-neutral-800">
                    <div class="flex items-center gap-2">
                        <PlusIcon class="h-5 w-5 text-primary-600" />
                        <h2 class="text-lg font-bold">{{ t('categories.new') }}</h2>
                    </div>
                    <button class="grid h-9 w-9 place-items-center rounded-md text-slate-500 hover:bg-slate-100 dark:hover:bg-neutral-900" type="button" :aria-label="t('common.close')" @click="closeModal">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </header>

                <div class="space-y-4 p-5">
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('categories.name') }}</span>
                        <input v-model="form.name" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" required>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('categories.parent') }}</span>
                        <select v-model="form.parent_id" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black">
                            <option value="">{{ t('categories.noParent') }}</option>
                            <option v-for="option in parentOptions" :key="option.id" :value="option.id">
                                {{ option.path }}
                            </option>
                        </select>
                    </label>
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
