<?php

namespace App\Services;

use App\Models\CategoryAccess;
use App\Models\Flashcard;
use App\Models\FlashcardCategory;
use App\Models\StudyCard;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeitnerService
{
    public function categoryIdsWithDescendants(FlashcardCategory $category): array
    {
        $ids = [$category->id];
        $children = FlashcardCategory::query()
            ->where('parent_id', $category->id)
            ->get(['id', 'parent_id']);

        foreach ($children as $child) {
            array_push($ids, ...$this->categoryIdsWithDescendants($child));
        }

        return array_values(array_unique($ids));
    }

    public function summaries(CategoryAccess $access): array
    {
        $steps = $this->steps($access);
        $categoryIds = $this->categoryIdsWithDescendants($access->category);
        $now = now();

        $unintroduced = Flashcard::query()
            ->whereIn('flashcard_category_id', $categoryIds)
            ->whereDoesntHave('studyCards', fn (Builder $query) => $query->where('category_access_id', $access->id))
            ->count();

        $items = [[
            'index' => null,
            'label' => 'بدون گام',
            'delay_days' => null,
            'total_count' => $unintroduced,
            'due_count' => $unintroduced,
            'locked_count' => 0,
            'is_final' => false,
        ]];

        for ($index = 0; $index <= count($steps); $index++) {
            $total = StudyCard::query()
                ->where('category_access_id', $access->id)
                ->where('step_index', $index)
                ->whereHas('flashcard', fn (Builder $query) => $query->whereIn('flashcard_category_id', $categoryIds))
                ->count();

            $due = StudyCard::query()
                ->where('category_access_id', $access->id)
                ->where('step_index', $index)
                ->whereHas('flashcard', fn (Builder $query) => $query->whereIn('flashcard_category_id', $categoryIds))
                ->when($index > 0, fn (Builder $query) => $query->where('due_at', '<=', $now))
                ->count();

            $items[] = [
                'index' => $index,
                'label' => 'گام '.$index,
                'delay_days' => $index === 0 ? 0 : $steps[$index - 1],
                'total_count' => $total,
                'due_count' => $due,
                'locked_count' => max(0, $total - $due),
                'is_final' => $index === count($steps),
            ];
        }

        return $items;
    }

    public function introduce(CategoryAccess $access, int $count): int
    {
        $categoryIds = $this->categoryIdsWithDescendants($access->category);
        $now = now();

        return DB::transaction(function () use ($access, $categoryIds, $count, $now) {
            $flashcards = Flashcard::query()
                ->whereIn('flashcard_category_id', $categoryIds)
                ->whereDoesntHave('studyCards', fn (Builder $query) => $query->where('category_access_id', $access->id))
                ->orderBy('id')
                ->limit($count)
                ->pluck('id');

            foreach ($flashcards as $flashcardId) {
                StudyCard::query()->create([
                    'category_access_id' => $access->id,
                    'flashcard_id' => $flashcardId,
                    'step_index' => 0,
                    'entered_at' => $now,
                    'due_at' => $now,
                ]);
            }

            return $flashcards->count();
        });
    }

    public function dueCards(CategoryAccess $access, int $stepIndex): Collection
    {
        $this->guardStepExists($access, $stepIndex);
        $categoryIds = $this->categoryIdsWithDescendants($access->category);

        return StudyCard::query()
            ->with(['flashcard.sides'])
            ->where('category_access_id', $access->id)
            ->where('step_index', $stepIndex)
            ->whereHas('flashcard', fn (Builder $query) => $query->whereIn('flashcard_category_id', $categoryIds))
            ->when($stepIndex > 0, fn (Builder $query) => $query->where('due_at', '<=', now()))
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();
    }

    public function moveCard(CategoryAccess $access, StudyCard $studyCard, string $action, ?int $customStep = null): StudyCard
    {
        if ($studyCard->category_access_id !== $access->id) {
            abort(404);
        }

        $steps = $this->steps($access);
        $lastStep = count($steps);

        $targetStep = match ($action) {
            'known' => min($studyCard->step_index + 1, $lastStep),
            'unknown' => 0,
            'mastered' => $lastStep,
            'custom' => $customStep,
            default => throw ValidationException::withMessages(['action' => 'عملیات مطالعه نامعتبر است.']),
        };

        if ($targetStep === null || $targetStep < 0 || $targetStep > $lastStep) {
            throw ValidationException::withMessages(['target_step' => 'گام انتخابی معتبر نیست.']);
        }

        $now = now();
        $studyCard->forceFill([
            'step_index' => $targetStep,
            'entered_at' => $now,
            'due_at' => $this->dueAt($steps, $targetStep, $now),
        ])->save();

        return $studyCard->refresh()->load('flashcard.sides');
    }

    public function updateSteps(CategoryAccess $access, array $steps): CategoryAccess
    {
        $oldSteps = $this->steps($access);
        $steps = collect($steps)
            ->map(fn ($step) => (int) $step)
            ->filter(fn ($step) => $step > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($steps === []) {
            throw ValidationException::withMessages(['steps' => 'حداقل یک گام زمان‌دار لازم است.']);
        }

        if (count($steps) < count($oldSteps) && array_slice($oldSteps, 0, count($steps)) !== $steps) {
            throw ValidationException::withMessages([
                'steps' => 'برای جلوگیری از جابه‌جایی کارت‌ها، فقط گام‌های انتهایی قابل حذف هستند.',
            ]);
        }

        $highestOccupiedStep = StudyCard::query()
            ->where('category_access_id', $access->id)
            ->max('step_index') ?? 0;

        if ($highestOccupiedStep > count($steps)) {
            throw ValidationException::withMessages([
                'steps' => 'نمی‌توانید گامی را حذف کنید که کارت داخل آن وجود دارد.',
            ]);
        }

        $access->forceFill(['steps' => $steps])->save();

        return $access->refresh();
    }

    public function steps(CategoryAccess $access): array
    {
        return array_values($access->steps ?: CategoryAccess::DEFAULT_STEPS);
    }

    private function dueAt(array $steps, int $targetStep, CarbonInterface $now): CarbonInterface
    {
        if ($targetStep === 0) {
            return $now;
        }

        return Carbon::instance($now)->copy()->addDays($steps[$targetStep - 1]);
    }

    private function guardStepExists(CategoryAccess $access, int $stepIndex): void
    {
        if ($stepIndex < 0 || $stepIndex > count($this->steps($access))) {
            throw ValidationException::withMessages(['step' => 'گام انتخابی وجود ندارد.']);
        }
    }
}
