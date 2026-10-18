<?php

namespace App\Http\Resources\Tasks;

use App\Http\Resources\FileResource;
use App\Http\Resources\TaskResource;
use Illuminate\Http\Request;

class TaskDetailResource extends TaskResource
{
    /**
     * Transform the resource into an array with deep details.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = parent::toArray($request);

        $base['creator'] = $this->creator ? [
            'id' => $this->creator->id,
            'name' => $this->creator->name,
            'avatar_url' => $this->creator->avatar_url,
        ] : null;

        $base['comments'] = $this->comments->map(fn ($c) => [
            'id' => $c->id,
            'comment' => $c->comment,
            'user' => [
                'id' => $c->user->id,
                'name' => $c->user->name,
                'avatar_url' => $c->user->avatar_url,
            ],
            'created_at' => $c->created_at?->toISOString(),
            'time_ago' => $c->created_at?->diffForHumans(),
        ]);

        $base['activities'] = $this->activities->map(fn ($act) => [
            'id' => $act->id,
            'type' => $act->type,
            'comment' => $act->comment,
            'old_status' => $act->old_status,
            'new_status' => $act->new_status,
            'user' => [
                'id' => $act->user->id,
                'name' => $act->user->name,
                'avatar_url' => $act->user->avatar_url,
            ],
            'created_at' => $act->created_at?->toISOString(),
            'time_ago' => $act->created_at?->diffForHumans(),
        ]);

        $base['files'] = FileResource::collection($this->files);

        return $base;
    }
}
