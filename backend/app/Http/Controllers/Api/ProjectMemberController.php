<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\AddProjectMembersRequest;
use App\Http\Resources\UserResource;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProjectMemberController extends Controller
{
    public function __construct(private readonly ProjectService $projects) {}

    /** POST /api/projects/{project}/members  {"user_ids": [1, 2]} */
    public function store(AddProjectMembersRequest $request, Project $project): AnonymousResourceCollection
    {
        $this->projects->addMembers($project, $request->validated('user_ids'), $request->user());

        return UserResource::collection($project->members()->orderBy('name')->get());
    }

    /** DELETE /api/projects/{project}/members/{user} */
    public function destroy(Request $request, Project $project, User $user): Response
    {
        Gate::authorize('manageMembers', $project);

        abort_unless($project->hasMember($user), 404);

        $this->projects->removeMember($project, $user, $request->user());

        return response()->noContent();
    }
}
