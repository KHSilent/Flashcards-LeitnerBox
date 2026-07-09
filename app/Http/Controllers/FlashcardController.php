<?php

namespace App\Http\Controllers;

use App\Models\CategoryAccess;
use App\Models\Flashcard;
use App\Models\FlashcardCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class FlashcardController extends Controller
{
    public function index(Request $request, FlashcardCategory $category): JsonResponse
    {
        $this->editableAccess($request, $category);

        $perPage = min(max((int) $request->integer('per_page', 10), 5), 50);
        $flashcards = Flashcard::query()
            ->with('sides')
            ->where('flashcard_category_id', $category->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'flashcards' => collect($flashcards->items())->map(fn (Flashcard $flashcard) => $this->payload($flashcard))->values(),
            'meta' => [
                'current_page' => $flashcards->currentPage(),
                'last_page' => $flashcards->lastPage(),
                'per_page' => $flashcards->perPage(),
                'total' => $flashcards->total(),
                'from' => $flashcards->firstItem(),
                'to' => $flashcards->lastItem(),
            ],
        ]);
    }

    public function store(Request $request, FlashcardCategory $category): JsonResponse
    {
        $this->editableAccess($request, $category);
        $data = $this->validated($request);

        $flashcard = DB::transaction(function () use ($category, $data) {
            $flashcard = Flashcard::query()->create([
                'flashcard_category_id' => $category->id,
                'title' => $data['title'] ?? null,
                'type' => $data['type'] ?? Flashcard::DEFAULT_TYPE,
            ]);

            $this->syncSides($flashcard, $data['sides']);

            return $flashcard->load('sides');
        });

        return response()->json(['flashcard' => $this->payload($flashcard)], 201);
    }

    public function update(Request $request, FlashcardCategory $category, Flashcard $flashcard): JsonResponse
    {
        $this->editableAccess($request, $category);
        $this->guardFlashcardBelongsToCategory($category, $flashcard);
        $data = $this->validated($request);

        $flashcard = DB::transaction(function () use ($flashcard, $data) {
            $flashcard->forceFill([
                'title' => $data['title'] ?? null,
                'type' => $data['type'] ?? Flashcard::DEFAULT_TYPE,
            ])->save();

            $this->syncSides($flashcard, $data['sides']);

            return $flashcard->refresh()->load('sides');
        });

        return response()->json(['flashcard' => $this->payload($flashcard)]);
    }

    public function destroy(Request $request, FlashcardCategory $category, Flashcard $flashcard): JsonResponse
    {
        $this->editableAccess($request, $category);
        $this->guardFlashcardBelongsToCategory($category, $flashcard);

        $flashcard->delete();

        return response()->json(['ok' => true]);
    }

    private function editableAccess(Request $request, FlashcardCategory $category): CategoryAccess
    {
        $access = CategoryAccess::query()
            ->where('user_id', $request->user()->id)
            ->where('flashcard_category_id', $category->id)
            ->firstOrFail();

        abort_unless($access->can_edit, 403);

        return $access;
    }

    private function guardFlashcardBelongsToCategory(FlashcardCategory $category, Flashcard $flashcard): void
    {
        abort_unless($flashcard->flashcard_category_id === $category->id, 404);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(Flashcard::TYPES)],
            'sides' => ['required', 'array', 'min:1', 'max:12'],
            'sides.*.content' => ['required', 'string'],
            'sides.*.side_number' => ['required', 'integer', 'min:1', 'distinct'],
            'sides.*.images' => ['nullable', 'array'],
            'sides.*.images.*' => ['nullable', 'string', 'max:2048'],
            'sides.*.image_files' => ['nullable', 'array'],
            'sides.*.image_files.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:10240'],
            'sides.*.audios' => ['nullable', 'array'],
            'sides.*.audios.*' => ['nullable', 'string', 'max:2048'],
            'sides.*.audio_files' => ['nullable', 'array'],
            'sides.*.audio_files.*' => ['file', 'mimes:mp3,wav,ogg,m4a,aac,webm', 'max:51200'],
        ]);
    }

    private function syncSides(Flashcard $flashcard, array $sides): void
    {
        $flashcard->sides()->delete();

        foreach ($sides as $side) {
            $flashcard->sides()->create([
                'side_number' => (int) $side['side_number'],
                'content' => $side['content'],
                'images' => [
                    ...array_values(array_filter($side['images'] ?? [])),
                    ...$this->storeUploads($side['image_files'] ?? [], 'images'),
                ],
                'audios' => [
                    ...array_values(array_filter($side['audios'] ?? [])),
                    ...$this->storeUploads($side['audio_files'] ?? [], 'audios'),
                ],
            ]);
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, string>
     */
    private function storeUploads(array $files, string $type): array
    {
        $target = public_path("uploads/flashcards/{$type}");
        File::ensureDirectoryExists($target);

        return collect($files)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->map(function (UploadedFile $file) use ($target, $type) {
                $name = $file->hashName();
                $file->move($target, $name);

                return "/uploads/flashcards/{$type}/{$name}";
            })
            ->values()
            ->all();
    }

    private function payload(Flashcard $flashcard): array
    {
        return [
            'id' => $flashcard->id,
            'title' => $flashcard->title,
            'type' => $flashcard->type ?: Flashcard::DEFAULT_TYPE,
            'created_at' => $flashcard->created_at?->toIso8601String(),
            'sides' => $flashcard->sides->map(fn ($side) => [
                'id' => $side->id,
                'side_number' => $side->side_number,
                'content' => $side->content,
                'images' => $side->images ?: [],
                'audios' => $side->audios ?: [],
            ])->values(),
        ];
    }
}
