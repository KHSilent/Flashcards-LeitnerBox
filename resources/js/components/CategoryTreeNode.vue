<script setup>
import { FolderIcon, LockClosedIcon } from '@heroicons/vue/24/outline';

defineProps({
    category: { type: Object, required: true },
    depth: { type: Number, required: true },
});
</script>

<template>
    <div>
        <RouterLink
            v-if="category.access"
            :to="{ name: 'category', params: { id: category.id } }"
            class="grid min-h-16 grid-cols-[1fr_auto] items-center gap-3 border-b border-slate-100 px-4 py-3 transition hover:bg-primary-50 dark:border-neutral-800 dark:hover:bg-neutral-900"
            :style="{ paddingRight: `${16 + depth * 24}px` }"
        >
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-md bg-primary-100 text-primary-700 dark:bg-neutral-900 dark:text-primary-300">
                        <FolderIcon class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-slate-900 dark:text-neutral-100">{{ category.name }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-neutral-400">{{ category.flashcards_count }} کارت مستقیم</p>
                    </div>
                </div>
            </div>
            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600 dark:bg-neutral-900 dark:text-neutral-300">{{ category.access.steps.length + 1 }} گام</span>
        </RouterLink>

        <div
            v-else
            class="grid min-h-16 grid-cols-[1fr_auto] items-center gap-3 border-b border-slate-100 px-4 py-3 text-slate-400 dark:border-neutral-800"
            :style="{ paddingRight: `${16 + depth * 24}px` }"
        >
            <div class="flex items-center gap-2">
                <span class="grid h-8 w-8 place-items-center rounded-md bg-slate-100 dark:bg-neutral-900">
                    <LockClosedIcon class="h-5 w-5" />
                </span>
                <p class="text-sm font-semibold">{{ category.name }}</p>
            </div>
            <span class="text-xs">بدون دسترسی</span>
        </div>

        <CategoryTreeNode v-for="child in category.children" :key="child.id" :category="child" :depth="depth + 1" />
    </div>
</template>
