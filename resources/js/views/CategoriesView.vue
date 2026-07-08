<script setup>
import { computed, onMounted, ref } from 'vue';
import CategoryTreeNode from '../components/CategoryTreeNode.vue';

const loading = ref(true);
const categories = ref([]);
const error = ref('');

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

    return roots;
});

async function fetchCategories() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get('/api/categories');
        categories.value = data.categories;
    } catch {
        error.value = 'دسته‌ها دریافت نشدند.';
    } finally {
        loading.value = false;
    }
}

onMounted(fetchCategories);
</script>

<template>
    <section class="mx-auto max-w-6xl px-4 py-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">دسته‌ها</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400">دسته‌ها به شکل پوشه‌ای نمایش داده می‌شوند.</p>
            </div>
            <button
                class="h-10 rounded-md border border-slate-200 px-3 text-sm font-semibold hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700"
                type="button"
                @click="fetchCategories"
            >
                تازه‌سازی
            </button>
        </div>

        <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-neutral-800 dark:bg-neutral-950">
            در حال دریافت دسته‌ها...
        </div>

        <div v-else-if="error" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
            {{ error }}
        </div>

        <div v-else class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
            <CategoryTreeNode v-for="category in tree" :key="category.id" :category="category" :depth="0" />
        </div>
    </section>
</template>
