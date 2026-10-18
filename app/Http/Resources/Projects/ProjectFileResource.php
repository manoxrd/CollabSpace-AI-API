<?php

namespace App\Http\Resources\Projects;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectFileResource extends JsonResource
{
    /**
     * Transform the resource into an array for project files.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_formatted' => $this->size_formatted,
            'downloads_count' => $this->downloads_count,
            'download_url' => $this->file_url,
            'uploader' => $this->uploader ? [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
                'avatar_url' => $this->uploader->avatar_url,
                'formatted' => 'By '.$this->uploader->name,
            ] : null,
            'uploaded_at' => $this->created_at?->toISOString(),
            'uploaded_at_relative' => $this->uploaded_at_relative,
            'card_meta' => $this->card_meta,
        ];
    }
}
