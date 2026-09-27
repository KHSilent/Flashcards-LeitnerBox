<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import {
    ArrowPathIcon,
    ArrowsRightLeftIcon,
    CalendarDaysIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    PhotoIcon,
    SpeakerWaveIcon,
} from '@heroicons/vue/24/outline';
import AutoDirContent from '../components/AutoDirContent.vue';
import { apiError, locale, t } from '../i18n';

const route = useRoute();
const loading = ref(true);
const submitting = ref(false);
const error = ref('');
const category = ref(null);
const cards = ref([]);
const currentIndex = ref(0);
const currentSideIndex = ref(0);
const steps = ref([]);
const customOpen = ref(false);
const customStep = ref(0);

const currentCard = computed(() => cards.value[currentIndex.value] || null);
const currentSides = computed(() => currentCard.value?.flashcard.sides || []);
const currentSide = computed(() => currentSides.value[currentSideIndex.value] || null);
const progressText = computed(() => t('common.pageProgress', { current: cards.value.length ? currentIndex.value + 1 : 0, total: cards.value.length }));
const sideProgressText = computed(() => currentSides.value.length ? `${currentSideIndex.value + 1} / ${currentSides.value.length}` : '0 / 0');
const maxStep = computed(() => steps.value.length);
const stepNumber = computed(() => Number(route.params.step));

async function fetchCards() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get(`/api/categories/${route.params.id}/study-cards`, {
            params: { step: route.params.step },
        });
        category.value = data.category;
        cards.value = data.cards;
        steps.value = data.access.steps;
        customStep.value = stepNumber.value;
        currentIndex.value = 0;
        currentSideIndex.value = 0;
    } catch (exception) {
        error.value = apiError(exception, 'study.stepCardsFailed');
    } finally {
        loading.value = false;
    }
}

