<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    PencilSquareIcon,
    PlusIcon,
    QueueListIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    categoryId: { type: Number, required: true },
});

const emit = defineEmits(['changed']);

const loading = ref(true);
const saving = ref(false);
const modalOpen = ref(false);
const editingId = ref(null);
const error = ref('');
const message = ref('');
const flashcards = ref([]);
const pagination = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: null,
    to: null,
});
const form = reactive({
    title: '',
    sides: [],
});

const editingCard = computed(() => flashcards.value.find((card) => card.id === editingId.value));

function firstError(exception, fallback) {
    return exception.response?.data?.message || Object.values(exception.response?.data?.errors || {})?.flat()?.[0] || fallback;
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
    form.sides = [blankSide('', 1), blankSide('', 2)];
}

function openCreateModal() {
    resetForm();
    modalOpen.value = true;
}

function openEditModal(card) {
    editingId.value = card.id;
    form.title = card.title || '';
    form.sides = card.sides.map((side) => ({
        side_number: side.side_number,
        content: side.content,
        images_text: (side.images || []).join('\n'),
        audios_text: (side.audios || []).join('\n'),
        image_files: [],
        audio_files: [],
    }));
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    resetForm();
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

function payload(method = null) {
    const data = new FormData();

    if (method) {
        data.append('_method', method);
    }

    data.append('title', form.title || '');

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
    } catch (exception) {
        error.value = firstError(exception, 'دریافت کارت‌ها انجام نشد.');
    } finally {
        loading.value = false;
    }
}

async function saveCard() {
    saving.value = true;
    error.value = '';
    message.value = '';

    try {
        if (editingId.value) {
            await window.axios.post(`/api/categories/${props.categoryId}/flashcards/${editingId.value}`, payload('PUT'));
            message.value = 'کارت ویرایش شد.';
            await fetchCards(pagination.current_page);
        } else {
            await window.axios.post(`/api/categories/${props.categoryId}/flashcards`, payload());
            message.value = 'کارت اضافه شد.';
            await fetchCards(1);
        }

        closeModal();
        emit('changed');
    } catch (exception) {
        error.value = firstError(exception, 'ذخیره کارت انجام نشد.');
    } finally {
        saving.value = false;
    }
}

async function deleteCard(card) {
    if (!window.confirm('این کارت حذف شود؟')) {
        return;
    }

    saving.value = true;
    error.value = '';
    message.value = '';

    try {
        await window.axios.delete(`/api/categories/${props.categoryId}/flashcards/${card.id}`);
        message.value = 'کارت حذف شد.';
        const nextPage = flashcards.value.length === 1 && pagination.current_page > 1
            ? pagination.current_page - 1
            : pagination.current_page;
        await fetchCards(nextPage);
        emit('changed');
    } catch (exception) {
        error.value = firstError(exception, 'حذف کارت انجام نشد.');
    } finally {
        saving.value = false;
    }
}

onMounted(() => fetchCards(1));
</script>

