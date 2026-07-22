<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\LicenseState;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Billing\Entitlement;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard overview (CSR): plan status from the entitlement plus an
 * orders summary — the chassis home for a signed-in user. New products
 * add their own widgets here.
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

        $recentOrders = $user->orders()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'plan' => $order->plan->value,
                'status' => $order->status->value,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'created_at' => $order->created_at->toIso8601String(),
            ]);

        return Inertia::render('dashboard', [
            'plan' => [
                'name' => $entitlement->plan()->value,
                'is_paid' => $entitlement->isPaid(),
                'license' => $license === null ? null : [
                    'state' => $license->licenseState()->value,
                    'status' => $license->status->value,
                    'billing_period' => $license->billing_period->value,
                    'ends_at' => $license->ends_at?->toIso8601String(),
                ],
                'cta' => $this->cta($entitlement, $license),
            ],
            'orders' => [
                'items' => $recentOrders,
                'total' => $user->orders()->count(),
                'index_url' => route('dashboard.orders.index'),
            ],
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
                'label' => 'Upgrade to a paid plan',
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
                'label' => 'Renew to keep your access',
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
