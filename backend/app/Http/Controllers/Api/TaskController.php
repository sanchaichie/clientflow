<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\ListTasksRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Requests\Task\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    /**
     * GET /api/tasks?project_id=&status=&priority=&assignee_id=(id|me)&due=(overdue|today)&search=&sort=&per_page=
     *
     * Tasks of archived projects are hidden unless a project_id is given.
     * The Kanban board calls ?project_id=X&sort=position&per_page=200.
     */
    public function index(ListTasksRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $user = $request->user();

        $query = Task::query()
            ->visibleTo($user)
            ->with(['project:id,name,manager_id', 'assignee'])
            ->withCount('comments')
            ->when($filters['project_id'] ?? null,
                fn ($q, $id) => $q->where('project_id', $id),
                fn ($q) => $q->whereHas('project', fn ($p) => $p->whereNull('archived_at')))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority))
            ->when($filters['assignee_id'] ?? null, fn ($q, $assignee) => $q->where('assignee_id', $assignee === 'me' ? $user->id : (int) $assignee))
            ->when(($filters['due'] ?? null) === 'overdue', fn ($q) => $q->overdue())
            ->when(($filters['due'] ?? null) === 'today', fn ($q) => $q->dueToday())
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', "%{$search}%"));

        $this->applySort($query, $filters['sort'] ?? null,
            ['title', 'due_date', 'status', 'position', 'created_at'], '-created_at');

        return TaskResource::collection($query->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    /** POST /api/tasks */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->tasks->create($request->project(), $request->validated(), $request->user());

        return (new TaskResource($this->loadDetails($task)))->response()->setStatusCode(201);
    }

    /** GET /api/tasks/{task} */
    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return new TaskResource($this->loadDetails($task));
    }

    /** PUT /api/tasks/{task} */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $task = $this->tasks->update($task, $request->validated());

        return new TaskResource($this->loadDetails($task));
    }

    /** PATCH /api/tasks/{task}/status  {"status": "review", "position": 0} */
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): TaskResource
    {
        $task = $this->tasks->changeStatus(
            $task,
            TaskStatus::from($request->validated('status')),
            $request->validated('position'),
        );

        return new TaskResource($this->loadDetails($task));
    }

    /** DELETE /api/tasks/{task} */
    public function destroy(Task $task): Response
    {
        Gate::authorize('delete', $task);

        $this->tasks->delete($task);

        return response()->noContent();
    }

    private function loadDetails(Task $task): Task
    {
        return $task->load(['project:id,name,manager_id', 'assignee', 'creator'])->loadCount('comments');
    }
}
