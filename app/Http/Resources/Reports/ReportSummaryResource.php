<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'task_completion' => [
                'rate' => $this->resource['task_completion']['rate'],
                'change' => $this->resource['task_completion']['change'],
                'total_tasks' => $this->resource['task_completion']['total_tasks'],
                'completed_tasks' => $this->resource['task_completion']['completed_tasks'],
            ],
            'projects_completion' => [
                'rate' => $this->resource['projects_completion']['rate'],
                'change' => $this->resource['projects_completion']['change'],
                'total_projects' => $this->resource['projects_completion']['total_projects'],
                'completed_projects' => $this->resource['projects_completion']['completed_projects'],
            ],
            'teams_activity' => [
                'rate' => $this->resource['teams_activity']['rate'],
                'change' => $this->resource['teams_activity']['change'],
                'total_members' => $this->resource['teams_activity']['total_members'],
                'active_members' => $this->resource['teams_activity']['active_members'],
            ],
        ];
    }
}