function formatDate(value) {
    if (!value) {
        return t('study.noDueDate');
    }

    return new Intl.DateTimeFormat(locale.value === 'fa' ? 'fa-IR' : 'en-US', {
        year: 'numeric',
        month: 'long',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(new Date(value));
}

function dueState(value) {
    if (!value) {
        return 'text-slate-500 dark:text-neutral-400';
    }

    return new Date(value) <= new Date()
        ? 'text-emerald-700 dark:text-emerald-300'
        : 'text-amber-700 dark:text-amber-300';
}

function goToSide(index) {
    if (!currentSides.value.length) {
        return;
    }

    currentSideIndex.value = Math.min(Math.max(index, 0), currentSides.value.length - 1);
}

function nextSide() {
    if (!currentSides.value.length) {
        return;
    }

    currentSideIndex.value = (currentSideIndex.value + 1) % currentSides.value.length;
}

function previousSide() {
    if (!currentSides.value.length) {
        return;
    }

    currentSideIndex.value = (currentSideIndex.value - 1 + currentSides.value.length) % currentSides.value.length;
}

function nextCard() {
    if (!cards.value.length) {
        return;
    }

    currentIndex.value = (currentIndex.value + 1) % cards.value.length;
    currentSideIndex.value = 0;
}

function previousCard() {
    if (!cards.value.length) {
        return;
    }

    currentIndex.value = (currentIndex.value - 1 + cards.value.length) % cards.value.length;
    currentSideIndex.value = 0;
}

async function moveToCustomStep() {
    if (!currentCard.value) {
        return;
    }

    submitting.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.post(`/api/categories/${route.params.id}/study-cards/${currentCard.value.study_card_id}/answer`, {
            action: 'custom',
            target_step: customStep.value,
        });

        customOpen.value = false;

        if (Number(data.card.step_index) === stepNumber.value) {
            cards.value.splice(currentIndex.value, 1, data.card);
            return;
        }

        cards.value.splice(currentIndex.value, 1);
        if (currentIndex.value >= cards.value.length) {
            currentIndex.value = Math.max(cards.value.length - 1, 0);
        }
        currentSideIndex.value = 0;
    } catch (exception) {
        error.value = apiError(exception, 'study.moveFailed');
    } finally {
        submitting.value = false;
    }
}

onMounted(fetchCards);
</script>

<template>
    <section class="mx-auto max-w-4xl px-4 py-8">
        <RouterLink :to="{ name: 'category', params: { id: route.params.id } }" class="mb-4 inline-flex text-sm font-semibold text-primary-700 hover:text-primary-800 dark:text-primary-300">
            {{ t('study.back') }}
        </RouterLink>

        <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-neutral-800 dark:bg-neutral-950">
            {{ t('study.stepCardsLoading') }}
        </div>

        <p v-else-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
            {{ error }}
        </p>

        <div v-else-if="!cards.length" class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-neutral-800 dark:bg-neutral-950">
            {{ t('study.emptyStep') }}
        </div>

        <article v-else-if="currentCard" class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-neutral-800">
                <div>
                    <p class="text-sm text-slate-500 dark:text-neutral-400">{{ category?.name }} / {{ t('common.step', { number: stepNumber }) }}</p>
                    <h1 class="mt-1 text-xl font-bold">{{ currentCard.flashcard.title || t('app.name') }}</h1>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-md bg-slate-100 px-3 py-2 text-sm font-semibold dark:bg-neutral-900">
                        <CalendarDaysIcon class="h-5 w-5" :class="dueState(currentCard.due_at)" />
                        <span :class="dueState(currentCard.due_at)">{{ formatDate(currentCard.due_at) }}</span>
                    </span>
                    <span class="rounded-md bg-primary-100 px-3 py-2 text-sm font-semibold text-primary-700 dark:bg-neutral-900 dark:text-primary-300">{{ progressText }}</span>
                </div>
            </header>

            <div class="space-y-4 p-5">
                <section v-if="currentSide" class="min-h-72 overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-neutral-700 dark:bg-black">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-neutral-800">
                        <button
                            class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-600 hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:text-neutral-300"
                            type="button"
                            :aria-label="t('study.previousSide')"
                            @click="previousSide"
                        >
                            <ChevronRightIcon class="h-5 w-5" />
                        </button>

                        <div class="text-center">
                            <p class="text-xs font-semibold text-slate-500 dark:text-neutral-400">{{ t('common.side', { number: currentSide.side_number }) }}</p>
                            <p class="mt-1 text-xs text-slate-400 dark:text-neutral-500">{{ sideProgressText }}</p>
                        </div>

                        <button
                            class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-600 hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:text-neutral-300"
                            type="button"
                            :aria-label="t('study.nextSide')"
                            @click="nextSide"
                        >
                            <ChevronLeftIcon class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="p-5">
                        <AutoDirContent :text="currentSide.content" line-class="text-lg leading-9 text-slate-950 dark:text-neutral-100" />

                        <div v-if="currentSide.images?.length" class="mt-5 grid gap-3 sm:grid-cols-2">
                            <a
                                v-for="image in currentSide.images"
                                :key="image"
                                :href="image"
                                target="_blank"
                                rel="noreferrer"
                                class="group overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-neutral-800 dark:bg-neutral-950"
                            >
                                <img
                                    :src="image"
                                    :alt="t('common.side', { number: currentSide.side_number })"
                                    class="h-48 w-full object-contain transition group-hover:scale-[1.02]"
                                    loading="lazy"
                                >
                            </a>
                        </div>

                        <div v-if="currentSide.audios?.length" class="mt-5 space-y-3">
                            <div
                                v-for="audio in currentSide.audios"
                                :key="audio"
                                class="rounded-lg border border-slate-200 bg-white p-3 dark:border-neutral-800 dark:bg-neutral-950"
                            >
                                <audio class="w-full" controls :src="audio" preload="none" />
                                <a :href="audio" target="_blank" rel="noreferrer" class="mt-2 inline-flex items-center gap-2 text-xs font-semibold text-primary-700 dark:text-primary-300">
                                    <SpeakerWaveIcon class="h-4 w-4" />
                                    {{ t('common.audioFile') }}
                                </a>
                            </div>
                        </div>

                        <div v-if="!currentSide.images?.length && !currentSide.audios?.length" class="mt-5 flex items-center gap-2 text-xs text-slate-400 dark:text-neutral-500">
                            <PhotoIcon class="h-4 w-4" />
                            {{ t('common.noMedia') }}
                        </div>
                    </div>

                    <div v-if="currentSides.length > 1" class="flex items-center justify-center gap-2 border-t border-slate-200 px-4 py-3 dark:border-neutral-800">
                        <button
                            v-for="(side, index) in currentSides"
                            :key="side.id"
                            class="h-2.5 rounded-full transition-all"
                            :class="index === currentSideIndex ? 'w-7 bg-primary-600' : 'w-2.5 bg-slate-300 hover:bg-primary-300 dark:bg-neutral-700'"
                            type="button"
                            :aria-label="t('study.goToSide', { number: side.side_number })"
                            @click="goToSide(index)"
                        />
                    </div>
                </section>
            </div>

            <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 p-5 dark:border-neutral-800">
                <button
                    class="grid h-12 w-12 place-items-center rounded-md border border-slate-200 text-slate-700 hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:text-neutral-200 dark:hover:text-primary-300"
                    type="button"
                    :aria-label="t('study.nextCard')"
                    :title="t('study.nextCard')"
                    @click="nextCard"
                >
                    <ChevronRightIcon class="h-5 w-5" />
                </button>

                <button
                    class="grid h-12 w-12 place-items-center rounded-md border border-slate-200 text-slate-600 hover:border-primary-400 hover:text-primary-700 disabled:opacity-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:text-primary-300"
                    type="button"
                    :disabled="submitting"
                    :aria-label="t('common.customStep')"
                    :title="t('common.customStep')"
                    @click="customOpen = true"
                >
                    <ArrowsRightLeftIcon class="h-6 w-6" />
                </button>

                <button
                    class="grid h-12 w-12 place-items-center rounded-md border border-slate-200 text-slate-700 hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:text-neutral-200 dark:hover:text-primary-300"
                    type="button"
                    :aria-label="t('study.previousCard')"
                    :title="t('study.previousCard')"
                    @click="previousCard"
                >
                    <ChevronLeftIcon class="h-5 w-5" />
                </button>
            </footer>
        </article>

        <div v-if="customOpen" class="fixed inset-0 z-50 grid place-items-center bg-black/70 px-4 py-8">
            <section class="w-full max-w-sm rounded-lg border border-slate-200 bg-white p-5 shadow-xl dark:border-neutral-700 dark:bg-neutral-950">
                <h2 class="mb-4 text-lg font-bold">{{ t('common.selectCustomStep') }}</h2>
                <select
                    v-model.number="customStep"
                    class="mb-4 h-11 w-full rounded-md border border-slate-200 bg-white px-3 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black"
                >
                    <option v-for="step in maxStep + 1" :key="step - 1" :value="step - 1">{{ t('common.step', { number: step - 1 }) }}</option>
                </select>
                <div class="mb-4 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-neutral-900 dark:text-neutral-400">
                    {{ t('study.rescheduleHint') }}
                </div>
                <div class="flex justify-end gap-2">
                    <button class="h-10 rounded-md border border-slate-200 px-4 text-sm font-semibold dark:border-neutral-700" type="button" @click="customOpen = false">
                        {{ t('common.cancel') }}
                    </button>
                    <button class="inline-flex h-10 items-center gap-2 rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50" type="button" :disabled="submitting" @click="moveToCustomStep">
                        <ArrowPathIcon v-if="submitting" class="h-5 w-5 animate-spin" />
                        <ArrowsRightLeftIcon v-else class="h-5 w-5" />
                        {{ t('common.submit') }}
                    </button>
                </div>
            </section>
        </div>
    </section>
</template>
