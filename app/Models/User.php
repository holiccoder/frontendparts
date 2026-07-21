<?php

namespace App\Models;

use App\Services\Notifications\NotificationPreferences;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Paddle\Billable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preview_layout' => 'array',
            'notification_preferences' => 'array',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function componentEvents(): HasMany
    {
        return $this->hasMany(ComponentEvent::class);
    }

    public function sequenceSends(): HasMany
    {
        return $this->hasMany(SequenceSend::class);
    }

    /**
     * The connected GitHub account used for repo exports (SPEC §6.4).
     */
    public function githubConnection(): HasOne
    {
        return $this->hasOne(GithubConnection::class);
    }

    /**
     * Whether any marketing category (digest / blog / product updates) is
     * still enabled — convenience delegate; the preference rules themselves
     * live only in NotificationPreferences (SPEC §16.3).
     */
    public function wantsMarketing(): bool
    {
        return app(NotificationPreferences::class)->wantsMarketing($this);
    }
}
