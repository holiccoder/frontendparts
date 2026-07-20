<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ComponentEventType;
use App\Enums\LicenseState;
use App\Http\Controllers\Controller;
use App\Http\Resources\ComponentCardResource;
use App\Models\Component;
use App\Models\ComponentEvent;
use App\Models\Order;
use App\Models\Project;
use App\Services\Billing\Entitlement;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard overview (SPEC §15.4, CSR): plan status from the entitlement,
 * the user's projects with usage against the plan limit, their recent
 * downloads from component events, and the newest published drops (same
 * query as the catalog home page's latest drops).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $entitlement = app(EntitlementService::class)->for($user);

        // The license summary mirrors EntitlementService's "latest order wins".
        $license = $user->orders()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $projects = $user->projects()
            ->withCount('components')
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'components_count' => $project->components_count,
                'url' => route('dashboard.projects.show', $project),
            ]);

        $recentDownloads = ComponentEvent::query()
            ->where('user_id', $user->id)
            ->where('type', ComponentEventType::Download)
            ->with('component.usageCategory')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (ComponentEvent $event): array => [
                'id' => $event->id,
                'downloaded_at' => $event->created_at->toIso8601String(),
                'component' => [
                    'name' => $event->component->name,
                    'url' => $event->component->publicUrl(),
                ],
            ]);

        $newDrops = Component::query()
            ->published()
            ->with('usageCategory')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return Inertia::render('dashboard', [
            'plan' => [
                'name' => $entitlement->plan()->value,
                'is_paid' => $entitlement->isPaid(),
                'has_full_library' => $entitlement->hasFullLibrary(),
                'can_scaffold' => $entitlement->canScaffold(),
                'license' => $license === null ? null : [
                    'state' => $license->licenseState()->value,
                    'status' => $license->status->value,
                    'billing_period' => $license->billing_period->value,
                    'ends_at' => $license->ends_at?->toIso8601String(),
                ],
                'cta' => $this->cta($entitlement, $license),
            ],
            'projects' => [
                'items' => $projects,
                'total' => $user->projects()->count(),
                'limit' => $entitlement->projectLimit(),
                'index_url' => route('dashboard.projects.index'),
            ],
            'recentDownloads' => $recentDownloads,
            'newDrops' => ComponentCardResource::collection($newDrops)->resolve($request),
        ]);
    }

    /**
     * The plan card's primary action per effective plan state: free users
     * upgrade, cancelled-but-still-valid licenses renew, dunning goes to the
     * orders page, and healthy paid licenses manage their orders.
     *
     * @return array{kind: string, label: string, url: string}
     */
    private function cta(Entitlement $entitlement, ?Order $license): array
    {
        if (! $entitlement->isPaid()) {
            return [
                'kind' => 'upgrade',
                'label' => 'Upgrade to unlock the full library',
                'url' => route('pricing'),
            ];
        }

        if ($license?->licenseState() === LicenseState::PastDue) {
            return [
                'kind' => 'payment_due',
                'label' => 'Payment due — review your orders',
                'url' => route('dashboard.orders.index'),
            ];
        }

        if ($license?->licenseState() === LicenseState::CancelledValidUntil) {
            return [
                'kind' => 'renew',
                'label' => 'Renew to keep library access',
                'url' => route('pricing'),
            ];
        }

        return [
            'kind' => 'manage',
            'label' => 'Manage license & orders',
            'url' => route('dashboard.orders.index'),
        ];
    }
}
