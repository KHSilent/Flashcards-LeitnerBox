<?php

namespace App\Http\Controllers;

use App\Models\CategoryAccess;
use App\Models\FlashcardCategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const AVAILABLE_ROLES = ['manageUser', 'accessAllCategories'];

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

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorizeManageUsers($request);

        return response()->json([
            'user' => user_payload($user),
            'roles' => self::AVAILABLE_ROLES,
            'categories' => $this->categoryAccessPayload($user),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeManageUsers($request);

        $data = $this->validateUser($request, $user, false);

        if ($request->user()->is($user) && array_key_exists('is_active', $data) && ! $data['is_active']) {
            abort(422, 'You cannot deactivate your own account.');
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
            abort(422, 'You cannot delete your own account.');
        }

        $user->delete();

        return response()->json(['ok' => true]);
    }

    public function updateCategoryAccesses(Request $request, User $user): JsonResponse
    {
        $this->authorizeManageUsers($request);

        $data = $request->validate([
            'accesses' => ['present', 'array'],
            'accesses.*.category_id' => ['required', 'integer', 'exists:flashcard_categories,id', 'distinct'],
            'accesses.*.has_access' => ['required', 'boolean'],
            'accesses.*.can_edit' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($user, $data) {
            $requested = collect($data['accesses'])->keyBy('category_id');

            CategoryAccess::query()
                ->where('user_id', $user->id)
                ->get()
                ->each(function (CategoryAccess $access) use ($requested) {
                    $item = $requested->get($access->flashcard_category_id);
                    $hasAccess = $item && ((bool) $item['has_access'] || (bool) $item['can_edit']);

                    if ($hasAccess) {
                        return;
                    }

                    $access->studyCards()->delete();
                    $access->delete();
                });

            foreach ($requested as $item) {
                $hasAccess = (bool) $item['has_access'] || (bool) $item['can_edit'];

                if (! $hasAccess) {
                    continue;
                }

                $access = CategoryAccess::query()->firstOrNew([
                    'user_id' => $user->id,
                    'flashcard_category_id' => $item['category_id'],
                ]);

                $access->can_edit = (bool) $item['can_edit'];
                if ($access->steps === null) {
                    $access->steps = CategoryAccess::DEFAULT_STEPS;
                }
                $access->save();
            }
        });

        return response()->json([
            'categories' => $this->categoryAccessPayload($user->refresh()),
        ]);
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

    private function categoryAccessPayload(User $user): array
    {
        $accesses = CategoryAccess::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('flashcard_category_id');

        return FlashcardCategory::query()
            ->withCount('flashcards')
            ->orderBy('name')
            ->get()
            ->map(fn (FlashcardCategory $category) => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'name' => $category->name,
                'flashcards_count' => $category->flashcards_count,
                'has_access' => $accesses->has($category->id),
                'can_edit' => (bool) $accesses->get($category->id)?->can_edit,
            ])
            ->values()
            ->all();
    }
}
