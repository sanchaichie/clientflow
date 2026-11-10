<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    /** GET /api/users?search=&role=&active=&per_page= */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::exists('roles', 'slug')],
            'active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->with('role')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->whereRelation('role', 'slug', $role))
            ->when(isset($filters['active']), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return UserResource::collection($users);
    }

    /** POST /api/users */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return (new UserResource($user->load('role')))->response()->setStatusCode(201);
    }

    /** GET /api/users/{user} */
    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load('role'));
    }

    /** PUT /api/users/{user} */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user = $this->users->update($user, $request->validated());

        return new UserResource($user->load('role'));
    }

    /** DELETE /api/users/{user} — deactivates; users are never hard-deleted. */
    public function destroy(Request $request, User $user): Response
    {
        Gate::authorize('delete', $user);

        abort_if($request->user()->is($user), 422, 'You cannot deactivate your own account.');

        $this->users->deactivate($user);

        return response()->noContent();
    }
}
