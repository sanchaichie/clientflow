<?php

namespace App\Http\Resources;

use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskComment */
class TaskCommentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'type' => $this->type->value,
            'body' => $this->body,
            'user_id' => $this->user_id,
            'author' => new UserResource($this->whenLoaded('author')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
