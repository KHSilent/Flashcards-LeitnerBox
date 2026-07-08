<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import {
    CheckCircleIcon,
    ClockIcon,
    PencilSquareIcon,
    PlayIcon,
    PlusIcon,
    RectangleStackIcon,
} from '@heroicons/vue/24/outline';
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

function delayLabel(summary) {
    return summary.delay_days === 0 ? 'بدون محدودیت روز' : `${summary.delay_days} روز فاصله`;
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

            <section v-if="unintroduced?.total_count > 0" class="mb-6 rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-neutral-800 dark:bg-neutral-950">
                <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                    <div>
                        <h2 class="text-base font-bold text-primary-900 dark:text-neutral-100">کارت‌های بدون گام</h2>
                        <p class="mt-1 text-sm text-primary-800/80 dark:text-neutral-400">
                            {{ unintroduced?.total_count || 0 }} کارت هنوز وارد برنامه مطالعه نشده است.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            v-model.number="introduceCount"
                            class="h-10 w-24 shrink-0 rounded-md border border-primary-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black"
                            dir="ltr"
                            type="number"
                            min="1"
                            max="100"
                        >
                        <button
                            class="inline-flex h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
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

            <div class="space-y-8">
                <article
                    v-for="summary in studySteps"
                    :key="summary.index"
                    class="grid min-h-36 grid-cols-[2.75rem_minmax(0,1fr)_8rem] items-center gap-0 max-md:grid-cols-[2.5rem_minmax(0,1fr)_5rem] max-md:min-h-28"
                    dir="ltr"
                >
                    <div class="relative h-16 self-start max-md:h-14">
                        <RouterLink
                            v-if="summary.due_count > 0"
                            :to="{ name: 'study', params: { id: page.category.id, step: summary.index } }"
                            class="absolute left-0 top-2 grid h-11 w-11 place-items-center rounded-md bg-primary-600 text-white shadow-sm shadow-primary-600/20 hover:bg-primary-700 max-md:top-0 max-md:h-10 max-md:w-10"
                            title="شروع مطالعه"
                        >
                            <PlayIcon class="h-5 w-5" />
                        </RouterLink>
                        <button
                            v-else
                            class="absolute left-0 top-2 grid h-11 w-11 place-items-center rounded-md border border-slate-200 text-slate-300 dark:border-neutral-800 dark:text-neutral-600 max-md:top-0 max-md:h-10 max-md:w-10"
                            type="button"
                            disabled
                            title="کارتی برای امروز نیست"
                        >
                            <PlayIcon class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="relative h-32 min-w-0 max-md:h-24">
                        <div class="absolute right-2 top-3 max-w-64 truncate text-right text-sm font-extrabold text-slate-900 dark:text-neutral-100 max-md:top-1 max-md:max-w-36 max-md:text-xs" dir="rtl">
                            {{ delayLabel(summary) }}
                        </div>
                        <div class="absolute -left-11 right-0 top-16 h-1 -translate-y-1/2 rounded-full bg-slate-500 dark:bg-neutral-700 max-md:-left-10 max-md:top-12 max-md:h-px" />
                        <div class="absolute inset-x-0 top-20 flex justify-center gap-12 max-md:top-[3.75rem] max-md:gap-5" dir="rtl">
                            <div class="flex items-center gap-2 whitespace-nowrap text-sm font-black text-amber-700 dark:text-amber-300 max-md:gap-1 max-md:text-xs" title="در انتظار موعد">
                                <ClockIcon class="h-5 w-5" />
                                <span>{{ summary.locked_count }}</span>
                            </div>
                            <div class="flex items-center gap-2 whitespace-nowrap text-sm font-black text-emerald-700 dark:text-emerald-300 max-md:gap-1 max-md:text-xs" title="آماده مطالعه">
                                <CheckCircleIcon class="h-5 w-5" />
                                <span>{{ summary.due_count }}</span>
                            </div>
                            <div class="flex items-center gap-2 whitespace-nowrap text-sm font-black text-slate-700 dark:text-neutral-200 max-md:gap-1 max-md:text-xs" title="کل کارت‌ها">
                                <RectangleStackIcon class="h-5 w-5 text-slate-500" />
                                <span>{{ summary.total_count }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <div class="grid h-32 w-32 place-items-center rounded-full border-4 border-primary-600 bg-primary-400 text-white shadow-xl shadow-primary-500/20 max-md:h-20 max-md:w-20">
                            <span class="text-5xl font-black leading-none max-md:text-3xl">{{ summary.index }}</span>
                        </div>
                    </div>
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
