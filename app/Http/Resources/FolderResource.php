<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FolderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'project_id' => $this->project_id,
            'parent_id' => $this->parent_id,
            'files_count' => $this->files_count,
            'subfolders_count' => $this->subfolders_count,
            'creator' => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'avatar_url' => $this->creator->avatar_url,
            ] : null,
            'children' => FolderResource::collection($this->whenLoaded('children')),
            'files' => FileResource::collection($this->whenLoaded('files')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
