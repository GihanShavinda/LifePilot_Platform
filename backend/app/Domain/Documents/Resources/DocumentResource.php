<?php

namespace App\Domain\Documents\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestExtraction = $this->relationLoaded('latestExtraction')
            ? $this->latestExtraction
            : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category?->slug,
            'source' => $this->source?->slug,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'checksum' => $this->checksum,
            'document_date' => $this->document_date?->toDateString(),
            'issuer' => $this->issuer,
            'status' => $this->enumValue($this->status),
            'processing_status' => $this->enumValue($this->processing_status),
            'intelligence_status' => $latestExtraction
                ? $this->enumValue($latestExtraction->status)
                : null,
            'intelligence_version' => $latestExtraction?->version_number,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'tags' => $this->tags->pluck('name')->values(),
            'versions' => $this->whenLoaded('versions', fn () => $this->versions->map(fn ($version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'original_filename' => $version->original_filename,
                'mime_type' => $version->mime_type,
                'size' => $version->size,
                'checksum' => $version->checksum,
                'created_at' => $version->created_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
