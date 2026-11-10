<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ListProjectsRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects) {}

    /** GET /api/projects?search=&status=&client_id=&manager_id=&archived=&sort=&per_page= */
    public function index(ListProjectsRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        // Eager-load everything the resource prints: one query per relation
        // for the whole page instead of one per row (no N+1).
        $query = Project::query()
            ->visibleTo($request->user())
            ->with(['client:id,company_name', 'manager'])
            ->withCount(['members', 'tasks'])
            ->when($request->boolean('archived'),
                fn ($q) => $q->whereNotNull('archived_at'),
                fn ($q) => $q->whereNull('archived_at'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['client_id'] ?? null, fn ($q, $id) => $q->where('client_id', $id))
            ->when($filters['manager_id'] ?? null, fn ($q, $id) => $q->where('manager_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"));

        $this->applySort($query, $filters['sort'] ?? null,
            ['name', 'start_date', 'end_date', 'progress', 'status', 'created_at'], '-created_at');

        return ProjectResource::collection($query->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    /** POST /api/projects */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projects->create($request->validated(), $request->user());

        return (new ProjectResource($this->loadDetails($project)))->response()->setStatusCode(201);
    }

    /** GET /api/projects/{project} */
    public function show(Project $project): ProjectResource
    {
        Gate::authorize('view', $project);

        return new ProjectResource($this->loadDetails($project));
    }

    /** PUT /api/projects/{project} */
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $project = $this->projects->update($project, $request->validated(), $request->user());

        return new ProjectResource($this->loadDetails($project));
    }

    /** DELETE /api/projects/{project} — archives; projects are never hard-deleted via the API. */
    public function destroy(Request $request, Project $project): Response
    {
        Gate::authorize('delete', $project);

        $this->projects->archive($project, $request->user());

        return response()->noContent();
    }

    /** POST /api/projects/{project}/restore */
    public function restore(Request $request, Project $project): ProjectResource
    {
        Gate::authorize('restore', $project);

        $this->projects->restore($project, $request->user());

        return new ProjectResource($this->loadDetails($project));
    }

    private function loadDetails(Project $project): Project
    {
        return $project->load([
            'client:id,company_name',
            'manager',
            'members' => fn ($q) => $q->orderBy('name'),
        ])->loadCount('tasks');
    }
}
