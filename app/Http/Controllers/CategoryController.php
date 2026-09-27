<?php

namespace App\Http\Controllers;

use App\Models\CategoryAccess;
use App\Models\FlashcardCategory;
use App\Models\StudyCard;
use App\Services\LeitnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct(private readonly LeitnerService $leitner) {}

    public function index(Request $request): JsonResponse
    {
        $accesses = CategoryAccess::query()
            ->with('category')
            ->withCount([
                'studyCards as due_today_count' => fn ($query) => $query->where(
                    fn ($query) => $query->where('step_index', 0)->orWhere('due_at', '<=', now()),
                ),
                'studyCards as introduced_count',
            ])
            ->withSum('studyCards as progress_step_sum', 'step_index')
            ->where('user_id', $request->user()->id)
            ->get()
            ->keyBy('flashcard_category_id');

        $stepCountsByAccess = StudyCard::query()
            ->select(['category_access_id', 'step_index'])
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw(
                'SUM(CASE WHEN step_index = 0 OR due_at <= ? THEN 1 ELSE 0 END) as due_count',
                [now()],
            )
            ->whereIn('category_access_id', $accesses->pluck('id'))
            ->groupBy('category_access_id', 'step_index')
            ->get()
            ->groupBy('category_access_id');

        $canAccessAll = $request->user()->hasRole('accessAllCategories');

        $categoriesQuery = FlashcardCategory::query()
            ->withCount('flashcards')
            ->orderBy('name');

        if (! $canAccessAll) {
            $categoriesQuery->whereIn('id', $accesses->keys());
        }

        $categories = $categoriesQuery
            ->get()
            ->map(function (FlashcardCategory $category) use ($accesses, $canAccessAll, $stepCountsByAccess) {
                $access = $accesses->get($category->id);

                return [
                    'id' => $category->id,
                    'parent_id' => $category->parent_id,
                    'name' => $category->name,
                    'flashcards_count' => $category->flashcards_count,
                    'access' => $this->accessPayloadForIndex(
                        $access,
                        $canAccessAll,
                        $category->flashcards_count,
                        $stepCountsByAccess->get($access?->id, collect())->all(),
                    ),
                ];
            });

        return response()->json(['categories' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('flashcard_categories', 'name')->where(fn ($query) => $request->filled('parent_id')
                    ? $query->where('parent_id', $request->input('parent_id'))
                    : $query->whereNull('parent_id')),
            ],
            'parent_id' => ['nullable', 'integer', 'exists:flashcard_categories,id'],
        ]);

        if (! empty($data['parent_id'])) {
            $parent = FlashcardCategory::query()->findOrFail($data['parent_id']);
            $parentAccess = $this->accessFor($request, $parent);
            abort_unless($parentAccess->can_edit, 403);
        }

        $category = DB::transaction(function () use ($request, $data) {
            $category = FlashcardCategory::query()->create([
                'parent_id' => $data['parent_id'] ?? null,
                'name' => $data['name'],
            ]);

            CategoryAccess::query()->create([
                'user_id' => $request->user()->id,
                'flashcard_category_id' => $category->id,
                'can_edit' => true,
                'steps' => CategoryAccess::DEFAULT_STEPS,
            ]);

            return $category;
        });

        return response()->json([
            'category' => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'name' => $category->name,
                'flashcards_count' => 0,
                'access' => [
                    'can_edit' => true,
                    'steps' => CategoryAccess::DEFAULT_STEPS,
                    'progress_percent' => 0,
                    'due_today_count' => 0,
                    'unintroduced_count' => 0,
                    'step_summary' => $this->stepSummary(CategoryAccess::DEFAULT_STEPS),
                ],
            ],
        ], 201);
    }

    public function show(Request $request, FlashcardCategory $category): JsonResponse
    {
        $access = $this->accessFor($request, $category);
        $category->load('parent')->loadCount(['children', 'flashcards']);

        return response()->json([
            'category' => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'name' => $category->name,
                'parent_name' => $category->parent?->name,
                'children_count' => $category->children_count,
                'flashcards_count' => $category->flashcards_count,
                'can_delete' => $access->can_edit && $category->children_count === 0 && $category->flashcards_count === 0,
            ],
            'access' => [
                'id' => $access->id,
                'can_edit' => $access->can_edit,
                'steps' => $this->leitner->steps($access),
            ],
            'summaries' => $this->leitner->summaries($access),
        ]);
    }

    public function destroy(Request $request, FlashcardCategory $category): JsonResponse
    {
        $access = $this->accessFor($request, $category);
        abort_unless($access->can_edit, 403);

        $category->loadCount(['children', 'flashcards']);

        abort_if($category->children_count > 0 || $category->flashcards_count > 0, 422, 'این دسته زیرمجموعه یا کارت دارد و قابل حذف نیست.');

        $category->delete();

        return response()->json(['ok' => true]);
    }

    public function introduce(Request $request, FlashcardCategory $category): JsonResponse
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $access = $this->accessFor($request, $category);
        $introduced = $this->leitner->introduce($access, $data['count']);

        return response()->json([
            'introduced' => $introduced,
            'summaries' => $this->leitner->summaries($access->refresh()),
        ]);
    }

    public function updateSteps(Request $request, FlashcardCategory $category): JsonResponse
    {
        $data = $request->validate([
            'steps' => ['required', 'array', 'min:1', 'max:12'],
            'steps.*' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $access = $this->accessFor($request, $category);
        $access = $this->leitner->updateSteps($access, $data['steps']);

        return response()->json([
            'access' => [
                'id' => $access->id,
                'can_edit' => $access->can_edit,
                'steps' => $this->leitner->steps($access),
            ],
            'summaries' => $this->leitner->summaries($access),
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

    private function accessPayloadForIndex(
        ?CategoryAccess $access,
        bool $canAccessAll,
        int $flashcardsCount,
        array $stepCounts = [],
    ): ?array {
        if ($access) {
            $steps = $this->leitner->steps($access);
            $maximumProgress = $flashcardsCount * count($steps);
            $progress = $maximumProgress > 0
                ? (int) round(min(1, (int) $access->progress_step_sum / $maximumProgress) * 100)
                : 0;

            return [
                'can_edit' => $access->can_edit,
                'steps' => $steps,
                'progress_percent' => $progress,
                'due_today_count' => (int) $access->due_today_count,
                'unintroduced_count' => max(0, $flashcardsCount - (int) $access->introduced_count),
                'step_summary' => $this->stepSummary($steps, $stepCounts),
            ];
        }

        if (! $canAccessAll) {
            return null;
        }

        return [
            'can_edit' => false,
            'steps' => CategoryAccess::DEFAULT_STEPS,
            'progress_percent' => 0,
            'due_today_count' => 0,
            'unintroduced_count' => $flashcardsCount,
            'step_summary' => $this->stepSummary(CategoryAccess::DEFAULT_STEPS),
        ];
    }

    private function stepSummary(array $steps, array $stepCounts = []): array
    {
        $countsByStep = collect($stepCounts)->keyBy(fn (StudyCard $count) => (int) $count->step_index);

        return collect(range(0, count($steps)))
            ->map(function (int $index) use ($countsByStep) {
                $count = $countsByStep->get($index);

                return [
                    'index' => $index,
                    'total_count' => (int) ($count?->total_count ?? 0),
                    'due_count' => (int) ($count?->due_count ?? 0),
                ];
            })
            ->all();
    }
}
