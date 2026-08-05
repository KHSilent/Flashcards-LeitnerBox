<?php

namespace Tests\Feature;

use App\Models\CategoryAccess;
use App\Models\Flashcard;
use App\Models\FlashcardCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FlashcardManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_edit_access_can_manage_flashcards(): void
    {
        $user = User::factory()->create();
        $category = FlashcardCategory::query()->create(['name' => 'Editable']);

        CategoryAccess::query()->create([
            'user_id' => $user->id,
            'flashcard_category_id' => $category->id,
            'can_edit' => true,
            'steps' => CategoryAccess::DEFAULT_STEPS,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/categories/{$category->id}/flashcards", [
                'title' => 'Card title',
                'type' => 'english-active',
                'sides' => [
                    ['side_number' => 2, 'content' => 'Back', 'images' => ['image.jpg'], 'audios' => ['voice.mp3']],
                    ['side_number' => 1, 'content' => 'Front', 'images' => [], 'audios' => []],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('flashcard.title', 'Card title')
            ->assertJsonPath('flashcard.type', 'english-active')
            ->assertJsonPath('flashcard.sides.0.side_number', 1)
            ->assertJsonPath('flashcard.sides.1.content', 'Back');

        $flashcardId = $response->json('flashcard.id');

        $this->actingAs($user)
            ->getJson("/api/categories/{$category->id}/flashcards")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('flashcards.0.sides.1.images.0', url('/storage/image.jpg'))
            ->assertJsonPath('flashcards.0.sides.1.audios.0', url('/storage/voice.mp3'))
            ->assertJsonPath('flashcards.0.sides.1.raw_images.0', 'image.jpg')
            ->assertJsonPath('flashcards.0.sides.1.raw_audios.0', 'voice.mp3');

        $this->actingAs($user)
            ->putJson("/api/categories/{$category->id}/flashcards/{$flashcardId}", [
                'title' => 'Updated',
                'type' => 'english-passive',
                'sides' => [
                    ['side_number' => 5, 'content' => 'Only side', 'images' => [], 'audios' => []],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('flashcard.title', 'Updated')
            ->assertJsonPath('flashcard.type', 'english-passive')
            ->assertJsonPath('flashcard.sides.0.side_number', 5)
            ->assertJsonPath('flashcard.sides.0.content', 'Only side');

        $this->actingAs($user)
            ->deleteJson("/api/categories/{$category->id}/flashcards/{$flashcardId}")
            ->assertOk();

        $this->assertDatabaseMissing('flashcards', ['id' => $flashcardId]);
    }

    public function test_user_without_edit_access_cannot_manage_flashcards(): void
    {
        $user = User::factory()->create();
        $category = FlashcardCategory::query()->create(['name' => 'Read only']);

        CategoryAccess::query()->create([
            'user_id' => $user->id,
            'flashcard_category_id' => $category->id,
            'can_edit' => false,
            'steps' => CategoryAccess::DEFAULT_STEPS,
        ]);

        $flashcard = Flashcard::query()->create([
            'flashcard_category_id' => $category->id,
            'title' => 'Locked',
        ]);

        $this->actingAs($user)
            ->getJson("/api/categories/{$category->id}/flashcards")
            ->assertForbidden();

        $this->actingAs($user)
            ->deleteJson("/api/categories/{$category->id}/flashcards/{$flashcard->id}")
            ->assertForbidden();
    }

    public function test_user_can_upload_flashcard_media_files(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $category = FlashcardCategory::query()->create(['name' => 'Media']);

        CategoryAccess::query()->create([
            'user_id' => $user->id,
            'flashcard_category_id' => $category->id,
            'can_edit' => true,
            'steps' => CategoryAccess::DEFAULT_STEPS,
        ]);

        $response = $this->actingAs($user)
            ->post("/api/categories/{$category->id}/flashcards", [
                'title' => 'Media card',
                'sides' => [
                    [
                        'side_number' => 1,
                        'content' => 'Front',
                        'image_files' => [UploadedFile::fake()->image('front.jpg')],
                        'audio_files' => [UploadedFile::fake()->create('front.mp3', 12, 'audio/mpeg')],
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('flashcard.type', 'other');

        $this->assertStringContainsString('/storage/flashcards/images/', $response->json('flashcard.sides.0.images.0'));
        $this->assertStringContainsString('/storage/flashcards/audios/', $response->json('flashcard.sides.0.audios.0'));
        $this->assertStringStartsWith('flashcards/images/', $response->json('flashcard.sides.0.raw_images.0'));
        $this->assertStringStartsWith('flashcards/audios/', $response->json('flashcard.sides.0.raw_audios.0'));
        $this->assertStringStartsWith('flashcards/images/', Flashcard::query()->firstOrFail()->sides()->firstOrFail()->images[0]);
        $this->assertStringStartsWith('flashcards/audios/', Flashcard::query()->firstOrFail()->sides()->firstOrFail()->audios[0]);
    }

    public function test_user_can_smart_process_english_flashcard(): void
    {
        config(['services.openai.key' => 'test-key']);
        Storage::fake('public');
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/responses')) {
                return Http::response([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => json_encode([
                                'english_word' => 'Apple',
                                'persian_meaning' => 'سیب',
                                'tts_text' => 'Apple',
                                'image_prompt' => 'Simple educational anime image of an apple, no text.',
                                'side_1_content' => 'سیب',
                                'side_2_content' => "Apple (noun)\nAP-uhl\n\nUseful near words: fruit, snack, produce\n\nNone\n\nI ate an apple after lunch.\n\nNoun: countable noun.",
                            ], JSON_UNESCAPED_UNICODE),
                        ]],
                    ]],
                    'usage' => [
                        'input_tokens' => 10,
                        'output_tokens' => 20,
                    ],
                ]);
            }

            if (str_ends_with($request->url(), '/audio/speech')) {
                return Http::response('fake-mp3', 200, ['Content-Type' => 'audio/mpeg']);
            }

            if (str_ends_with($request->url(), '/images/generations')) {
                return Http::response([
                    'data' => [[
                        'b64_json' => base64_encode('fake-png'),
                    ]],
                    'usage' => ['total_tokens' => 3],
                ]);
            }

            return Http::response([], 404);
        });

        $user = User::factory()->create();
        $category = FlashcardCategory::query()->create(['name' => 'Smart']);

        CategoryAccess::query()->create([
            'user_id' => $user->id,
            'flashcard_category_id' => $category->id,
            'can_edit' => true,
            'steps' => CategoryAccess::DEFAULT_STEPS,
        ]);

        $flashcard = Flashcard::query()->create([
            'flashcard_category_id' => $category->id,
            'title' => 'سیب',
            'type' => 'english-active',
        ]);
        $flashcard->sides()->create(['side_number' => 1, 'content' => 'سیب']);

        $this->actingAs($user)
            ->postJson("/api/categories/{$category->id}/flashcards/{$flashcard->id}/smart-process")
            ->assertAccepted()
            ->assertJsonPath('flashcard.needs_ai_processing', true)
            ->assertJsonPath('flashcard.ai_processing_status', Flashcard::AI_STATUS_PENDING);

        $this->assertSame(0, Artisan::call('flashcards:process-smart', ['--limit' => 1]));

        $this->actingAs($user)
            ->getJson("/api/categories/{$category->id}/flashcards")
            ->assertOk()
            ->assertJsonPath('flashcards.0.needs_ai_processing', false)
            ->assertJsonPath('flashcards.0.ai_processing_status', Flashcard::AI_STATUS_DONE)
            ->assertJsonPath('flashcards.0.sides.0.content', 'سیب')
            ->assertJsonPath('flashcards.0.sides.1.content', "Apple (noun)\nAP-uhl\n\nUseful near words: fruit, snack, produce\n\nNone\n\nI ate an apple after lunch.\n\nNoun: countable noun.");

        $this->assertCount(2, $flashcard->refresh()->sides);
        $this->assertStringStartsWith('flashcards/ai/images/', $flashcard->sides()->where('side_number', 2)->first()->images[0]);
        $this->assertStringStartsWith('flashcards/ai/audios/', $flashcard->sides()->where('side_number', 2)->first()->audios[0]);
    }

    public function test_user_can_create_bulk_normal_flashcards(): void
    {
        $user = User::factory()->create();
        $category = FlashcardCategory::query()->create(['name' => 'Bulk normal']);

        CategoryAccess::query()->create([
            'user_id' => $user->id,
            'flashcard_category_id' => $category->id,
            'can_edit' => true,
            'steps' => CategoryAccess::DEFAULT_STEPS,
        ]);

        $this->actingAs($user)
            ->postJson("/api/categories/{$category->id}/flashcards/bulk-normal", [
                'type' => 'other',
                'sides' => [
                    ['side_number' => 1, 'content' => "Front A\nFront B"],
                    ['side_number' => 2, 'content' => "Back A\nBack B"],
                    ['side_number' => 3, 'content' => "Hint A\nHint B"],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('created', 2)
            ->assertJsonPath('flashcards.0.sides.0.content', 'Front A')
            ->assertJsonPath('flashcards.1.sides.2.content', 'Hint B');

        $this->assertSame(2, Flashcard::query()->where('flashcard_category_id', $category->id)->count());
    }

    public function test_bulk_normal_flashcards_preserve_empty_rows_as_missing_sides(): void
    {
        $user = User::factory()->create();
        $category = FlashcardCategory::query()->create(['name' => 'Bulk normal blanks']);

        CategoryAccess::query()->create([
            'user_id' => $user->id,
            'flashcard_category_id' => $category->id,
            'can_edit' => true,
            'steps' => CategoryAccess::DEFAULT_STEPS,
        ]);

        $this->actingAs($user)
            ->postJson("/api/categories/{$category->id}/flashcards/bulk-normal", [
                'type' => 'other',
                'sides' => [
                    ['side_number' => 1, 'content' => "SS\n\nTT"],
                    ['side_number' => 2, 'content' => "SSS\nRR\nTT"],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('created', 3)
            ->assertJsonPath('flashcards.0.sides.0.content', 'SS')
            ->assertJsonPath('flashcards.0.sides.1.content', 'SSS')
            ->assertJsonCount(1, 'flashcards.1.sides')
            ->assertJsonPath('flashcards.1.sides.0.side_number', 2)
            ->assertJsonPath('flashcards.1.sides.0.content', 'RR')
            ->assertJsonPath('flashcards.2.sides.0.content', 'TT')
            ->assertJsonPath('flashcards.2.sides.1.content', 'TT');
    }

    public function test_user_can_queue_bulk_smart_flashcards(): void
    {
        $user = User::factory()->create();
        $category = FlashcardCategory::query()->create(['name' => 'Bulk smart']);

        CategoryAccess::query()->create([
            'user_id' => $user->id,
            'flashcard_category_id' => $category->id,
            'can_edit' => true,
            'steps' => CategoryAccess::DEFAULT_STEPS,
        ]);

        $this->actingAs($user)
            ->postJson("/api/categories/{$category->id}/flashcards/bulk-smart", [
                'type' => 'english-passive',
                'items' => "apple\nbook",
            ])
            ->assertCreated()
            ->assertJsonPath('created', 2)
            ->assertJsonPath('flashcards.0.needs_ai_processing', true)
            ->assertJsonPath('flashcards.0.ai_processing_status', Flashcard::AI_STATUS_PENDING)
            ->assertJsonPath('flashcards.1.sides.0.content', 'book');

        $this->assertSame(2, Flashcard::query()
            ->where('flashcard_category_id', $category->id)
            ->where('needs_ai_processing', true)
            ->count());
    }

    public function test_user_with_edit_access_can_delete_empty_leaf_category(): void
    {
        $user = User::factory()->create();
        $category = FlashcardCategory::query()->create(['name' => 'Empty']);

        CategoryAccess::query()->create([
            'user_id' => $user->id,
            'flashcard_category_id' => $category->id,
            'can_edit' => true,
            'steps' => CategoryAccess::DEFAULT_STEPS,
        ]);

        $this->actingAs($user)
            ->getJson("/api/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('category.can_delete', true);

        $this->actingAs($user)
            ->deleteJson("/api/categories/{$category->id}")
            ->assertOk();

        $this->assertDatabaseMissing('flashcard_categories', ['id' => $category->id]);
    }

    public function test_user_can_create_private_category_with_edit_access(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/categories', [
                'name' => 'Private Deck',
            ])
            ->assertCreated()
            ->assertJsonPath('category.name', 'Private Deck')
            ->assertJsonPath('category.access.can_edit', true);

        $categoryId = $response->json('category.id');

        $this->assertDatabaseHas('category_accesses', [
            'user_id' => $user->id,
            'flashcard_category_id' => $categoryId,
            'can_edit' => true,
        ]);

        $this->assertDatabaseMissing('category_accesses', [
            'user_id' => $otherUser->id,
            'flashcard_category_id' => $categoryId,
        ]);
    }

    public function test_category_index_only_lists_categories_with_access(): void
    {
        $user = User::factory()->create();
        $parent = FlashcardCategory::query()->create(['name' => 'Parent Visible']);
        FlashcardCategory::query()->create(['name' => 'Child Hidden', 'parent_id' => $parent->id]);
        FlashcardCategory::query()->create(['name' => 'Other Hidden']);

        CategoryAccess::query()->create([
            'user_id' => $user->id,
            'flashcard_category_id' => $parent->id,
            'can_edit' => true,
            'steps' => CategoryAccess::DEFAULT_STEPS,
        ]);

        $this->actingAs($user)
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(1, 'categories')
            ->assertJsonPath('categories.0.name', 'Parent Visible');
    }

    public function test_access_all_categories_role_can_read_categories_without_edit_access(): void
    {
        $user = User::factory()->create([
            'roles' => ['accessAllCategories'],
        ]);
        $category = FlashcardCategory::query()->create(['name' => 'Shared Deck']);

        $this->actingAs($user)
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('categories.0.name', 'Shared Deck')
            ->assertJsonPath('categories.0.access.can_edit', false);

        $this->actingAs($user)
            ->getJson("/api/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('access.can_edit', false);

        $this->assertDatabaseHas('category_accesses', [
            'user_id' => $user->id,
            'flashcard_category_id' => $category->id,
            'can_edit' => false,
        ]);
    }
}
