<?php

namespace App\Models;

use Database\Factories\SequenceSendFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lifecycle-engine progress record (SPEC §16.2): one row per user per
 * sequence step actually sent. The unique (user_id, sequence, step) index
 * makes every send idempotent — the engine creates the row first and only
 * the creating process sends the mail.
 */
class SequenceSend extends Model
{
    /** @use HasFactory<SequenceSendFactory> */
    use HasFactory;

    /**
     * The table records sent_at only — no created_at/updated_at.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'sequence',
        'step',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
