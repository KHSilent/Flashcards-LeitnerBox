<?php

namespace App\Services;

use App\Models\CategoryAccess;
use App\Models\Flashcard;
use App\Models\StudyCard;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeitnerService
{
    public function summaries(CategoryAccess $access): array
    {
        $steps = $this->steps($access);
        $categoryId = $access->flashcard_category_id;
        $now = now();

        $unintroduced = Flashcard::query()
            ->where('flashcard_category_id', $categoryId)
            ->whereDoesntHave('studyCards', fn (Builder $query) => $query->where('category_access_id', $access->id))
            ->count();

        $items = [[
            'index' => null,
            'label' => 'No step',
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
                ->whereHas('flashcard', fn (Builder $query) => $query->where('flashcard_category_id', $categoryId))
                ->count();

            $due = StudyCard::query()
                ->where('category_access_id', $access->id)
                ->where('step_index', $index)
                ->whereHas('flashcard', fn (Builder $query) => $query->where('flashcard_category_id', $categoryId))
                ->when($index > 0, fn (Builder $query) => $query->where('due_at', '<=', $now))
                ->count();

            $items[] = [
                'index' => $index,
                'label' => 'Step '.$index,
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
        $categoryId = $access->flashcard_category_id;
        $now = now();

        return DB::transaction(function () use ($access, $categoryId, $count, $now) {
            $flashcards = Flashcard::query()
                ->where('flashcard_category_id', $categoryId)
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
        $categoryId = $access->flashcard_category_id;

        return StudyCard::query()
            ->with(['flashcard.sides'])
            ->where('category_access_id', $access->id)
            ->where('step_index', $stepIndex)
            ->whereHas('flashcard', fn (Builder $query) => $query->where('flashcard_category_id', $categoryId))
            ->when($stepIndex > 0, fn (Builder $query) => $query->where('due_at', '<=', now()))
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();
    }

    public function stepCards(CategoryAccess $access, int $stepIndex): Collection
    {
        $this->guardStepExists($access, $stepIndex);
        $categoryId = $access->flashcard_category_id;

        return StudyCard::query()
            ->with(['flashcard.sides'])
            ->where('category_access_id', $access->id)
            ->where('step_index', $stepIndex)
            ->whereHas('flashcard', fn (Builder $query) => $query->where('flashcard_category_id', $categoryId))
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
            default => throw ValidationException::withMessages(['action' => 'The study action is invalid.']),
        };

        if ($targetStep === null || $targetStep < 0 || $targetStep > $lastStep) {
            throw ValidationException::withMessages(['target_step' => 'The selected step is invalid.']);
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
            throw ValidationException::withMessages(['steps' => 'At least one timed step is required.']);
        }

        if (count($steps) < count($oldSteps) && array_slice($oldSteps, 0, count($steps)) !== $steps) {
            throw ValidationException::withMessages([
                'steps' => 'Only trailing steps may be removed to prevent cards from moving.',
            ]);
        }

        $highestOccupiedStep = StudyCard::query()
            ->where('category_access_id', $access->id)
            ->max('step_index') ?? 0;

        if ($highestOccupiedStep > count($steps)) {
            throw ValidationException::withMessages([
                'steps' => 'You cannot remove a step that contains cards.',
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
            throw ValidationException::withMessages(['step' => 'The selected step does not exist.']);
        }
    }
}
