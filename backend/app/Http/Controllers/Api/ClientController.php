<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ListClientsRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Client CRUD is simple enough to live in the controller; there is no
 * multi-step business logic that would justify a service class.
 */
class ClientController extends Controller
{
    /** GET /api/clients?search=&status=&archived=&sort=&per_page= */
    public function index(ListClientsRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = Client::query()
            ->visibleTo($request->user())
            ->with('accountManager')
            ->withCount('projects')
            ->when($request->boolean('archived'),
                fn ($q) => $q->whereNotNull('archived_at'),
                fn ($q) => $q->whereNull('archived_at'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('company_name', 'like', "%{$search}%")
                ->orWhere('contact_person', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")));

        $this->applySort($query, $filters['sort'] ?? null, ['company_name', 'created_at', 'status'], 'company_name');

        return ClientResource::collection($query->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    /** POST /api/clients */
    public function store(StoreClientRequest $request): JsonResponse
    {
        $user = $request->user();
        $client = new Client($request->validated());
        $client->forceFill([
            'created_by' => $user->id,
            'account_manager_id' => $user->isAdmin() ? $request->validated('account_manager_id') : $user->id,
        ])->save();

        return (new ClientResource($client->load('accountManager')))->response()->setStatusCode(201);
    }

    /** GET /api/clients/{client} */
    public function show(Request $request, Client $client): ClientResource
    {
        Gate::authorize('view', $client);

        // Only list the client's projects this user is allowed to see.
        $client->load([
            'accountManager',
            'projects' => fn ($q) => $q->visibleTo($request->user())->with('manager')->orderBy('name'),
        ]);

        return new ClientResource($client);
    }

    /** PUT /api/clients/{client} */
    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $client->fill($request->validated())->save();

        return new ClientResource($client->load('accountManager'));
    }

    /** DELETE /api/clients/{client} — archives; clients are never hard-deleted. */
    public function destroy(Client $client): Response
    {
        Gate::authorize('delete', $client);

        $client->forceFill(['archived_at' => now()])->save();

        return response()->noContent();
    }

    /** POST /api/clients/{client}/restore */
    public function restore(Client $client): ClientResource
    {
        Gate::authorize('restore', $client);

        $client->forceFill(['archived_at' => null])->save();

        return new ClientResource($client->load('accountManager'));
    }
}
