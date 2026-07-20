<?php

namespace App\Http\Controllers;

use App\Http\Resources\ComponentDetailResource;
use App\Services\Catalog\ComponentRouteResolver;

/**
 * JSON payload endpoint for the preview-modal overlay (SPEC §5.4): the same
 * ComponentDetailResource as the SSR detail page with the same published-only
 * basename resolution — but records NO view event (an overlay open is a
 * preview interaction, not a page view; views stay page-only per SPEC §8.6).
 * Rate-limited 60/minute via the route definition.
 */
class ComponentApiController extends Controller
{
    public function show(string $usage, string $slug): ComponentDetailResource
    {
        $component = app(ComponentRouteResolver::class)->resolve($usage, $slug);
        $component->load(['usageCategory', 'industries', 'tags']);

        return new ComponentDetailResource($component);
    }
}
