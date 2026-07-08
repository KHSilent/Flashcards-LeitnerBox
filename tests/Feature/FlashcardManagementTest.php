<?php

namespace Tests\Feature;

use App\Models\CategoryAccess;
use App\Models\Flashcard;
use App\Models\FlashcardCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
                'sides' => [
                    ['side_number' => 2, 'content' => 'Back', 'images' => ['image.jpg'], 'audios' => ['voice.mp3']],
                    ['side_number' => 1, 'content' => 'Front', 'images' => [], 'audios' => []],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('flashcard.title', 'Card title')
            ->assertJsonPath('flashcard.sides.0.side_number', 1)
            ->assertJsonPath('flashcard.sides.1.content', 'Back');

        $flashcardId = $response->json('flashcard.id');

        $this->actingAs($user)
            ->getJson("/api/categories/{$category->id}/flashcards")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($user)
            ->putJson("/api/categories/{$category->id}/flashcards/{$flashcardId}", [
                'title' => 'Updated',
                'sides' => [
                    ['side_number' => 5, 'content' => 'Only side', 'images' => [], 'audios' => []],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('flashcard.title', 'Updated')
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
            ->assertCreated();

        $this->assertStringStartsWith('/uploads/flashcards/images/', $response->json('flashcard.sides.0.images.0'));
        $this->assertStringStartsWith('/uploads/flashcards/audios/', $response->json('flashcard.sides.0.audios.0'));
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
}
