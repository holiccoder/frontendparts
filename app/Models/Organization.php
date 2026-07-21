<?php

namespace App\Models;

use App\Enums\OrderPlan;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A team-tier organization (task 5.2): one owner, any number of members
 * (`organization_user` pivot with a minimal role vocabulary), and email
 * invitations for seats not yet claimed.
 *
 * The organization's subscription is the owner's latest team-plan order —
 * the order itself stays a normal personal order (plan = team, seats = N),
 * so the SPEC §7.3 state machine applies unchanged; EntitlementService
 * resolves membership against it live.
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'owner_user_id',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * Pending (not yet accepted) invitations.
     */
    public function pendingInvitations(): HasMany
    {
        return $this->invitations()->whereNull('accepted_at');
    }

    /**
     * Seats in use — every member occupies one seat, owner included.
     */
    public function seats(): int
    {
        return $this->members()->count();
    }

    /**
     * The organization's team subscription: the owner's latest team-plan
     * order. Whether it currently entitles is decided by EntitlementService
     * with the same rules as personal orders (latest order wins).
     */
    public function teamOrder(): ?Order
    {
        return $this->owner->orders()
            ->where('plan', OrderPlan::Team)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }
}
