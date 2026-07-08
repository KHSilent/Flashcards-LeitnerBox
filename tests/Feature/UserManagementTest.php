<?php

namespace Tests\Feature;

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
}
