<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    ArrowPathIcon,
    EyeIcon,
    PencilSquareIcon,
    PlusIcon,
    QueueListIcon,
    SparklesIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import AutoDirContent from './AutoDirContent.vue';
import { apiError, t } from '../i18n';

const props = defineProps({
    categoryId: { type: Number, required: true },
});

const emit = defineEmits(['changed']);

const loading = ref(true);
const saving = ref(false);
const smartProcessing = ref(false);
const modalOpen = ref(false);
const modalTab = ref('single');
const editingId = ref(null);
const previewCard = ref(null);
const error = ref('');
const message = ref('');
const flashcards = ref([]);
const typeOptions = [
    { value: 'english-active', labelKey: 'cards.englishActive' },
    { value: 'english-passive', labelKey: 'cards.englishPassive' },
    { value: 'other', labelKey: 'cards.otherType' },
];
const smartTypeOptions = typeOptions.filter((option) => ['english-active', 'english-passive'].includes(option.value));
const createTabs = [
    { value: 'single', labelKey: 'cards.single' },
    { value: 'smart', labelKey: 'cards.smart' },
    { value: 'normal', labelKey: 'cards.normal' },
];
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
    title: '',
    type: 'other',
    sides: [],
});
const bulkSmartForm = reactive({
    type: 'english-active',
    items: '',
});
const bulkNormalForm = reactive({
    type: 'other',
    sides: [],
});

const editingCard = computed(() => flashcards.value.find((card) => card.id === editingId.value));
const canSmartProcessPreview = computed(() => ['english-active', 'english-passive'].includes(previewCard.value?.type));
const modalSubmitLabel = computed(() => {
    if (editingId.value) {
        return t('common.save');
    }

    return t(modalTab.value === 'smart' || modalTab.value === 'normal' ? 'cards.addCards' : 'common.save');
});
const bulkNormalRows = computed(() => bulkNormalForm.sides.map((side) => splitRows(side.content)));
const bulkNormalLineCount = computed(() => Math.max(0, ...bulkNormalRows.value.map((rows) => rows.length)));
const bulkNormalHasCard = computed(() => Array.from({ length: bulkNormalLineCount.value }).some((_, index) => bulkNormalRows.value.some((rows) => (rows[index] || '').trim() !== '')));
const bulkNormalLineMismatch = computed(() => bulkNormalForm.sides.length < 2 || !bulkNormalHasCard.value);

function typeLabel(type) {
    return t(typeOptions.find((option) => option.value === type)?.labelKey || 'cards.otherType');
}

function blankSide(content = '', sideNumber = nextSideNumber()) {
    return {
        side_number: sideNumber,
        content,
        images_text: '',
        audios_text: '',
        image_files: [],
        audio_files: [],
    };
}

function nextSideNumber() {
    const max = Math.max(0, ...form.sides.map((side) => Number(side.side_number) || 0));

    return max + 1;
}

function resetForm() {
    editingId.value = null;
    form.title = '';
    form.type = 'other';
    form.sides = [blankSide('', 1)];
}

function resetBulkForms() {
    bulkSmartForm.type = 'english-active';
    bulkSmartForm.items = '';
    bulkNormalForm.type = 'other';
    bulkNormalForm.sides = [
        { side_number: 1, content: '' },
        { side_number: 2, content: '' },
    ];
}

function openCreateModal() {
    resetForm();
    resetBulkForms();
    modalTab.value = 'single';
    modalOpen.value = true;
}

function openEditModal(card) {
    editingId.value = card.id;
    form.title = card.title || '';
    form.type = card.type || 'other';
    form.sides = card.sides.map((side) => ({
        side_number: side.side_number,
        content: side.content,
        images_text: (side.raw_images || side.images || []).join('\n'),
        audios_text: (side.raw_audios || side.audios || []).join('\n'),
        image_files: [],
        audio_files: [],
    }));
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    resetForm();
    resetBulkForms();
}

function openPreview(card) {
    previewCard.value = card;
}

function closePreview() {
    previewCard.value = null;
}

function addSide() {
    form.sides.push(blankSide());
}

function removeSide(index) {
    if (form.sides.length <= 1) {
        return;
    }

    form.sides.splice(index, 1);
}

function linesToArray(value) {
    return String(value || '')
        .split('\n')
        .map((item) => item.trim())
        .filter(Boolean);
}

