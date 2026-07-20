<?php

namespace App\Http\Middleware;

use App\Services\Billing\EntitlementService;
use App\Services\Legal\LegalPages;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                // Preview-modal pane layout (SPEC §5.4); guests keep theirs
                // in localStorage instead.
                'preview_layout' => $request->user()?->preview_layout,
                // Effective plan entitlements (SPEC §7.1); guests resolve to
                // a Free entitlement, so this is always populated.
                'entitlements' => fn (): array => $this->entitlements($request),
            ],
            // Footer legal links (SPEC §15.7): every public page's footer
            // renders the full seven from the LegalPages registry, so the
            // sitemap, routes and footer can never drift apart.
            'legalNav' => fn (): array => app(LegalPages::class)->navigation(),
            // Shared explicitly so the SSR bundle (no @routes script) can
            // build Ziggy's route() helper from page props.
            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            // One-shot user notices (e.g. project removal prune cascade,
            // SPEC §6.1) flashed by dashboard-zone POST/DELETE endpoints.
            'flash' => [
                'notice' => fn (): ?string => $request->session()->get('notice'),
                // B7 cancel flow (SPEC §16.2): the reason-mapped save offer
                // presented between the exit survey and confirmation.
                'save_offer' => fn (): ?array => $request->session()->get('save_offer'),
            ],
        ]);
    }

    /**
     * Serializable shape of the user's Entitlement for the frontend
     * (SPEC §7.1 feature matrix + §8.7 settings-driven project limit).
     *
     * @return array{plan: string, is_paid: bool, has_full_library: bool, can_scaffold: bool, project_limit: int|null}
     */
    private function entitlements(Request $request): array
    {
        $entitlement = app(EntitlementService::class)->for($request->user());

        return [
            'plan' => $entitlement->plan()->value,
            'is_paid' => $entitlement->isPaid(),
            'has_full_library' => $entitlement->hasFullLibrary(),
            'can_scaffold' => $entitlement->canScaffold(),
            'project_limit' => $entitlement->projectLimit(),
        ];
    }
}
