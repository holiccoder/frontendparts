<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Enums\ComponentEventType;
use App\Services\Catalog\ComponentRouteResolver;
use App\Services\Catalog\ComponentZipper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GET /components/{usage}/{slug}/download?framework=react|vue
 * (SPEC §2.4, §6.1, §8.6): streams the closure zip and records a download
 * event. Accountless for free components; guests hitting a paid component
 * get a 403 upgrade payload. Rate-limited 10/minute via the route.
 */
class ComponentDownloadController extends Controller
{
    public function __invoke(Request $request, string $usage, string $slug): JsonResponse|BinaryFileResponse
    {
        $component = app(ComponentRouteResolver::class)->resolve($usage, $slug);

        $validated = $request->validate([
            'framework' => ['sometimes', 'nullable', Rule::in(['react', 'vue'])],
        ]);

        $framework = $validated['framework'] ?? 'react';

        if ($component->access_level === AccessLevel::Paid && $request->user() === null) {
            return response()->json([
                'error' => 'upgrade_required',
                'upgrade' => [
                    'cta' => 'Upgrade to download',
                    'pricing_url' => '/pricing',
                ],
            ], 403);
        }

        // TODO Phase 2 (plan entitlements): any authenticated user currently
        // passes the paid gate — enforce plan-based download rights here once
        // subscriptions exist.

        $zipPath = app(ComponentZipper::class)->build($component, $framework);

        $component->recordEvent(ComponentEventType::Download, $request->user());

        return response()
            ->download($zipPath, "{$component->basename}-{$framework}.zip")
            ->deleteFileAfterSend();
    }
}