function setFiles(side, key, event) {
    side[key] = Array.from(event.target.files || []);
}

function addBulkNormalSide() {
    const max = Math.max(0, ...bulkNormalForm.sides.map((side) => Number(side.side_number) || 0));
    bulkNormalForm.sides.push({ side_number: max + 1, content: '' });
}

function removeBulkNormalSide(index) {
    if (bulkNormalForm.sides.length <= 2) {
        return;
    }

    bulkNormalForm.sides.splice(index, 1);
}

function splitLines(value) {
    return String(value || '')
        .split(/\r?\n/)
        .map((item) => item.trim())
        .filter(Boolean);
}

function splitRows(value) {
    const text = String(value || '');

    if (!text.trim()) {
        return [];
    }

    return text
        .split(/\r?\n/)
        .map((item) => item.trim());
}

function payload(method = null) {
    const data = new FormData();

    if (method) {
        data.append('_method', method);
    }

    data.append('title', form.title || '');
    data.append('type', form.type || 'other');

    form.sides.forEach((side, index) => {
        data.append(`sides[${index}][side_number]`, side.side_number);
        data.append(`sides[${index}][content]`, side.content);

        linesToArray(side.images_text).forEach((url) => {
            data.append(`sides[${index}][images][]`, url);
        });
        linesToArray(side.audios_text).forEach((url) => {
            data.append(`sides[${index}][audios][]`, url);
        });
        side.image_files.forEach((file) => {
            data.append(`sides[${index}][image_files][]`, file);
        });
        side.audio_files.forEach((file) => {
            data.append(`sides[${index}][audio_files][]`, file);
        });
    });

    return data;
}

async function fetchCards(page = pagination.current_page) {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get(`/api/categories/${props.categoryId}/flashcards`, {
            params: {
                page,
                per_page: pagination.per_page,
            },
        });

        flashcards.value = data.flashcards;
        Object.assign(pagination, data.meta);
        pageInput.value = pagination.current_page;
    } catch (exception) {
        error.value = apiError(exception, 'cards.fetchFailed');
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
    fetchCards(normalizedPage(pageInput.value));
}

async function saveCard() {
    saving.value = true;
    error.value = '';
    message.value = '';

    try {
        if (editingId.value) {
            await window.axios.post(`/api/categories/${props.categoryId}/flashcards/${editingId.value}`, payload('PUT'));
            message.value = t('cards.updated');
            await fetchCards(pagination.current_page);
        } else {
            await window.axios.post(`/api/categories/${props.categoryId}/flashcards`, payload());
            message.value = t('cards.created');
            await fetchCards(1);
        }

        closeModal();
        emit('changed');
    } catch (exception) {
        error.value = apiError(exception, 'cards.saveFailed');
    } finally {
        saving.value = false;
    }
}

async function submitModal() {
    if (editingId.value || modalTab.value === 'single') {
        await saveCard();
        return;
    }

    if (modalTab.value === 'smart') {
        await saveBulkSmart();
        return;
    }

    await saveBulkNormal();
}

async function saveBulkSmart() {
    saving.value = true;
    error.value = '';
    message.value = '';

    try {
        const { data } = await window.axios.post(`/api/categories/${props.categoryId}/flashcards/bulk-smart`, {
            type: bulkSmartForm.type,
            items: bulkSmartForm.items,
        });

        message.value = t('cards.smartQueuedCount', { count: data.created });
        await fetchCards(1);
        closeModal();
        emit('changed');
    } catch (exception) {
        error.value = apiError(exception, 'cards.smartBulkFailed');
    } finally {
        saving.value = false;
    }
}

async function saveBulkNormal() {
    if (bulkNormalLineMismatch.value) {
        error.value = t('cards.textRequired');
        return;
    }

    saving.value = true;
    error.value = '';
    message.value = '';

    try {
        const { data } = await window.axios.post(`/api/categories/${props.categoryId}/flashcards/bulk-normal`, {
            type: bulkNormalForm.type,
            sides: bulkNormalForm.sides,
        });

        message.value = t('cards.bulkCreated', { count: data.created });
        await fetchCards(1);
        closeModal();
        emit('changed');
    } catch (exception) {
        error.value = apiError(exception, 'cards.bulkFailed');
    } finally {
        saving.value = false;
    }
}

