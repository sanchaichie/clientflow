<?php

namespace Tests\Feature\Collaboration;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCommentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = User::factory()->teamMember()->create();
        $project = Project::factory()->active()->create();
        $project->members()->attach($this->member);
        $this->task = Task::factory()->for($project)->create();
    }

    public function test_member_comments_on_a_task_in_their_project(): void
    {
        $this->actingAs($this->member)
            ->postJson("/api/tasks/{$this->task->id}/comments", ['body' => 'On it!'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'On it!')
            ->assertJsonPath('data.type', 'comment')
            ->assertJsonPath('data.author.id', $this->member->id);
    }

    public function test_member_can_submit_a_work_update(): void
    {
        $this->actingAs($this->member)
            ->postJson("/api/tasks/{$this->task->id}/comments", ['body' => 'Done 80%', 'type' => 'work_update'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'work_update');
    }

    public function test_comment_is_validated(): void
    {
        $this->actingAs($this->member)
            ->postJson("/api/tasks/{$this->task->id}/comments", ['body' => '', 'type' => 'rant'])
            ->assertJsonValidationErrors(['body', 'type']);
    }

    public function test_outsider_cannot_read_or_post_comments(): void
    {
        $outsider = User::factory()->teamMember()->create();

        $this->actingAs($outsider)->getJson("/api/tasks/{$this->task->id}/comments")->assertForbidden();
        $this->actingAs($outsider)->postJson("/api/tasks/{$this->task->id}/comments", ['body' => 'Hi'])->assertForbidden();
    }

    public function test_no_comments_on_archived_projects(): void
    {
        $this->task->project->forceFill(['archived_at' => now()])->save();

        $this->actingAs($this->member)
            ->postJson("/api/tasks/{$this->task->id}/comments", ['body' => 'Hi'])
            ->assertForbidden();
    }

    public function test_comments_are_listed_oldest_first_with_authors(): void
    {
        $first = TaskComment::factory()->for($this->task)->create(['created_at' => now()->subHour()]);
        $second = TaskComment::factory()->for($this->task)->create();

        $this->actingAs($this->member)->getJson("/api/tasks/{$this->task->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonStructure(['data' => [['author' => ['id', 'name']]]]);
    }

    public function test_only_the_author_or_an_admin_can_delete_a_comment(): void
    {
        $comment = TaskComment::factory()->for($this->task)->create(['user_id' => $this->member->id]);
        $colleague = User::factory()->teamMember()->create();
        $this->task->project->members()->attach($colleague);

        $this->actingAs($colleague)->deleteJson("/api/comments/{$comment->id}")->assertForbidden();
        $this->actingAs($this->member)->deleteJson("/api/comments/{$comment->id}")->assertNoContent();
        $this->assertModelMissing($comment);

        $other = TaskComment::factory()->for($this->task)->create(['user_id' => $colleague->id]);
        $this->actingAs(User::factory()->admin()->create())->deleteJson("/api/comments/{$other->id}")->assertNoContent();
    }
}
