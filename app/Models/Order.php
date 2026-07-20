<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\LicenseState;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Observers\OrderObserver;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'billing_period',
        'amount',
        'currency',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'past_due_at',
        'cancellation_reason',
        'paddle_customer_id',
        'paddle_transaction_id',
        'paddle_subscription_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => OrderPlan::class,
            'status' => OrderStatus::class,
            'billing_period' => BillingPeriod::class,
            'amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'past_due_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * License state shown on the dashboard orders page (SPEC §15.4): the §7.3
     * state machine with Cancelled split by whether access is still valid
     * until ends_at — the same cut-off EntitlementService uses to decide
     * whether the order still entitles.
     */
    public function licenseState(): LicenseState
    {
        return match ($this->status) {
            OrderStatus::Active => LicenseState::Active,
            OrderStatus::PastDue => LicenseState::PastDue,
            OrderStatus::Pending => LicenseState::Pending,
            OrderStatus::Refunded => LicenseState::Refunded,
            OrderStatus::Expired => LicenseState::Expired,
            OrderStatus::Cancelled => $this->ends_at !== null && $this->ends_at->isFuture()
                ? LicenseState::CancelledValidUntil
                : LicenseState::Expired,
        };
    }

    /**
     * Receipt/invoice link for the Paddle transaction (SPEC §15.4). Paddle
     * Billing has no permanent public receipt URL — receipts and invoices are
     * emailed by Paddle as merchant of record (SPEC §16.1) — so this builds
     * the Paddle dashboard transaction URL from the stored transaction id,
     * sandbox-aware to mirror the checkout environment. Kept in one place so
     * it can be swapped for a customer-portal session or API invoice-PDF flow
     * later; null when no transaction id is stored (e.g. unpaid orders).
     */
    public function receiptUrl(): ?string
    {
        if ($this->paddle_transaction_id === null) {
            return null;
        }

        $host = config('cashier.sandbox') ? 'sandbox-vendors.paddle.com' : 'vendors.paddle.com';

        return "https://{$host}/transactions-v2/{$this->paddle_transaction_id}";
    }
}
