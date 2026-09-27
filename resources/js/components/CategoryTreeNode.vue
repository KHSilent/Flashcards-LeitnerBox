<script setup>
import { ref } from 'vue';
import { CalendarDaysIcon, ChartBarIcon, FolderIcon, InboxArrowDownIcon, LockClosedIcon } from '@heroicons/vue/24/outline';

const summaryOpen = ref(false);

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
            <div class="flex items-center gap-1.5" dir="ltr">
                <div
                    v-if="category.flashcards_count > 0"
                    class="group/progress relative inline-flex cursor-help items-center gap-1 rounded-md bg-primary-50 px-2 py-1 text-xs font-semibold tabular-nums text-primary-700 dark:bg-primary-950/40 dark:text-primary-300"
                    :aria-label="`پیشرفت ${category.access.progress_percent} درصد`"
                    role="button"
                    tabindex="0"
                    @click.stop.prevent="summaryOpen = !summaryOpen"
                    @keydown.enter.stop.prevent="summaryOpen = !summaryOpen"
                    @keydown.space.stop.prevent="summaryOpen = !summaryOpen"
                >
                    <ChartBarIcon class="h-4 w-4" aria-hidden="true" />
                    <span>{{ category.access.progress_percent }}%</span>
                    <div
                        class="pointer-events-none absolute left-0 top-full z-30 mt-2 w-56 rounded-lg border border-slate-200 bg-white p-3 text-right shadow-xl transition group-hover/progress:visible group-hover/progress:translate-y-0 group-hover/progress:opacity-100 dark:border-neutral-700 dark:bg-neutral-950"
                        :class="summaryOpen ? 'visible translate-y-0 opacity-100' : 'invisible translate-y-1 opacity-0'"
                        dir="rtl"
                    >
                        <p class="mb-2 text-xs font-bold text-slate-800 dark:text-neutral-100">وضعیت گام‌ها</p>
                        <div class="space-y-1.5">
                            <div
                                v-for="step in category.access.step_summary"
                                :key="step.index"
                                class="grid grid-cols-[1fr_auto] items-center gap-3 rounded-md bg-slate-50 px-2 py-1.5 text-[11px] dark:bg-neutral-900"
                            >
                                <span class="font-semibold text-slate-700 dark:text-neutral-200">گام {{ step.index }}</span>
                                <span class="text-slate-500 dark:text-neutral-400">
                                    {{ step.total_count }} کارت
                                    <template v-if="step.due_count > 0"> · {{ step.due_count }} آماده</template>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <span
                    v-if="category.access.unintroduced_count > 0"
                    class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold tabular-nums text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                    :title="`هنوز وارد برنامهٔ مطالعه نشده: ${category.access.unintroduced_count}`"
                    :aria-label="`${category.access.unintroduced_count} کارت هنوز وارد برنامهٔ مطالعه نشده`"
                >
                    <InboxArrowDownIcon class="h-4 w-4" aria-hidden="true" />
                    <span>{{ category.access.unintroduced_count }}</span>
                </span>
                <span
                    v-if="category.access.due_today_count > 0"
                    class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold tabular-nums text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                    :title="`آمادهٔ مطالعهٔ امروز: ${category.access.due_today_count}`"
                    :aria-label="`${category.access.due_today_count} کارت آمادهٔ مطالعهٔ امروز`"
                >
                    <CalendarDaysIcon class="h-4 w-4" aria-hidden="true" />
                    <span>{{ category.access.due_today_count }}</span>
                </span>
            </div>
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
