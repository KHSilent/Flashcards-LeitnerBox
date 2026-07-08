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
            ->assertJsonPath('cards.0.flashcard.sides.0.images.0', 'front.jpg')
            ->assertJsonPath('cards.0.flashcard.sides.0.audios.0', 'front.mp3')
            ->assertJsonPath('cards.0.flashcard.sides.1.images.0', 'back.jpg')
            ->assertJsonPath('cards.0.flashcard.sides.1.audios.0', 'back.mp3');

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
}
