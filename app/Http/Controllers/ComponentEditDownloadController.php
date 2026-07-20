<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Services\Billing\EntitlementService;
use App\Services\Catalog\ComponentRouteResolver;
use App\Services\Catalog\EditedSourcesZip;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * POST /components/{usage}/{slug}/edit-download (SPEC §5.6): instant
 * download of the live-edit tab's edited sources — the posted files are
 * zipped verbatim with NO server-side build. Only reachable while the
 * live-edit feature flag is on (404 otherwise) and, for paid components,
 * behind the same full-library entitlement as the regular source download
 * (403 upgrade payload). Rate-limited 10/minute via the route.
 */
class ComponentEditDownloadController extends Controller
{
    public function __invoke(Request $request, string $usage, string $slug): JsonResponse|BinaryFileResponse
    {
        abort_unless((bool) app(Settings::class)->get('features.live_edit'), 404);

        $component = app(ComponentRouteResolver::class)->resolve($usage, $slug);

        // Zip-slip guard on the path regex: no leading slash, no `..`
        // segments, no backslashes — only library-relative file paths.
        $validated = $request->validate([
            'framework' => ['required', Rule::in(['react', 'vue'])],
            'files' => ['required', 'array', 'min:1', 'max:100'],
            'files.*.path' => ['required', 'string', 'max:255', 'regex:#^(?!/)(?!.*(?:^|/)\.\.(?:/|$))[A-Za-z0-9._\-/]+$#'],
            'files.*.code' => ['present', 'string', 'max:1000000'],
        ]);

        // Plan gate (SPEC §7.1): identical to ComponentDownloadController —
        // edited sources are still the component's sources.
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

        $zipPath = app(EditedSourcesZip::class)->build($validated['files']);

        return response()
            ->download($zipPath, "{$component->basename}-{$validated['framework']}-edited.zip")
            ->deleteFileAfterSend();
    }
}
