<?php

namespace App\Models;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Support ticket (SPEC §13.3): a threaded conversation between a user and the
 * (single) admin. The `takedown` category doubles as the §9 legal takedown
 * channel. Status transitions follow the TicketStatus map — pending/resolved
 * are admin-set, users may only close.
 */
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'subject',
        'category',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'status' => TicketStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id');
    }

    /**
     * Order context for billing tickets (SPEC §13.3): the requester's five
     * most recent orders, shown in the Filament inbox so billing questions
     * can be answered without switching to the Orders resource.
     *
     * @return Collection<int, Order>
     */
    public function recentOrders(): Collection
    {
        return $this->user->orders()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }
}
