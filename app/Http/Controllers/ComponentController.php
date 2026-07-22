<?php

namespace App\Http\Controllers;

use App\Enums\ComponentEventType;
use App\Http\Resources\ComponentDetailResource;
use App\Services\Catalog\ComponentRouteResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Component detail `/components/{usage}/{slug}` (SPEC §15.1, FR-1.6).
 * The URL slug is the basename of the stored full slug
 * (`elements/section-title-01` → `section-title-01`); resolution is
 * scoped to the usage category and 404s on zero or ambiguous matches.
 */
class ComponentController extends Controller
{
    public function show(Request $request, string $usage, string $slug): Response
    {
        $component = app(ComponentRouteResolver::class)->resolve($usage, $slug);
        $component->load(['usageCategory', 'industries', 'tags']);

        $component->recordEvent(ComponentEventType::View, $request->user());

        $validated = $request->validate([
            'framework' => ['sometimes', 'nullable', Rule::in(['react', 'vue'])],
        ]);

        $resource = new ComponentDetailResource($component);
        $canonical = $component->publicUrl();
        $ogImage = $component->screenshotUrl('react', 1280) ?? url('/brand/logo.png');
        $categoryName = $component->usageCategory->name;

        return $this->cachedResponse(Inertia::render('catalog/component', [
            'component' => $resource,
            'framework' => $validated['framework'] ?? 'react',
            'meta' => [
                'title' => "{$component->name} — {$categoryName} component for React & Vue",
                'description' => "{$component->name} is a production-ready {$categoryName} {$component->level->value} recreated from the best sites on the web, with live preview and clean React + Vue code.",
                'canonical' => $canonical,
                'og_image' => $ogImage,
                'og_type' => 'article',
            ],
        ]), $request);
    }
}
