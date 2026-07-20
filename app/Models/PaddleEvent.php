<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A processed Paddle webhook event (SPEC §7.3). The unique `event_id`
 * constraint makes webhook delivery idempotent: replayed events are no-ops.
 */
class PaddleEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'event_type',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
