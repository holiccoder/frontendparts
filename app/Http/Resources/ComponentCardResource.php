<?php

namespace App\Http\Resources;

use App\Models\Component;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact card shape shared by every catalog grid (home, index, taxonomy
 * pages, related row). URLs are pre-computed server-side so the React
 * pages stay free of route generation.
 *
 * @mixin Component
 */
class ComponentCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->basename,
            'level' => $this->level->value,
            'access' => $this->access_level->value,
            'usage' => [
                'name' => $this->usageCategory->name,
                'slug' => $this->usageCategory->slug,
            ],
            'url' => $this->publicUrl(),
            'thumb' => $this->screenshotUrl('react', 375),
        ];
    }
}
