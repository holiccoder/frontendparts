<?php

namespace App\Http\Resources;

use App\Enums\AccessLevel;
use App\Models\Component;
use App\Services\Billing\EntitlementService;
use App\Services\Catalog\ComponentContent;
use App\Services\Catalog\CompositionTree;
use App\Services\Catalog\LiveEditPayload;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full component payload for the detail page, shared with the interactive
 * preview modal (SPEC §5.4): header fields + badges, citation, both
 * framework file sets, sample data, params (props table source), preview
 * iframe URLs, per-viewport screenshots, composition tree and related
 * components. Fail-soft — preview/screenshot URLs are null when the build
 * artifacts do not exist locally.
 *
 * @mixin Component
 */
class ComponentDetailResource extends JsonResource
{
    /**
     * Rendered as an Inertia prop, so no `data` envelope.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $content = app(ComponentContent::class)->for($this->resource);
        $tree = app(CompositionTree::class)->for($this->resource);
        $widths = array_map('intval', (array) config('library.screenshot_widths', [375, 768, 1280]));

        $related = Component::query()
            ->published()
            ->where('usage_category_id', $this->usage_category_id)
            ->whereKeyNot($this->id)
            ->with('usageCategory')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        // Blur-gate (SPEC §5.4): paid components lock their Code/Data
        // tabs behind a full-library plan entitlement (Starter/Pro);
        // free components are always entitled.
        $entitled = $this->access_level === AccessLevel::Free
            || app(EntitlementService::class)->for($request->user())->hasFullLibrary();

        $liveEdit = (bool) app(Settings::class)->get('features.live_edit');

        $payload = [
            'id' => $this->id,
            'slug' => $this->slug,
            'basename' => $this->basename,
            'name' => $this->name,
            'level' => $this->level->value,
            'usage' => [
                'name' => $this->usageCategory->name,
                'slug' => $this->usageCategory->slug,
            ],
            'industries' => $this->industries
                ->map(fn ($industry): array => ['name' => $industry->name, 'slug' => $industry->slug])
                ->values()
                ->all(),
            'tags' => $this->tags
                ->map(fn ($tag): array => ['name' => $tag->name, 'slug' => $tag->slug])
                ->values()
                ->all(),
            'access' => $this->access_level->value,
            'entitled' => $entitled,
            'features' => [
                'dark_toggle' => (bool) app(Settings::class)->get('features.preview_dark_toggle'),
                'tree_interactions' => (bool) app(Settings::class)->get('features.tree_interactions'),
                'live_edit' => $liveEdit,
            ],
            'citation' => [
                'source_name' => $this->source_name,
                'source_url' => $this->source_url,
            ],
            'version' => $this->version,
            'deps' => $this->deps ?? [],
            'params' => $content['params'],
            'data' => $content['data'],
            'files' => $content['files'],
            'previews' => [
                'react' => $this->previewUrl('react'),
                'vue' => $this->previewUrl('vue'),
            ],
            'screenshots' => [
                'react' => $this->shots('react', $widths),
                'vue' => $this->shots('vue', $widths),
            ],
            'tree' => $tree,
            'related' => ComponentCardResource::collection($related)->resolve($request),
            'og_image' => $this->screenshotUrl('react', 1280),
        ];

        // Live edit (SPEC §5.6): the Edit tab's client-side bundler payload
        // rides the same modal payload, but only while the feature is on AND
        // the reader is entitled to sources — locked components get the tab's
        // blur-gate state (via features.live_edit + entitled) without ever
        // shipping closure code to the client.
        if ($liveEdit && $entitled) {
            $payload['edit'] = [
                'react' => app(LiveEditPayload::class)->react($this->resource),
                'vue' => app(LiveEditPayload::class)->vue($this->resource),
            ];
        }

        return $payload;
    }

    /**
     * Cast to object: ResourceResponse applies array_merge_recursive, which
     * would re-index numeric width keys (375/768/1280 → 0/1/2); an object
     * keeps the widths as JSON object keys.
     *
     * @param  list<int>  $widths
     */
    private function shots(string $framework, array $widths): object
    {
        $shots = [];

        foreach ($widths as $width) {
            $shots[$width] = $this->screenshotUrl($framework, $width);
        }

        return (object) $shots;
    }
}
