<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Enums\ComponentEventType;
use App\Http\Resources\ComponentDetailResource;
use App\Models\Category;
use App\Models\Component;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
        $category = Category::query()
            ->where('type', CategoryType::Usage)
            ->where('slug', $usage)
            ->firstOrFail();

        $matches = Component::query()
            ->published()
            ->where('usage_category_id', $category->id)
            ->where(function (Builder $query) use ($slug): void {
                $query->where('slug', $slug)
                    ->orWhere('slug', 'like', '%/'.$slug);
            })
            ->limit(2)
            ->get();

        abort_unless($matches->count() === 1, 404);

        /** @var Component $component */
        $component = $matches->first()->load(['usageCategory', 'industries', 'tags']);

        $component->recordEvent(ComponentEventType::View, $request->user());

        $validated = $request->validate([
            'framework' => ['sometimes', 'nullable', Rule::in(['react', 'vue'])],
        ]);

        $resource = new ComponentDetailResource($component);
        $canonical = $component->publicUrl();
        $ogImage = $component->screenshotUrl('react', 1280) ?? url('/brand/logo.png');

        return Inertia::render('catalog/component', [
            'component' => $resource,
            'framework' => $validated['framework'] ?? 'react',
            'meta' => [
                'title' => "{$component->name} — {$category->name} component for React & Vue",
                'description' => "{$component->name} is a production-ready {$category->name} {$component->level->value} recreated from the best sites on the web, with live preview and clean React + Vue code.",
                'canonical' => $canonical,
                'og_image' => $ogImage,
                'og_type' => 'article',
            ],
        ]);
    }
}
