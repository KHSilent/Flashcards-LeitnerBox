<?php

namespace Tests\Feature;

use App\Models\CategoryAccess;
use App\Models\Flashcard;
use App\Models\FlashcardCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeitnerFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cards_move_through_leitner_steps(): void
    {
        Carbon::setTestNow('2026-07-07 12:00:00');

        $user = User::factory()->create();
        $category = FlashcardCategory::query()->create(['name' => 'Test Deck']);
        $access = CategoryAccess::query()->create([
            'user_id' => $user->id,
            'flashcard_category_id' => $category->id,
            'can_edit' => true,
            'steps' => [2, 4],
        ]);

        $flashcard = Flashcard::query()->create([
            'flashcard_category_id' => $category->id,
            'title' => 'A',
        ]);
        $flashcard->sides()->createMany([
            ['side_number' => 1, 'content' => 'A', 'images' => ['front.jpg'], 'audios' => ['front.mp3']],
            ['side_number' => 2, 'content' => 'B', 'images' => ['back.jpg'], 'audios' => ['back.mp3']],
        ]);

        $this->actingAs($user)
            ->postJson("/api/categories/{$category->id}/introduce", ['count' => 1])
            ->assertOk()
            ->assertJsonPath('introduced', 1);

        $studyCard = $access->studyCards()->firstOrFail();
        $this->assertSame(0, $studyCard->step_index);

        $this->actingAs($user)
            ->getJson("/api/categories/{$category->id}/study?step=0")
            ->assertOk()
            ->assertJsonPath('cards.0.flashcard.sides.0.images.0', url('/storage/front.jpg'))
            ->assertJsonPath('cards.0.flashcard.sides.0.audios.0', url('/storage/front.mp3'))
            ->assertJsonPath('cards.0.flashcard.sides.1.images.0', url('/storage/back.jpg'))
            ->assertJsonPath('cards.0.flashcard.sides.1.audios.0', url('/storage/back.mp3'));

        $this->actingAs($user)
            ->postJson("/api/categories/{$category->id}/study-cards/{$studyCard->id}/answer", ['action' => 'known'])
            ->assertOk()
            ->assertJsonPath('card.step_index', 1);

        $this->assertDatabaseHas('study_cards', [
            'id' => $studyCard->id,
            'step_index' => 1,
            'due_at' => '2026-07-09 12:00:00',
        ]);

        $this->actingAs($user)
            ->postJson("/api/categories/{$category->id}/study-cards/{$studyCard->id}/answer", ['action' => 'known'])
            ->assertOk()
            ->assertJsonPath('card.step_index', 2);

        $this->actingAs($user)
            ->putJson("/api/categories/{$category->id}/steps", ['steps' => [2]])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson("/api/categories/{$category->id}/study-cards/{$studyCard->id}/answer", ['action' => 'unknown'])
            ->assertOk()
            ->assertJsonPath('card.step_index', 0);
    }

    public function test_category_study_uses_only_direct_flashcards(): void
    {
        $user = User::factory()->create();
        $parent = FlashcardCategory::query()->create(['name' => 'Parent']);
        $child = FlashcardCategory::query()->create(['name' => 'Child', 'parent_id' => $parent->id]);
        $access = CategoryAccess::query()->create([
            'user_id' => $user->id,
            'flashcard_category_id' => $parent->id,
            'can_edit' => true,
            'steps' => CategoryAccess::DEFAULT_STEPS,
        ]);

        $parentCard = Flashcard::query()->create([
            'flashcard_category_id' => $parent->id,
            'title' => 'Direct',
        ]);
        $parentCard->sides()->create(['side_number' => 1, 'content' => 'Direct card']);

        $childCard = Flashcard::query()->create([
            'flashcard_category_id' => $child->id,
            'title' => 'Child',
        ]);
        $childCard->sides()->create(['side_number' => 1, 'content' => 'Child card']);

        $this->actingAs($user)
            ->getJson("/api/categories/{$parent->id}")
            ->assertOk()
            ->assertJsonPath('summaries.0.total_count', 1);

        $this->actingAs($user)
            ->postJson("/api/categories/{$parent->id}/introduce", ['count' => 10])
            ->assertOk()
            ->assertJsonPath('introduced', 1);

        $this->assertSame(1, $access->studyCards()->count());
        $this->assertDatabaseHas('study_cards', [
            'category_access_id' => $access->id,
            'flashcard_id' => $parentCard->id,
        ]);
        $this->assertDatabaseMissing('study_cards', [
            'category_access_id' => $access->id,
            'flashcard_id' => $childCard->id,
        ]);
    }

    public function test_step_cards_endpoint_lists_locked_cards_too(): void
    {
        Carbon::setTestNow('2026-07-07 12:00:00');

        $user = User::factory()->create();
        $category = FlashcardCategory::query()->create(['name' => 'Locked Step']);
        $access = CategoryAccess::query()->create([
            'user_id' => $user->id,
            'flashcard_category_id' => $category->id,
            'can_edit' => true,
            'steps' => [2],
        ]);

        $flashcard = Flashcard::query()->create([
            'flashcard_category_id' => $category->id,
            'title' => 'Future card',
        ]);
        $flashcard->sides()->create(['side_number' => 1, 'content' => 'Future']);

        $this->actingAs($user)
            ->postJson("/api/categories/{$category->id}/introduce", ['count' => 1])
            ->assertOk();

        $studyCard = $access->studyCards()->firstOrFail();

        $this->actingAs($user)
            ->postJson("/api/categories/{$category->id}/study-cards/{$studyCard->id}/answer", ['action' => 'known'])
            ->assertOk();

        $this->actingAs($user)
            ->getJson("/api/categories/{$category->id}/study?step=1")
            ->assertOk()
            ->assertJsonCount(0, 'cards');

        $this->actingAs($user)
            ->getJson("/api/categories/{$category->id}/study-cards?step=1")
            ->assertOk()
            ->assertJsonCount(1, 'cards')
            ->assertJsonPath('cards.0.flashcard.title', 'Future card')
            ->assertJsonPath('cards.0.due_at', '2026-07-09T12:00:00+00:00');

        Carbon::setTestNow();
    }
}