async function deleteCard(card) {
    if (!window.confirm(t('cards.deleteConfirm'))) {
        return;
    }

    saving.value = true;
    error.value = '';
    message.value = '';

    try {
        await window.axios.delete(`/api/categories/${props.categoryId}/flashcards/${card.id}`);
        message.value = t('cards.deleted');
        const nextPage = flashcards.value.length === 1 && pagination.current_page > 1
            ? pagination.current_page - 1
            : pagination.current_page;
        await fetchCards(nextPage);
        emit('changed');
    } catch (exception) {
        error.value = apiError(exception, 'cards.deleteFailed');
    } finally {
        saving.value = false;
    }
}

async function smartProcess(card) {
    smartProcessing.value = true;
    error.value = '';
    message.value = '';

    try {
        const { data } = await window.axios.post(`/api/categories/${props.categoryId}/flashcards/${card.id}/smart-process`);
        const index = flashcards.value.findIndex((item) => item.id === card.id);

        if (index !== -1) {
            flashcards.value.splice(index, 1, data.flashcard);
        }

        previewCard.value = data.flashcard;
        message.value = t('cards.smartQueued');
        emit('changed');
    } catch (exception) {
        error.value = apiError(exception, 'cards.smartFailed');
    } finally {
        smartProcessing.value = false;
    }
}

function isAiQueued(card) {
    return card?.needs_ai_processing || ['pending', 'processing'].includes(card?.ai_processing_status);
}

function aiStatusLabel(card) {
    if (card?.ai_processing_status === 'processing') {
        return t('cards.processing');
    }

    if (card?.ai_processing_status === 'pending' || card?.needs_ai_processing) {
        return t('cards.queued');
    }

    if (card?.ai_processing_status === 'failed') {
        return t('cards.processingFailed');
    }

    return '';
}

onMounted(() => fetchCards(1));
</script>

