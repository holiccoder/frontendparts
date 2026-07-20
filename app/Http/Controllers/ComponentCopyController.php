<?php

namespace App\Http\Controllers;

use App\Enums\ComponentEventType;
use App\Services\Catalog\ComponentRouteResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /components/{usage}/{slug}/copy (SPEC §6.1, §8.6): records a copy
 * event fired by the preview modal's copy buttons. Accountless — guests may
 * copy free sources, and the Code/Data blur-gate already keeps paid sources
 * out of guest reach. Rate-limited 30/minute via the route.
 */
class ComponentCopyController extends Controller
{
    public function __invoke(Request $request, string $usage, string $slug): Response
    {
        $component = app(ComponentRouteResolver::class)->resolve($usage, $slug);

        $component->recordEvent(ComponentEventType::Copy, $request->user());

        return response()->noContent();
    }
}