<template>
    <section class="mt-5 rounded-lg border border-slate-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-4 dark:border-neutral-800">
            <div class="flex items-center gap-2">
                <QueueListIcon class="h-5 w-5 text-primary-600" />
                <div>
                    <h2 class="font-bold">مدیریت کارت‌ها</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-neutral-400">کارت‌ها مستقل از گام مطالعه قابل ویرایش هستند.</p>
                </div>
            </div>
            <button
                class="inline-flex h-10 items-center gap-2 rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700"
                type="button"
                @click="openCreateModal"
            >
                <PlusIcon class="h-5 w-5" />
                افزودن کارت
            </button>
        </header>

        <div class="p-4">
            <p v-if="message" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ message }}
            </p>
            <p v-if="error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                {{ error }}
            </p>

            <div v-if="loading" class="text-sm text-slate-500 dark:text-neutral-400">در حال دریافت کارت‌ها...</div>

            <div v-else class="space-y-3">
                <article
                    v-for="card in flashcards"
                    :key="card.id"
                    class="grid gap-3 rounded-lg border border-slate-200 p-3 dark:border-neutral-800 md:grid-cols-[1fr_auto] md:items-center"
                >
                    <div class="min-w-0">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <p class="truncate font-bold">{{ card.title || `کارت ${card.id}` }}</p>
                            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600 dark:bg-neutral-900 dark:text-neutral-300">
                                {{ card.sides.length }} رو
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
                            title="ویرایش"
                            @click="openEditModal(card)"
                        >
                            <PencilSquareIcon class="h-5 w-5" />
                        </button>
                        <button
                            class="grid h-10 w-10 place-items-center rounded-md border border-rose-200 text-rose-600 hover:bg-rose-50 disabled:opacity-40 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                            type="button"
                            title="حذف"
                            :disabled="saving"
                            @click="deleteCard(card)"
                        >
                            <TrashIcon class="h-5 w-5" />
                        </button>
                    </div>
                </article>

                <div v-if="!flashcards.length" class="rounded-lg border border-dashed border-slate-200 p-5 text-center text-sm text-slate-500 dark:border-neutral-800 dark:text-neutral-400">
                    هنوز کارتی برای این دسته ثبت نشده است.
                </div>
            </div>
        </div>

        <footer v-if="pagination.total" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-sm dark:border-neutral-800">
            <p class="text-slate-500 dark:text-neutral-400">
                نمایش {{ pagination.from }} تا {{ pagination.to }} از {{ pagination.total }}
            </p>
            <div class="flex items-center gap-2">
                <button
                    class="h-9 rounded-md border border-slate-200 px-3 font-semibold disabled:opacity-40 dark:border-neutral-700"
                    type="button"
                    :disabled="pagination.current_page <= 1 || loading"
                    @click="fetchCards(pagination.current_page - 1)"
                >
                    قبلی
                </button>
                <span class="min-w-20 text-center text-slate-600 dark:text-neutral-300">
                    {{ pagination.current_page }} / {{ pagination.last_page }}
                </span>
                <button
                    class="h-9 rounded-md border border-slate-200 px-3 font-semibold disabled:opacity-40 dark:border-neutral-700"
                    type="button"
                    :disabled="pagination.current_page >= pagination.last_page || loading"
                    @click="fetchCards(pagination.current_page + 1)"
                >
                    بعدی
                </button>
            </div>
        </footer>

        <div v-if="modalOpen" class="fixed inset-0 z-50 grid place-items-center bg-black/70 px-4 py-8">
            <form class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-xl dark:border-neutral-700 dark:bg-neutral-950" @submit.prevent="saveCard">
                <header class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4 dark:border-neutral-800 dark:bg-neutral-950">
                    <div class="flex items-center gap-2">
                        <PlusIcon v-if="!editingId" class="h-5 w-5 text-primary-600" />
                        <PencilSquareIcon v-else class="h-5 w-5 text-primary-600" />
                        <h2 class="text-lg font-bold">{{ editingId ? 'ویرایش کارت' : 'کارت جدید' }}</h2>
                    </div>
                    <button class="grid h-9 w-9 place-items-center rounded-md text-slate-500 hover:bg-slate-100 dark:hover:bg-neutral-900" type="button" aria-label="بستن" @click="closeModal">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </header>

                <div class="space-y-4 p-5">
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">عنوان</span>
                        <input v-model="form.title" class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black">
                    </label>

                    <section
                        v-for="(side, index) in form.sides"
                        :key="index"
                        class="rounded-lg border border-slate-200 p-4 dark:border-neutral-800"
                    >
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <h3 class="font-bold">روی</h3>
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
                                حذف رو
                            </button>
                        </div>

                        <label class="mb-3 block">
                            <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">متن</span>
                            <textarea v-model="side.content" class="min-h-28 w-full rounded-md border border-slate-200 bg-white px-3 py-2 leading-7 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" required />
                        </label>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">لینک تصاویر</span>
                                <textarea v-model="side.images_text" class="min-h-20 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-left text-sm leading-6 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">لینک ویس‌ها</span>
                                <textarea v-model="side.audios_text" class="min-h-20 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-left text-sm leading-6 outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black" dir="ltr" />
                            </label>
                        </div>

                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">فایل تصویر</span>
                                <input
                                    class="block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-black"
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    @change="setFiles(side, 'image_files', $event)"
                                >
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-slate-700 dark:text-neutral-200">فایل ویس</span>
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
                        افزودن رو
                    </button>
                </div>

                <footer class="sticky bottom-0 flex justify-end gap-2 border-t border-slate-200 bg-white px-5 py-4 dark:border-neutral-800 dark:bg-neutral-950">
                    <button class="h-10 rounded-md border border-slate-200 px-4 text-sm font-semibold dark:border-neutral-700" type="button" @click="closeModal">
                        انصراف
                    </button>
                    <button class="h-10 rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50" type="submit" :disabled="saving">
                        ذخیره
                    </button>
                </footer>
            </form>
        </div>
    </section>
</template>
