<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import {
    CheckCircleIcon,
    ClockIcon,
    EyeIcon,
    PencilSquareIcon,
    PlayIcon,
    PlusIcon,
    RectangleStackIcon,
} from '@heroicons/vue/24/outline';
import StepEditorModal from '../components/StepEditorModal.vue';
import { apiError, isRtl, t } from '../i18n';

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
        error.value = apiError(exception, 'categories.fetchInfoFailed');
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
        error.value = apiError(exception, 'categories.introduceFailed');
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
        error.value = apiError(exception, 'steps.saveFailed');
    } finally {
        saving.value = false;
    }
}

function delayLabel(summary) {
    return summary.delay_days === 0 ? t('steps.unlimited') : t('steps.delayDays', { count: summary.delay_days });
}

onMounted(fetchCategory);
</script>

<template>
    <section class="mx-auto max-w-6xl px-4 py-8">
        <RouterLink :to="{ name: 'categories' }" class="mb-4 inline-flex text-sm font-semibold text-primary-700 hover:text-primary-800 dark:text-primary-300">
            {{ t('categories.back') }}
        </RouterLink>

        <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-neutral-800 dark:bg-neutral-950">
            {{ t('categories.loadingInfo') }}
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
                            :title="t('categories.editCards')"
                        >
                            <PencilSquareIcon class="h-5 w-5" />
                        </RouterLink>
                    </div>
                </div>

                <button
                    class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-200 px-3 text-sm font-semibold hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700"
                    type="button"
                    @click="modalOpen = true"
                >
                    <PencilSquareIcon class="h-5 w-5" />
                    {{ t('categories.editSteps') }}
                </button>
            </div>

            <p v-if="error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                {{ error }}
            </p>

            <section v-if="unintroduced?.total_count > 0" class="mb-6 rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-neutral-800 dark:bg-neutral-950">
                <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                    <div>
                        <h2 class="text-base font-bold text-primary-900 dark:text-neutral-100">{{ t('categories.noStepCards') }}</h2>
                        <p class="mt-1 text-sm text-primary-800/80 dark:text-neutral-400">
                            {{ t('categories.unintroducedCount', { count: unintroduced?.total_count || 0 }) }}
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
                            {{ t('categories.introduce') }}
                        </button>
                    </div>
                </div>
            </section>

            <div class="space-y-8">
                <article
                    v-for="summary in studySteps"
                    :key="summary.index"
                    class="grid min-h-36 grid-cols-[6rem_minmax(0,1fr)_8rem] items-center gap-0 max-md:grid-cols-[5.5rem_minmax(0,1fr)_5rem] max-md:min-h-28"
                    dir="ltr"
                >
                    <div class="relative h-16 self-start max-md:h-14">
                        <RouterLink
                            v-if="summary.due_count > 0"
                            :to="{ name: 'study', params: { id: page.category.id, step: summary.index } }"
                            class="absolute left-0 top-2 grid h-11 w-11 place-items-center rounded-md bg-primary-600 text-white shadow-sm shadow-primary-600/20 hover:bg-primary-700 max-md:top-0 max-md:h-10 max-md:w-10"
                            :title="t('categories.startStudy')"
                        >
                            <PlayIcon class="h-5 w-5" />
                        </RouterLink>
                        <button
                            v-else
                            class="absolute left-0 top-2 grid h-11 w-11 place-items-center rounded-md border border-slate-200 text-slate-300 dark:border-neutral-800 dark:text-neutral-600 max-md:top-0 max-md:h-10 max-md:w-10"
                            type="button"
                            disabled
                            :title="t('categories.noCardsToday')"
                        >
                            <PlayIcon class="h-5 w-5" />
                        </button>
                        <RouterLink
                            v-if="summary.total_count > 0"
                            :to="{ name: 'step-cards', params: { id: page.category.id, step: summary.index } }"
                            class="absolute left-[3.25rem] top-2 grid h-11 w-11 place-items-center rounded-md border border-primary-200 bg-white text-primary-700 shadow-sm hover:border-primary-400 hover:bg-primary-50 dark:border-neutral-700 dark:bg-neutral-950 dark:text-primary-300 dark:hover:border-primary-600 max-md:left-12 max-md:top-0 max-md:h-10 max-md:w-10"
                            :title="t('categories.viewStepCards')"
                        >
                            <EyeIcon class="h-5 w-5" />
                        </RouterLink>
                        <button
                            v-else
                            class="absolute left-[3.25rem] top-2 grid h-11 w-11 place-items-center rounded-md border border-slate-200 text-slate-300 dark:border-neutral-800 dark:text-neutral-600 max-md:left-12 max-md:top-0 max-md:h-10 max-md:w-10"
                            type="button"
                            disabled
                            :title="t('categories.noCardsInStep')"
                        >
                            <EyeIcon class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="relative h-32 min-w-0 max-md:h-24">
                        <div
                            class="absolute right-2 top-3 max-w-64 truncate text-right text-sm font-extrabold text-slate-900 dark:text-neutral-100 max-md:top-1 max-md:max-w-36 max-md:text-xs"
                            :dir="isRtl ? 'rtl' : 'ltr'"
                        >
                            {{ delayLabel(summary) }}
                        </div>
                        <div class="absolute -left-24 right-0 top-16 h-1 -translate-y-1/2 rounded-full bg-slate-500 dark:bg-neutral-700 max-md:-left-20 max-md:top-12 max-md:h-px" />
                        <div class="absolute inset-x-0 top-20 flex justify-center gap-12 max-md:top-[3.75rem] max-md:gap-5" dir="rtl">
                            <div class="flex items-center gap-2 whitespace-nowrap text-sm font-black text-amber-700 dark:text-amber-300 max-md:gap-1 max-md:text-xs" :title="t('categories.waiting')">
                                <ClockIcon class="h-5 w-5" />
                                <span>{{ summary.locked_count }}</span>
                            </div>
                            <div class="flex items-center gap-2 whitespace-nowrap text-sm font-black text-emerald-700 dark:text-emerald-300 max-md:gap-1 max-md:text-xs" :title="t('categories.ready')">
                                <CheckCircleIcon class="h-5 w-5" />
                                <span>{{ summary.due_count }}</span>
                            </div>
                            <div class="flex items-center gap-2 whitespace-nowrap text-sm font-black text-slate-700 dark:text-neutral-200 max-md:gap-1 max-md:text-xs" :title="t('categories.totalCards')">
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
