<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Enums\ComponentEventType;
use App\Services\Billing\EntitlementService;
use App\Services\Catalog\ComponentRouteResolver;
use App\Services\Catalog\ComponentZipper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GET /components/{usage}/{slug}/download?framework=react|vue
 * (SPEC §2.4, §6.1, §8.6): streams the closure zip and records a download
 * event. Accountless for free components; paid components require a
 * full-library plan entitlement (SPEC §7.1), otherwise a 403 upgrade
 * payload. Rate-limited 10/minute via the route.
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

        // Plan gate (SPEC §7.1, FR-7.6): paid components require a
        // full-library entitlement (Starter/Pro). Guests resolve to a Free
        // entitlement, so this one check covers them too.
        if ($component->access_level === AccessLevel::Paid
            && ! app(EntitlementService::class)->for($request->user())->hasFullLibrary()) {
            return response()->json([
                'error' => 'upgrade_required',
                'upgrade' => [
                    'cta' => 'Upgrade to download',
                    'pricing_url' => '/pricing',
                ],
            ], 403);
        }

        $zipPath = app(ComponentZipper::class)->build($component, $framework);

        $component->recordEvent(ComponentEventType::Download, $request->user());

        return response()
            ->download($zipPath, "{$component->basename}-{$framework}.zip")
            ->deleteFileAfterSend();
    }
}
