<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\ListTaskCommentsRequest;
use App\Http\Requests\Task\StoreTaskCommentRequest;
use App\Http\Resources\TaskCommentResource;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\TaskCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TaskCommentController extends Controller
{
    public function __construct(private readonly TaskCommentService $comments) {}

    /** GET /api/tasks/{task}/comments — oldest first, like a conversation. */
    public function index(ListTaskCommentsRequest $request, Task $task): AnonymousResourceCollection
    {
        $comments = $task->comments()
            ->with('author')
            ->oldest()
            ->oldest('id')
            ->paginate($request->validated('per_page') ?? 50);

        return TaskCommentResource::collection($comments);
    }

    /** POST /api/tasks/{task}/comments */
    public function store(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        $comment = $this->comments->create($task, $request->validated(), $request->user());

        return (new TaskCommentResource($comment->load('author')))->response()->setStatusCode(201);
    }

    /** DELETE /api/comments/{comment} */
    public function destroy(TaskComment $comment): Response
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }
}
