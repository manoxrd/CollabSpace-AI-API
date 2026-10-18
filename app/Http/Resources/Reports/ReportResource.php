<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_type' => $this->report_type,
            'report_type_label' => $this->report_type_label,
            'status' => $this->status->value,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'file_url' => $this->file_url,
            'metrics_data' => $this->metrics_data,
            'generator' => $this->generator ? [
                'id' => $this->generator->id,
                'name' => $this->generator->name,
                'avatar_url' => $this->generator->avatar_url,
            ] : null,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->avatar_url,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'formatted_created_at' => $this->formatted_created_at,
        ];
    }
}
