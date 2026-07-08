<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { PencilSquareIcon, PlayIcon, PlusIcon } from '@heroicons/vue/24/outline';
import StepEditorModal from '../components/StepEditorModal.vue';

const route = useRoute();
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const modalOpen = ref(false);
const page = reactive({
    category: null,
    access: { steps: [] },
    summaries: [],
});
const introduceCount = ref(10);

const unintroduced = computed(() => page.summaries.find((summary) => summary.index === null));
const studySteps = computed(() => page.summaries.filter((summary) => summary.index !== null));

async function fetchCategory() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get(`/api/categories/${route.params.id}`);
        page.category = data.category;
        page.access = data.access;
        page.summaries = data.summaries;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'اطلاعات دسته دریافت نشد.';
    } finally {
        loading.value = false;
    }
}

async function introduce() {
    saving.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.post(`/api/categories/${route.params.id}/introduce`, { count: introduceCount.value });
        page.summaries = data.summaries;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'کارت‌ها وارد گام صفر نشدند.';
    } finally {
        saving.value = false;
    }
}

async function saveSteps(steps) {
    saving.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.put(`/api/categories/${route.params.id}/steps`, { steps });
        page.access = data.access;
        page.summaries = data.summaries;
        modalOpen.value = false;
    } catch (exception) {
        error.value = exception.response?.data?.message || Object.values(exception.response?.data?.errors || {})?.flat()?.[0] || 'گام‌ها ذخیره نشدند.';
    } finally {
        saving.value = false;
    }
}

onMounted(fetchCategory);
</script>

<template>
    <section class="mx-auto max-w-6xl px-4 py-8">
        <RouterLink :to="{ name: 'categories' }" class="mb-4 inline-flex text-sm font-semibold text-primary-700 hover:text-primary-800 dark:text-primary-300">
            بازگشت به دسته‌ها
        </RouterLink>

        <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-neutral-800 dark:bg-neutral-950">
            در حال دریافت اطلاعات...
        </div>

        <template v-else>
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p v-if="page.category?.parent_name" class="mb-1 text-sm text-slate-500 dark:text-neutral-400">{{ page.category.parent_name }}</p>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold">{{ page.category?.name }}</h1>
                        <RouterLink
                            v-if="page.access.can_edit"
                            :to="{ name: 'category-cards', params: { id: page.category.id } }"
                            class="grid h-9 w-9 place-items-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-primary-700 dark:text-neutral-400 dark:hover:bg-neutral-900 dark:hover:text-primary-300"
                            title="ویرایش کارت‌ها"
                        >
                            <PencilSquareIcon class="h-5 w-5" />
                        </RouterLink>
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400">فقط کارت‌هایی که موعدشان رسیده باشد وارد مطالعه می‌شوند.</p>
                </div>

                <button
                    class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-200 px-3 text-sm font-semibold hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700"
                    type="button"
                    @click="modalOpen = true"
                >
                    <PencilSquareIcon class="h-5 w-5" />
                    ویرایش گام‌ها
                </button>
            </div>

            <p v-if="error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                {{ error }}
            </p>

            <section class="mb-5 rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-neutral-800 dark:bg-neutral-950">
                <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                    <div>
                        <h2 class="text-base font-bold text-primary-900 dark:text-neutral-100">کارت‌های بدون گام</h2>
                        <p class="mt-1 text-sm text-primary-800/80 dark:text-neutral-400">
                            {{ unintroduced?.total_count || 0 }} کارت هنوز وارد برنامه مطالعه نشده است.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            v-model.number="introduceCount"
                            class="h-10 w-24 rounded-md border border-primary-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black"
                            dir="ltr"
                            type="number"
                            min="1"
                            max="100"
                        >
                        <button
                            class="inline-flex h-10 items-center gap-2 rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                            type="button"
                            :disabled="saving || !unintroduced?.total_count"
                            @click="introduce"
                        >
                            <PlusIcon class="h-5 w-5" />
                            انتقال به گام ۰
                        </button>
                    </div>
                </div>
            </section>

            <div class="space-y-3">
                <article
                    v-for="summary in studySteps"
                    :key="summary.index"
                    class="grid gap-4 rounded-lg border border-slate-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950 md:grid-cols-[1fr_auto] md:items-center"
                >
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold">{{ summary.label }}</h2>
                            <span v-if="summary.is_final" class="rounded-md bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">آخرین گام</span>
                            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600 dark:bg-neutral-900 dark:text-neutral-300">
                                {{ summary.delay_days === 0 ? 'بدون محدودیت روز' : `${summary.delay_days} روز فاصله` }}
                            </span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-sm">
                            <div class="rounded-md bg-slate-50 p-3 dark:bg-black">
                                <p class="text-slate-500 dark:text-neutral-400">کل کارت‌ها</p>
                                <p class="mt-1 text-lg font-bold">{{ summary.total_count }}</p>
                            </div>
                            <div class="rounded-md bg-emerald-50 p-3 dark:bg-emerald-950/30">
                                <p class="text-emerald-700 dark:text-emerald-200">آماده مطالعه</p>
                                <p class="mt-1 text-lg font-bold">{{ summary.due_count }}</p>
                            </div>
                            <div class="rounded-md bg-amber-50 p-3 dark:bg-amber-950/30">
                                <p class="text-amber-700 dark:text-amber-200">در انتظار موعد</p>
                                <p class="mt-1 text-lg font-bold">{{ summary.locked_count }}</p>
                            </div>
                        </div>
                    </div>

                    <RouterLink
                        v-if="summary.due_count > 0"
                        :to="{ name: 'study', params: { id: page.category.id, step: summary.index } }"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700"
                    >
                        <PlayIcon class="h-5 w-5" />
                        شروع مطالعه
                    </RouterLink>
                    <button
                        v-else
                        class="h-11 rounded-md border border-slate-200 px-4 text-sm font-semibold text-slate-400 dark:border-neutral-700"
                        type="button"
                        disabled
                    >
                        کارتی برای امروز نیست
                    </button>
                </article>
            </div>
        </template>

        <StepEditorModal
            :open="modalOpen"
            :steps="page.access.steps"
            :summaries="page.summaries"
            @close="modalOpen = false"
            @save="saveSteps"
        />
    </section>
</template>
