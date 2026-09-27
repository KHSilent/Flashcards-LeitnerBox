<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
    ArrowPathIcon,
    ArrowsRightLeftIcon,
    CheckIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    PhotoIcon,
    SpeakerWaveIcon,
    TrophyIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import AutoDirContent from '../components/AutoDirContent.vue';
import { apiError, t } from '../i18n';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const submitting = ref(false);
const processingAction = ref(null);
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
const progressText = computed(() => t('common.pageProgress', { current: Math.min(currentIndex.value + 1, cards.value.length), total: cards.value.length }));
const maxStep = computed(() => steps.value.length);
const stepNumber = computed(() => Number(route.params.step));
const sideProgressText = computed(() => `${Math.min(currentSideIndex.value + 1, currentSides.value.length)} / ${currentSides.value.length}`);

async function fetchCards() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get(`/api/categories/${route.params.id}/study`, {
            params: { step: route.params.step },
        });
        category.value = data.category;
        cards.value = data.cards;
        steps.value = data.access.steps;
        customStep.value = Number(route.params.step);

        if (!data.cards.length) {
            router.replace({ name: 'category', params: { id: route.params.id } });
        }
    } catch (exception) {
        error.value = apiError(exception, 'study.fetchFailed');
    } finally {
        loading.value = false;
    }
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

function ignoresShortcuts(target) {
    return ['INPUT', 'SELECT', 'TEXTAREA', 'AUDIO', 'VIDEO', 'BUTTON'].includes(target?.tagName) || target?.isContentEditable;
}

function playCurrentAudio() {
    const audio = document.querySelector('[data-study-current-audio]');

    if (!audio) {
        return;
    }

    audio.currentTime = 0;
    audio.play().catch(() => {});
}

function handleShortcut(event) {
    if (!currentCard.value || submitting.value || customOpen.value || ignoresShortcuts(event.target)) {
        return;
    }

    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        nextSide();
    }

    if (event.key === 'ArrowRight') {
        event.preventDefault();
        previousSide();
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        answer('mastered');
    }

    if (event.key === ' ') {
        event.preventDefault();
        answer('known');
    }

    if (event.key === 'Backspace' || event.key === 'Delete') {
        event.preventDefault();
        answer('unknown');
    }

    if (event.key.toLowerCase() === 'p') {
        event.preventDefault();
        playCurrentAudio();
    }
}

async function answer(action, targetStep = null) {
    if (!currentCard.value) {
        return;
    }

    submitting.value = true;
    processingAction.value = action;
    error.value = '';

    try {
        await window.axios.post(`/api/categories/${route.params.id}/study-cards/${currentCard.value.study_card_id}/answer`, {
            action,
            target_step: targetStep,
        });

        customOpen.value = false;
        currentIndex.value += 1;
        currentSideIndex.value = 0;

        if (currentIndex.value >= cards.value.length) {
            router.replace({ name: 'category', params: { id: route.params.id } });
        }
    } catch (exception) {
        error.value = apiError(exception, 'study.saveFailed');
    } finally {
        submitting.value = false;
        processingAction.value = null;
    }
}

onMounted(() => {
    fetchCards();
    window.addEventListener('keydown', handleShortcut);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleShortcut);
});
</script>

<template>
    <section class="mx-auto max-w-4xl px-4 py-8">
        <RouterLink :to="{ name: 'category', params: { id: route.params.id } }" class="mb-4 inline-flex text-sm font-semibold text-primary-700 hover:text-primary-800 dark:text-primary-300">
            {{ t('study.back') }}
        </RouterLink>

        <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-neutral-800 dark:bg-neutral-950">
            {{ t('study.preparing') }}
        </div>

        <p v-else-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
            {{ error }}
        </p>

        <article v-else-if="currentCard" class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-neutral-800">
                <div>
                    <p class="text-sm text-slate-500 dark:text-neutral-400">{{ category?.name }} / {{ t('common.step', { number: stepNumber }) }}</p>
                    <h1 class="mt-1 text-xl font-bold">{{ currentCard.flashcard.title || t('app.name') }}</h1>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        class="grid h-11 w-11 place-items-center rounded-md border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 disabled:opacity-50 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                        type="button"
                        :disabled="submitting"
                        :aria-label="t('study.mastered')"
                        :title="t('study.mastered')"
                        @click="answer('mastered')"
                    >
                        <ArrowPathIcon v-if="processingAction === 'mastered'" class="h-6 w-6 animate-spin" />
                        <TrophyIcon v-else class="h-6 w-6" />
                    </button>
                    <span class="rounded-md bg-primary-100 px-3 py-1 text-sm font-semibold text-primary-700 dark:bg-neutral-900 dark:text-primary-300">{{ progressText }}</span>
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
                                <audio class="w-full" controls :src="audio" preload="none" data-study-current-audio />
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
                <div class="flex items-center gap-2">
                    <button
                        class="grid h-12 w-12 place-items-center rounded-md border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 disabled:opacity-50 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
                        type="button"
                        :disabled="submitting"
                        :aria-label="t('study.known')"
                        :title="t('study.known')"
                        @click="answer('known')"
                    >
                        <ArrowPathIcon v-if="processingAction === 'known'" class="h-6 w-6 animate-spin" />
                        <CheckIcon v-else class="h-6 w-6" />
                    </button>
                    <button
                        class="grid h-12 w-12 place-items-center rounded-md border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 disabled:opacity-50 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"
                        type="button"
                        :disabled="submitting"
                        :aria-label="t('study.unknown')"
                        :title="t('study.unknown')"
                        @click="answer('unknown')"
                    >
                        <ArrowPathIcon v-if="processingAction === 'unknown'" class="h-6 w-6 animate-spin" />
                        <XMarkIcon v-else class="h-6 w-6" />
                    </button>
                </div>

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
                <div class="flex justify-end gap-2">
                    <button class="h-10 rounded-md border border-slate-200 px-4 text-sm font-semibold dark:border-neutral-700" type="button" @click="customOpen = false">
                        {{ t('common.cancel') }}
                    </button>
                    <button class="inline-flex h-10 items-center gap-2 rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50" type="button" :disabled="submitting" @click="answer('custom', customStep)">
                        <ArrowPathIcon v-if="processingAction === 'custom'" class="h-5 w-5 animate-spin" />
                        <ArrowsRightLeftIcon v-else class="h-5 w-5" />
                        {{ t('common.submit') }}
                    </button>
                </div>
            </section>
        </div>
    </section>
</template>
