<?php

namespace App\Http\Controllers;

use App\Models\CategoryAccess;
use App\Models\FlashcardCategory;
use App\Models\StudyCard;
use App\Services\LeitnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudyController extends Controller
{
    public function __construct(private readonly LeitnerService $leitner) {}

    public function index(Request $request, FlashcardCategory $category): JsonResponse
    {
        $data = $request->validate([
            'step' => ['required', 'integer', 'min:0'],
        ]);

        $access = $this->accessFor($request, $category);
        $cards = $this->leitner->dueCards($access, $data['step'])->map(fn (StudyCard $studyCard) => $this->cardPayload($studyCard));

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
            'access' => [
                'steps' => $this->leitner->steps($access),
            ],
            'step' => $data['step'],
            'cards' => $cards,
        ]);
    }

    public function stepCards(Request $request, FlashcardCategory $category): JsonResponse
    {
        $data = $request->validate([
            'step' => ['required', 'integer', 'min:0'],
        ]);

        $access = $this->accessFor($request, $category);
        $cards = $this->leitner->stepCards($access, $data['step'])->map(fn (StudyCard $studyCard) => $this->cardPayload($studyCard));

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
            'access' => [
                'steps' => $this->leitner->steps($access),
            ],
            'step' => $data['step'],
            'cards' => $cards,
        ]);
    }

    public function answer(Request $request, FlashcardCategory $category, StudyCard $studyCard): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['known', 'unknown', 'mastered', 'custom'])],
            'target_step' => ['nullable', 'integer', 'min:0'],
        ]);

        $access = $this->accessFor($request, $category);
        $studyCard = $this->leitner->moveCard($access, $studyCard, $data['action'], $data['target_step'] ?? null);

        return response()->json([
            'card' => $this->cardPayload($studyCard),
            'summaries' => $this->leitner->summaries($access->refresh()),
        ]);
    }

    private function accessFor(Request $request, FlashcardCategory $category): CategoryAccess
    {
        $access = CategoryAccess::query()
            ->where('user_id', $request->user()->id)
            ->where('flashcard_category_id', $category->id)
            ->first();

        if ($access) {
            return $access;
        }

        abort_unless($request->user()->hasRole('accessAllCategories'), 404);

        return CategoryAccess::query()->firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'flashcard_category_id' => $category->id,
            ],
            [
                'can_edit' => false,
                'steps' => CategoryAccess::DEFAULT_STEPS,
            ],
        );
    }

    private function cardPayload(StudyCard $studyCard): array
    {
        return [
            'study_card_id' => $studyCard->id,
            'step_index' => $studyCard->step_index,
            'entered_at' => $studyCard->entered_at?->toIso8601String(),
            'due_at' => $studyCard->due_at?->toIso8601String(),
            'flashcard' => [
                'id' => $studyCard->flashcard->id,
                'title' => $studyCard->flashcard->title,
                'sides' => $studyCard->flashcard->sides->map(fn ($side) => [
                    'id' => $side->id,
                    'side_number' => $side->side_number,
                    'content' => $side->content,
                    'images' => $side->images ?: [],
                    'audios' => $side->audios ?: [],
                ]),
            ],
        ];
    }
}
