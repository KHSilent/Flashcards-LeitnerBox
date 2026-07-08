<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const AVAILABLE_ROLES = ['manageUser'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManageUsers($request);

        $perPage = min(max((int) $request->integer('per_page', 10), 5), 50);
        $users = User::query()
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'users' => collect($users->items())->map(fn (User $user) => user_payload($user))->values(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
            'roles' => self::AVAILABLE_ROLES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManageUsers($request);

        $data = $this->validateUser($request, new User, true);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
            'roles' => $this->normalizeRoles($data['roles'] ?? []),
        ]);

        return response()->json(['user' => user_payload($user)], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeManageUsers($request);

        $data = $this->validateUser($request, $user, false);

        if ($request->user()->is($user) && array_key_exists('is_active', $data) && ! $data['is_active']) {
            abort(422, 'نمی‌توانید حساب خودتان را غیرفعال کنید.');
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $data['is_active'] ?? $user->is_active,
            'roles' => $this->normalizeRoles($data['roles'] ?? []),
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->forceFill($payload)->save();

        return response()->json(['user' => user_payload($user->refresh())]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeManageUsers($request);

        if ($request->user()->is($user)) {
            abort(422, 'نمی‌توانید حساب خودتان را حذف کنید.');
        }

        $user->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeManageUsers(Request $request): void
    {
        abort_unless($request->user()?->hasRole('manageUser'), 403);
    }

    private function validateUser(Request $request, User $user, bool $creating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in(self::AVAILABLE_ROLES)],
        ]);
    }

    private function normalizeRoles(array $roles): array
    {
        return collect($roles)
            ->filter(fn (string $role) => in_array($role, self::AVAILABLE_ROLES, true))
            ->unique()
            ->values()
            ->all();
    }
}
