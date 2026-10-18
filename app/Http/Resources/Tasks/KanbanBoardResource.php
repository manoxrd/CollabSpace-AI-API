<?php

namespace App\Http\Resources\Tasks;

use App\Enums\TaskStatus;
use App\Http\Resources\TaskResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KanbanBoardResource extends JsonResource
{
    /**
     * Transform the resource into an array formatted for the 4-column Kanban board.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tasks = $this->resource;
        $columns = [];
        $meta = [
            'total_tasks' => $tasks->count(),
        ];

        foreach (TaskStatus::cases() as $statusCase) {
            $columnTasks = $tasks->filter(function ($t) use ($statusCase) {
                $statusVal = $t->status instanceof TaskStatus ? $t->status->value : $t->status;

                return $statusVal === $statusCase->value;
            });

            $columns[$statusCase->value] = [
                'title' => $statusCase->label(),
                'status' => $statusCase->value,
                'count' => $columnTasks->count(),
                'tasks' => TaskResource::collection($columnTasks->values()),
            ];

            $meta[$statusCase->value.'_count'] = $columnTasks->count();
        }

        return [
            'columns' => $columns,
            'meta' => $meta,
        ];
    }
}
