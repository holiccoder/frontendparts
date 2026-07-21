<?php

namespace App\Models;

use Database\Factories\GithubConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's connected GitHub account (SPEC §6.4): the OAuth identity (`repo`
 * scope) used for repo exports. One connection per user; the access token is
 * encrypted at rest through the `encrypted` cast and is only ever decrypted
 * inside the GitHub API client.
 */
class GithubConnection extends Model
{
    /** @use HasFactory<GithubConnectionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'github_id',
        'github_login',
        'token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
