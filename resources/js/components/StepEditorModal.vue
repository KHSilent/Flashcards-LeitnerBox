<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, required: true },
    steps: { type: Array, required: true },
    summaries: { type: Array, required: true },
});

const emit = defineEmits(['close', 'save']);
const localSteps = ref([]);
const error = ref('');

const occupiedByStep = computed(() => {
    const map = new Map();
    props.summaries.forEach((summary) => {
        if (summary.index !== null) {
            map.set(summary.index, summary.total_count);
        }
    });
    return map;
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            localSteps.value = [...props.steps];
            error.value = '';
        }
    },
);

function addStep() {
    const last = localSteps.value[localSteps.value.length - 1] || 1;
    localSteps.value.push(last * 2);
}

function removeStep(index) {
    const stepIndex = index + 1;

    if (index !== localSteps.value.length - 1) {
        error.value = 'فقط گام انتهایی را حذف کنید تا کارت‌ها جابه‌جا نشوند.';
        return;
    }

    if ((occupiedByStep.value.get(stepIndex) || 0) > 0) {
        error.value = 'این گام کارت دارد و قابل حذف نیست.';
        return;
    }

    localSteps.value.splice(index, 1);
}

function save() {
    const normalized = localSteps.value.map((step) => Number(step)).filter((step) => step > 0);

    if (!normalized.length) {
        error.value = 'حداقل یک گام زمان‌دار لازم است.';
        return;
    }

    emit('save', normalized);
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 grid place-items-center bg-black/70 px-4 py-8">
        <section class="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-xl dark:border-neutral-700 dark:bg-neutral-950">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-neutral-800">
                <h2 class="text-lg font-bold">ویرایش گام‌ها</h2>
                <button class="h-9 w-9 rounded-md text-slate-500 hover:bg-slate-100 dark:hover:bg-neutral-900" type="button" aria-label="بستن" @click="emit('close')">
                    ×
                </button>
            </div>

            <div class="space-y-3 p-5">
                <div v-for="(step, index) in localSteps" :key="index" class="grid grid-cols-[auto_1fr_auto_auto] items-center gap-3">
                    <span class="text-sm font-semibold text-slate-500 dark:text-neutral-400">گام {{ index + 1 }}</span>
                    <input
                        v-model.number="localSteps[index]"
                        class="h-10 rounded-md border border-slate-200 bg-white px-3 text-left outline-none focus:border-primary-500 dark:border-neutral-700 dark:bg-black"
                        dir="ltr"
                        type="number"
                        min="1"
                    >
                    <span class="text-sm text-slate-500 dark:text-neutral-400">روز</span>
                    <button
                        class="h-10 w-10 rounded-md border border-slate-200 text-slate-500 hover:border-rose-300 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-700"
                        type="button"
                        aria-label="حذف گام"
                        :disabled="localSteps.length === 1"
                        @click="removeStep(index)"
                    >
                        −
                    </button>
                </div>

                <button
                    class="h-10 rounded-md border border-dashed border-primary-300 px-3 text-sm font-semibold text-primary-700 hover:bg-primary-50 dark:border-neutral-700 dark:text-primary-300 dark:hover:bg-neutral-900"
                    type="button"
                    @click="addStep"
                >
                    افزودن گام
                </button>

                <p v-if="error" class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/40 dark:text-rose-200">
                    {{ error }}
                </p>
            </div>

            <footer class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-neutral-800">
                <button class="h-10 rounded-md border border-slate-200 px-4 text-sm font-semibold dark:border-neutral-700" type="button" @click="emit('close')">
                    انصراف
                </button>
                <button class="h-10 rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700" type="button" @click="save">
                    ذخیره
                </button>
            </footer>
        </section>
    </div>
</template>
