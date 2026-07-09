<?php

namespace App\Http\Controllers;

use App\Models\CategoryAccess;
use App\Models\FlashcardCategory;
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
            ->where('user_id', $request->user()->id)
            ->get()
            ->keyBy('flashcard_category_id');

        $canAccessAll = $request->user()->hasRole('accessAllCategories');

        $categoriesQuery = FlashcardCategory::query()
            ->withCount('flashcards')
            ->orderBy('name');

        if (! $canAccessAll) {
            $categoriesQuery->whereIn('id', $accesses->keys());
        }

        $categories = $categoriesQuery
            ->get()
            ->map(fn (FlashcardCategory $category) => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'name' => $category->name,
                'flashcards_count' => $category->flashcards_count,
                'access' => $this->accessPayloadForIndex($accesses->get($category->id), $canAccessAll),
            ]);

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

    private function accessPayloadForIndex(?CategoryAccess $access, bool $canAccessAll): ?array
    {
        if ($access) {
            return [
                'can_edit' => $access->can_edit,
                'steps' => $this->leitner->steps($access),
            ];
        }

        if (! $canAccessAll) {
            return null;
        }

        return [
            'can_edit' => false,
            'steps' => CategoryAccess::DEFAULT_STEPS,
        ];
    }
}
