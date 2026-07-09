<?php

namespace Tests\Feature;

use App\Models\CategoryAccess;
use App\Models\Flashcard;
use App\Models\FlashcardCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_and_password(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'roles' => [],
        ]);

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('user.email', 'new@example.com');

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_manage_user_role_can_manage_users(): void
    {
        $manager = User::factory()->create([
            'is_active' => true,
            'roles' => ['manageUser'],
        ]);

        $this->actingAs($manager)
            ->postJson('/api/users', [
                'name' => 'Member',
                'email' => 'member@example.com',
                'password' => 'password123',
                'is_active' => true,
                'roles' => [],
            ])
            ->assertCreated()
            ->assertJsonPath('user.email', 'member@example.com');

        $member = User::query()->where('email', 'member@example.com')->firstOrFail();

        $this->actingAs($manager)
            ->putJson("/api/users/{$member->id}", [
                'name' => 'Member',
                'email' => 'member@example.com',
                'is_active' => false,
                'roles' => ['manageUser'],
            ])
            ->assertOk()
            ->assertJsonPath('user.is_active', false)
            ->assertJsonPath('user.roles.0', 'manageUser');

        $this->actingAs($manager)
            ->deleteJson("/api/users/{$member->id}")
            ->assertOk();
    }

    public function test_user_without_manage_user_role_cannot_manage_users(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'roles' => [],
        ]);

        $this->actingAs($user)
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_manager_can_edit_user_category_accesses(): void
    {
        $manager = User::factory()->create([
            'is_active' => true,
            'roles' => ['manageUser'],
        ]);
        $member = User::factory()->create([
            'is_active' => true,
            'roles' => [],
        ]);
        $category = FlashcardCategory::query()->create(['name' => 'Assigned']);
        $removedCategory = FlashcardCategory::query()->create(['name' => 'Removed']);

        CategoryAccess::query()->create([
            'user_id' => $member->id,
            'flashcard_category_id' => $category->id,
            'can_edit' => true,
            'steps' => [2, 4],
        ]);
        $removedAccess = CategoryAccess::query()->create([
            'user_id' => $member->id,
            'flashcard_category_id' => $removedCategory->id,
            'can_edit' => true,
            'steps' => [3, 6],
        ]);
        $flashcard = Flashcard::query()->create([
            'flashcard_category_id' => $removedCategory->id,
            'title' => 'Removed study card',
        ]);
        $removedStudyCard = $removedAccess->studyCards()->create([
            'flashcard_id' => $flashcard->id,
            'step_index' => 0,
            'entered_at' => now(),
            'due_at' => now(),
        ]);

        $this->actingAs($manager)
            ->getJson("/api/users/{$member->id}")
            ->assertOk()
            ->assertJsonPath('user.id', $member->id)
            ->assertJsonCount(2, 'categories');

        $this->actingAs($manager)
            ->putJson("/api/users/{$member->id}/category-accesses", [
                'accesses' => [
                    ['category_id' => $category->id, 'has_access' => true, 'can_edit' => false],
                    ['category_id' => $removedCategory->id, 'has_access' => false, 'can_edit' => false],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('category_accesses', [
            'user_id' => $member->id,
            'flashcard_category_id' => $category->id,
            'can_edit' => false,
        ]);
        $this->assertDatabaseMissing('category_accesses', [
            'user_id' => $member->id,
            'flashcard_category_id' => $removedCategory->id,
        ]);
        $this->assertDatabaseMissing('study_cards', [
            'id' => $removedStudyCard->id,
        ]);
    }
}
