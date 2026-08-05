<?php

namespace App\Http\Controllers;

use App\Models\CategoryAccess;
use App\Models\Flashcard;
use App\Models\FlashcardCategory;
use App\Support\FlashcardMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    public function bulkSmart(Request $request, FlashcardCategory $category): JsonResponse
    {
        $this->editableAccess($request, $category);

        $data = $request->validate([
            'type' => ['required', Rule::in([Flashcard::TYPE_ENGLISH_ACTIVE, Flashcard::TYPE_ENGLISH_PASSIVE])],
            'items' => ['required', 'string'],
        ]);
        $items = $this->lineList($data['items']);

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'حداقل یک خط وارد کنید.']);
        }

        $flashcards = DB::transaction(function () use ($category, $data, $items) {
            return collect($items)->map(function (string $item) use ($category, $data) {
                $flashcard = Flashcard::query()->create([
                    'flashcard_category_id' => $category->id,
                    'title' => $item,
                    'type' => $data['type'],
                    'needs_ai_processing' => true,
                    'ai_processing_status' => Flashcard::AI_STATUS_PENDING,
                    'ai_processing_requested_at' => now(),
                ]);

                $flashcard->sides()->create([
                    'side_number' => 1,
                    'content' => $item,
                    'images' => [],
                    'audios' => [],
                ]);

                return $flashcard->load('sides');
            });
        });

        return response()->json([
            'created' => $flashcards->count(),
            'flashcards' => $flashcards->map(fn (Flashcard $flashcard) => $this->payload($flashcard))->values(),
        ], 201);
    }

    public function bulkNormal(Request $request, FlashcardCategory $category): JsonResponse
    {
        $this->editableAccess($request, $category);

        $data = $request->validate([
            'type' => ['required', Rule::in(Flashcard::TYPES)],
            'sides' => ['required', 'array', 'min:2', 'max:12'],
            'sides.*.side_number' => ['required', 'integer', 'min:1', 'distinct'],
            'sides.*.content' => ['nullable', 'string'],
        ]);

        $sideLines = collect($data['sides'])->map(fn (array $side) => [
            'side_number' => (int) $side['side_number'],
            'lines' => $this->lineRows($side['content'] ?? ''),
        ])->values();

        $cardCount = $sideLines->pluck('lines')->map(fn (array $lines) => count($lines))->max() ?: 0;

        if ($cardCount < 1) {
            throw ValidationException::withMessages([
                'sides' => 'حداقل یک خط برای ساخت کارت وارد کنید.',
            ]);
        }

        $sideLines = $sideLines->map(fn (array $side) => [
            'side_number' => $side['side_number'],
            'lines' => array_pad($side['lines'], $cardCount, ''),
        ]);
        $cardIndexes = collect(range(0, $cardCount - 1))
            ->filter(fn (int $index) => $sideLines->contains(fn (array $side) => trim($side['lines'][$index]) !== ''))
            ->values();

        if ($cardIndexes->isEmpty()) {
            throw ValidationException::withMessages([
                'sides' => 'حداقل یک کارت با متن وارد کنید.',
            ]);
        }

        $flashcards = DB::transaction(function () use ($category, $data, $sideLines, $cardIndexes) {
            return $cardIndexes->map(function (int $index) use ($category, $data, $sideLines) {
                $firstLine = $sideLines
                    ->map(fn (array $side) => trim($side['lines'][$index]))
                    ->first(fn (string $line) => $line !== '');
                $flashcard = Flashcard::query()->create([
                    'flashcard_category_id' => $category->id,
                    'title' => $firstLine,
                    'type' => $data['type'],
                ]);

                foreach ($sideLines as $side) {
                    $content = trim($side['lines'][$index]);

                    if ($content === '') {
                        continue;
                    }

                    $flashcard->sides()->create([
                        'side_number' => $side['side_number'],
                        'content' => $content,
                        'images' => [],
                        'audios' => [],
                    ]);
                }

                return $flashcard->load('sides');
            });
        });

        return response()->json([
            'created' => $flashcards->count(),
            'flashcards' => $flashcards->map(fn (Flashcard $flashcard) => $this->payload($flashcard))->values(),
        ], 201);
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

    public function smartProcess(Request $request, FlashcardCategory $category, Flashcard $flashcard): JsonResponse
    {
        $this->editableAccess($request, $category);
        $this->guardFlashcardBelongsToCategory($category, $flashcard);

        if (! $flashcard->supportsAiProcessing()) {
            throw ValidationException::withMessages([
                'type' => 'پردازش هوشمند فقط برای کارت‌های انگلیسی فعال و انگلیسی پسیو فعال است.',
            ]);
        }

        $flashcard->forceFill([
            'needs_ai_processing' => true,
            'ai_processing_status' => Flashcard::AI_STATUS_PENDING,
            'ai_processing_requested_at' => now(),
            'ai_processed_at' => null,
            'ai_processing_failed_at' => null,
            'ai_processing_error' => null,
        ])->save();

        return response()->json(['flashcard' => $this->payload($flashcard->refresh()->load('sides'))], 202);
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
                    ...FlashcardMedia::normalizeMany($side['images'] ?? []),
                    ...$this->storeUploads($side['image_files'] ?? [], 'images'),
                ],
                'audios' => [
                    ...FlashcardMedia::normalizeMany($side['audios'] ?? []),
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
        return collect($files)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file) => $file->storePublicly("flashcards/{$type}", 'public'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function lineList(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function lineRows(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];

        return collect($lines)
            ->map(fn ($line) => trim((string) $line))
            ->values()
            ->all();
    }

    private function payload(Flashcard $flashcard): array
    {
        return [
            'id' => $flashcard->id,
            'title' => $flashcard->title,
            'type' => $flashcard->type ?: Flashcard::DEFAULT_TYPE,
            'needs_ai_processing' => (bool) $flashcard->needs_ai_processing,
            'ai_processing_status' => $flashcard->ai_processing_status,
            'ai_processing_error' => $flashcard->ai_processing_error,
            'ai_processing_requested_at' => $flashcard->ai_processing_requested_at?->toIso8601String(),
            'ai_processed_at' => $flashcard->ai_processed_at?->toIso8601String(),
            'ai_processing_failed_at' => $flashcard->ai_processing_failed_at?->toIso8601String(),
            'created_at' => $flashcard->created_at?->toIso8601String(),
            'sides' => $flashcard->sides->map(fn ($side) => [
                'id' => $side->id,
                'side_number' => $side->side_number,
                'content' => $side->content,
                'images' => FlashcardMedia::urls($side->images),
                'audios' => FlashcardMedia::urls($side->audios),
                'raw_images' => array_values($side->images ?: []),
                'raw_audios' => array_values($side->audios ?: []),
            ])->values(),
        ];
    }
}
