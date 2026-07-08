<?php

namespace App\Http\Controllers;

use App\Models\CategoryAccess;
use App\Models\FlashcardCategory;
use App\Services\LeitnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $categories = FlashcardCategory::query()
            ->withCount('flashcards')
            ->orderBy('name')
            ->get()
            ->map(fn (FlashcardCategory $category) => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'name' => $category->name,
                'flashcards_count' => $category->flashcards_count,
                'access' => $accesses->has($category->id) ? [
                    'can_edit' => $accesses[$category->id]->can_edit,
                    'steps' => $this->leitner->steps($accesses[$category->id]),
                ] : null,
            ]);

        return response()->json(['categories' => $categories]);
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
        return CategoryAccess::query()
            ->where('user_id', $request->user()->id)
            ->where('flashcard_category_id', $category->id)
            ->firstOrFail();
    }
}
