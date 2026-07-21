<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Database\Factories\OrganizationInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * An emailed invitation to join an organization (task 5.2). The acceptance
 * link is a signed URL (tamper-proof, revocation = deleting the row); the
 * stored token identifies the invitation for record-keeping. Accepting
 * attaches the user as a member and stamps accepted_at.
 */
class OrganizationInvitation extends Model
{
    /** @use HasFactory<OrganizationInvitationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'email',
        'role',
        'token',
        'invited_by_user_id',
        'accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class,
            'accepted_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null;
    }

    /**
     * The signed acceptance link carried by the invitation email. Guests
     * are bounced through login/registration and back by the auth
     * middleware, so one link covers existing users and post-registration
     * claims alike.
     */
    public function acceptUrl(): string
    {
        return URL::signedRoute('team.invitations.accept', ['invitation' => $this]);
    }
}
