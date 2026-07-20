<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Enums\ComponentEventType;
use App\Http\Resources\ComponentDetailResource;
use App\Services\Billing\EntitlementService;
use App\Services\Catalog\ComponentRouteResolver;
use Illuminate\Http\Request;

/**
 * JSON payload endpoint for the preview-modal overlay (SPEC §5.4): the same
 * ComponentDetailResource as the SSR detail page with the same published-only
 * basename resolution — but records NO view event (an overlay open is a
 * preview interaction, not a page view; views stay page-only per SPEC §8.6).
 * Rate-limited 60/minute via the route definition.
 */
class ComponentApiController extends Controller
{
    public function show(Request $request, string $usage, string $slug): ComponentDetailResource
    {
        $component = app(ComponentRouteResolver::class)->resolve($usage, $slug);
        $component->load(['usageCategory', 'industries', 'tags']);

        // Blur-gate hit (SPEC §5.4, §16.2 B2 trigger): a non-entitled user
        // opening a paid component's payload gets the blurred modal — record
        // the signal for the upgrade-trigger sequence.
        if ($component->access_level === AccessLevel::Paid
            && ! app(EntitlementService::class)->for($request->user())->hasFullLibrary()) {
            $component->recordEvent(ComponentEventType::GateHit, $request->user());
        }

        return new ComponentDetailResource($component);
    }
}