<template>
    <section class="mt-5 rounded-lg border border-slate-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-4 dark:border-neutral-800">
            <div class="flex items-center gap-2">
                <QueueListIcon class="h-5 w-5 text-primary-600" />
                <div>
                    <h2 class="font-bold">{{ t('cards.manage') }}</h2>
                </div>
            </div>
            <button
                class="grid h-10 w-10 place-items-center rounded-md border border-primary-200 text-primary-700 hover:border-primary-400 hover:bg-primary-50 dark:border-neutral-700 dark:text-primary-300 dark:hover:bg-neutral-900"
                type="button"
                :aria-label="t('cards.add')"
                :title="t('cards.add')"
                @click="openCreateModal"
            >
                <PlusIcon class="h-5 w-5" />
            </button>
        </header>

        <div class="p-4">
            <p v-if="message" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ message }}
            </p>
            <p v-if="error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                {{ error }}
            </p>

            <div v-if="loading" class="text-sm text-slate-500 dark:text-neutral-400">{{ t('cards.loading') }}</div>

            <div v-else class="space-y-3">
                <article
                    v-for="card in flashcards"
                    :key="card.id"
                    class="grid gap-3 rounded-lg border border-slate-200 p-3 dark:border-neutral-800 md:grid-cols-[1fr_auto] md:items-center"
                >
                    <div class="min-w-0">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <p class="truncate font-bold">{{ card.title || t('cards.fallbackTitle', { id: card.id }) }}</p>
                            <span class="rounded-md bg-primary-50 px-2 py-1 text-xs font-semibold text-primary-700 dark:bg-neutral-900 dark:text-primary-300">
                                {{ typeLabel(card.type) }}
                            </span>
                            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600 dark:bg-neutral-900 dark:text-neutral-300">
                                {{ t('cards.sides', { count: card.sides.length }) }}
                            </span>
                            <span
                                v-if="aiStatusLabel(card)"
                                class="rounded-md px-2 py-1 text-xs font-semibold"
                                :class="card.ai_processing_status === 'failed' ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200'"
                            >
                                {{ aiStatusLabel(card) }}
                            </span>
                        </div>
                        <p class="line-clamp-2 whitespace-pre-line text-sm leading-7 text-slate-600 dark:text-neutral-300">
                            {{ card.sides[0]?.content }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-600 hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:text-neutral-300"
                            type="button"
                            :title="t('cards.preview')"
                            @click="openPreview(card)"
                        >
                            <EyeIcon class="h-5 w-5" />
                        </button>
                        <button
                            class="grid h-10 w-10 place-items-center rounded-md border border-slate-200 text-slate-600 hover:border-primary-400 hover:text-primary-700 dark:border-neutral-700 dark:text-neutral-300"
                            type="button"
                            :title="t('common.edit')"
                            @click="openEditModal(card)"
                        >
                            <PencilSquareIcon class="h-5 w-5" />
                        </button>
                        <button
                            class="grid h-10 w-10 place-items-center rounded-md border border-rose-200 text-rose-600 hover:bg-rose-50 disabled:opacity-40 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                            type="button"
                            :title="t('common.delete')"
                            :disabled="saving"
                            @click="deleteCard(card)"
                        >
                            <TrashIcon class="h-5 w-5" />
                        </button>
                    </div>
                </article>

                <div v-if="!flashcards.length" class="rounded-lg border border-dashed border-slate-200 p-5 text-center text-sm text-slate-500 dark:border-neutral-800 dark:text-neutral-400">
                    {{ t('cards.empty') }}
                </div>
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
                    @click="fetchCards(pagination.current_page - 1)"
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
                    @click="fetchCards(pagination.current_page + 1)"
                >
                    {{ t('common.next') }}
                </button>
            </div>
        </footer>

        <div v-if="modalOpen" class="fixed inset-0 z-50 grid place-items-center bg-black/70 px-4 py-8">
            <form class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-xl dark:border-neutral-700 dark:bg-neutral-950" @submit.prevent="submitModal">
                <header class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4 dark:border-neutral-800 dark:bg-neutral-950">
                    <div class="flex items-center gap-2">
                        <PlusIcon v-if="!editingId" class="h-5 w-5 text-primary-600" />
                        <PencilSquareIcon v-else class="h-5 w-5 text-primary-600" />
                        <h2 class="text-lg font-bold">{{ t(editingId ? 'cards.edit' : 'cards.new') }}</h2>
                    </div>
                    <button class="grid h-9 w-9 place-items-center rounded-md text-slate-500 hover:bg-slate-100 dark:hover:bg-neutral-900" type="button" :aria-label="t('common.close')" @click="closeModal">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </header>

                <div class="space-y-4 p-5">
                    <div v-if="!editingId" class="grid grid-cols-3 gap-2 rounded-md bg-slate-100 p-1 text-sm dark:bg-neutral-900">
                        <button
                            v-for="tab in createTabs"
                            :key="tab.value"
                            class="h-10 rounded-md font-semibold"
                            :class="modalTab === tab.value ? 'bg-white text-primary-700 shadow-sm dark:bg-black dark:text-primary-300' : 'text-slate-500 hover:text-slate-800 dark:text-neutral-400 dark:hover:text-neutral-100'"
                            type="button"
                            @click="modalTab = tab.value"
                        >
                            {{ t(tab.labelKey) }}
                        </button>
                    </div>

                    <template v-if="editingId || modalTab === 'single'">
                        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_13rem]">
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('common.title') }}</span>
                                <input v-model="form.title" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('common.type') }}</span>
                                <select v-model="form.type" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black">
                                    <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                                        {{ t(option.labelKey) }}
                                    </option>
                                </select>
                            </label>
                        </div>

                        <section
                            v-for="(side, index) in form.sides"
                            :key="index"
                            class="rounded-lg border border-slate-200 p-4 dark:border-neutral-800"
                        >
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <h3 class="font-bold">{{ t('cards.side') }}</h3>
                                    <input
                                        v-model.number="side.side_number"
                                        class="h-9 w-20 rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black"
                                        dir="ltr"
                                        type="number"
                                        min="1"
                                        required
                                    >
                                </div>
                                <button
                                    class="h-9 rounded-md border border-rose-200 px-3 text-sm font-semibold text-rose-600 disabled:opacity-40 dark:border-rose-900 dark:text-rose-300"
                                    type="button"
                                    :disabled="form.sides.length <= 1"
                                    @click="removeSide(index)"
                                >
                                    {{ t('cards.removeSide') }}
                                </button>
                            </div>

                            <label class="mb-3 block">
                                <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('cards.text') }}</span>
                                <textarea v-model="side.content" class="min-h-28 w-full rounded-md border border-slate-200 bg-white px-3 py-2 leading-7 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="auto" required />
                            </label>

                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('cards.imageLinks') }}</span>
                                    <textarea v-model="side.images_text" class="min-h-20 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-left text-sm leading-6 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('cards.audioLinks') }}</span>
                                    <textarea v-model="side.audios_text" class="min-h-20 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-left text-sm leading-6 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" />
                                </label>
                            </div>

                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('cards.imageFile') }}</span>
                                    <input
                                        class="block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-black"
                                        type="file"
                                        accept="image/*"
                                        multiple
                                        @change="setFiles(side, 'image_files', $event)"
                                    >
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('cards.voiceFile') }}</span>
                                    <input
                                        class="block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-black"
                                        type="file"
                                        accept="audio/*"
                                        multiple
                                        @change="setFiles(side, 'audio_files', $event)"
                                    >
                                </label>
                            </div>
                        </section>

                        <button
                            class="inline-flex h-10 items-center gap-2 rounded-md border border-dashed border-primary-300 px-3 text-sm font-semibold text-primary-700 hover:bg-primary-50 dark:border-neutral-700 dark:text-primary-300 dark:hover:bg-neutral-900"
                            type="button"
                            @click="addSide"
                        >
                            <PlusIcon class="h-5 w-5" />
                            {{ t('cards.addSide') }}
                        </button>
                    </template>

                    <template v-else-if="modalTab === 'smart'">
                        <label class="block">
                            <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('common.type') }}</span>
                            <select v-model="bulkSmartForm.type" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black">
                                <option v-for="option in smartTypeOptions" :key="option.value" :value="option.value">
                                    {{ t(option.labelKey) }}
                                </option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('cards.oneWordPerLine') }}</span>
                            <textarea v-model="bulkSmartForm.items" class="min-h-72 w-full rounded-md border border-slate-200 bg-white px-3 py-2 leading-7 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="auto" required />
                            <span class="mt-1 block text-xs text-slate-500 dark:text-neutral-400">{{ t('cards.wordCount', { count: splitLines(bulkSmartForm.items).length }) }}</span>
                        </label>
                    </template>

                    <template v-else>
                        <label class="block">
                            <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">{{ t('common.type') }}</span>
                            <select v-model="bulkNormalForm.type" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black">
                                <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                                    {{ t(option.labelKey) }}
                                </option>
                            </select>
                        </label>

                        <section
                            v-for="(side, index) in bulkNormalForm.sides"
                            :key="index"
                            class="rounded-lg border border-slate-200 p-4 dark:border-neutral-800"
                        >
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <h3 class="font-bold">{{ t('cards.side') }}</h3>
                                    <input
                                        v-model.number="side.side_number"
                                        class="h-9 w-20 rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black"
                                        dir="ltr"
                                        type="number"
                                        min="1"
                                        required
                                    >
                                </div>
                                <button
                                    class="h-9 rounded-md border border-rose-200 px-3 text-sm font-semibold text-rose-600 disabled:opacity-40 dark:border-rose-900 dark:text-rose-300"
                                    type="button"
                                    :disabled="bulkNormalForm.sides.length <= 2"
                                    @click="removeBulkNormalSide(index)"
                                >
                                    {{ t('cards.removeSide') }}
                                </button>
                            </div>
                            <textarea v-model="side.content" class="min-h-40 w-full rounded-md border border-slate-200 bg-white px-3 py-2 leading-7 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="auto" required />
                            <span class="mt-1 block text-xs text-slate-500 dark:text-neutral-400">{{ t('cards.rowCount', { count: splitRows(side.content).length }) }}</span>
                        </section>

                        <p v-if="bulkNormalLineMismatch" class="rounded-md bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
                            {{ t('cards.textRequired') }}
                        </p>
                        <p v-else class="text-xs text-slate-500 dark:text-neutral-400">
                            {{ t('cards.generatedCount', { count: bulkNormalLineCount }) }}
                        </p>

                        <button
                            class="inline-flex h-10 items-center gap-2 rounded-md border border-dashed border-primary-300 px-3 text-sm font-semibold text-primary-700 hover:bg-primary-50 dark:border-neutral-700 dark:text-primary-300 dark:hover:bg-neutral-900"
                            type="button"
                            @click="addBulkNormalSide"
                        >
                            <PlusIcon class="h-5 w-5" />
                            {{ t('cards.addSide') }}
                        </button>
                    </template>
                </div>

                <footer class="sticky bottom-0 flex justify-end gap-2 border-t border-slate-200 bg-white px-5 py-4 dark:border-neutral-800 dark:bg-neutral-950">
                    <button class="h-10 rounded-md border border-slate-200 px-4 text-sm font-semibold dark:border-neutral-700" type="button" @click="closeModal">
                        {{ t('common.cancel') }}
                    </button>
                    <button class="h-10 rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50" type="submit" :disabled="saving || (!editingId && modalTab === 'normal' && bulkNormalLineMismatch)">
                        {{ modalSubmitLabel }}
                    </button>
                </footer>
            </form>
        </div>

        <div v-if="previewCard" class="fixed inset-0 z-50 grid place-items-center bg-black/70 px-4 py-8">
            <section class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-xl dark:border-neutral-700 dark:bg-neutral-950">
                <header class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 dark:border-neutral-800 dark:bg-neutral-950">
                    <div class="min-w-0">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <h2 class="truncate text-lg font-bold">{{ previewCard.title || t('cards.fallbackTitle', { id: previewCard.id }) }}</h2>
                            <span class="rounded-md bg-primary-50 px-2 py-1 text-xs font-semibold text-primary-700 dark:bg-neutral-900 dark:text-primary-300">
                                {{ typeLabel(previewCard.type) }}
                            </span>
                            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600 dark:bg-neutral-900 dark:text-neutral-300">
                                {{ t('cards.sides', { count: previewCard.sides.length }) }}
                            </span>
                            <span
                                v-if="aiStatusLabel(previewCard)"
                                class="rounded-md px-2 py-1 text-xs font-semibold"
                                :class="previewCard.ai_processing_status === 'failed' ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200'"
                            >
                                {{ aiStatusLabel(previewCard) }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-neutral-400">{{ t('cards.cardPreview') }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            v-if="canSmartProcessPreview"
                            class="inline-flex h-9 items-center gap-2 rounded-md bg-primary-600 px-3 text-xs font-bold text-white hover:bg-primary-700 disabled:cursor-wait disabled:opacity-60"
                            type="button"
                            :disabled="smartProcessing || isAiQueued(previewCard)"
                            @click="smartProcess(previewCard)"
                        >
                            <ArrowPathIcon v-if="smartProcessing" class="h-4 w-4 animate-spin" />
                            <SparklesIcon v-else class="h-4 w-4" />
                            {{ t(isAiQueued(previewCard) ? 'cards.inQueue' : 'cards.smartProcess') }}
                        </button>
                        <button class="grid h-9 w-9 place-items-center rounded-md text-slate-500 hover:bg-slate-100 dark:hover:bg-neutral-900" type="button" :aria-label="t('common.close')" @click="closePreview">
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </div>
                </header>

                <div class="space-y-4 p-5">
                    <article
                        v-for="side in previewCard.sides"
                        :key="side.id || side.side_number"
                        class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-neutral-800 dark:bg-black"
                    >
                        <header class="border-b border-slate-200 px-4 py-3 dark:border-neutral-800">
                            <h3 class="text-sm font-bold text-slate-700 dark:text-neutral-200">{{ t('common.side', { number: side.side_number }) }}</h3>
                        </header>

                        <div class="space-y-4 p-4">
                            <AutoDirContent :text="side.content" line-class="text-base leading-8 text-slate-950 dark:text-neutral-100" />

                            <div v-if="side.images?.length" class="grid gap-3 sm:grid-cols-2">
                                <a
                                    v-for="image in side.images"
                                    :key="image"
                                    :href="image"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-neutral-800 dark:bg-neutral-950"
                                >
                                    <img :src="image" :alt="t('common.side', { number: side.side_number })" class="h-56 w-full object-contain" loading="lazy">
                                </a>
                            </div>

                            <div v-if="side.audios?.length" class="space-y-3">
                                <div
                                    v-for="audio in side.audios"
                                    :key="audio"
                                    class="rounded-lg border border-slate-200 bg-white p-3 dark:border-neutral-800 dark:bg-neutral-950"
                                >
                                    <audio class="w-full" controls :src="audio" preload="none" />
                                    <a :href="audio" target="_blank" rel="noreferrer" class="mt-2 block truncate text-left text-xs font-semibold text-primary-700 dark:text-primary-300" dir="ltr">
                                        {{ audio }}
                                    </a>
                                </div>
                            </div>

                        </div>
                    </article>
                </div>
            </section>
        </div>
    </section>
</template>
