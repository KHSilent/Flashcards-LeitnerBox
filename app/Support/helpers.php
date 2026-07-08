<?php

use App\Models\User;

if (! function_exists('user_payload')) {
    function user_payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'roles' => $user->roles ?: [],
            'avatar_url' => $user->gravatarUrl(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
