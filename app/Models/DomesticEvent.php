<?php

namespace App\Models;

use App\Enums\DomesticChannel;
use Illuminate\Database\Eloquent\Model;

/**
 * A processed domestic notify event (SPEC §7.5). The (channel, event_id)
 * unique constraint makes Alipay/WeChat notify delivery idempotent:
 * replayed notifications are acknowledged but never re-applied — the
 * domestic twin of PaddleEvent.
 */
class DomesticEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'channel',
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
            'channel' => DomesticChannel::class,
            'processed_at' => 'datetime',
        ];
    }
}
