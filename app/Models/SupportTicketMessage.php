<?php

namespace App\Models;

use App\Enums\TicketAuthorType;
use Database\Factories\SupportTicketMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a support-ticket thread (SPEC §13.3). `author_type` marks
 * user vs admin; `author_id` is the authoring user/admin id. Attachments are
 * stored on the private disk and tracked here as JSON
 * `[{name, path, size}]` entries.
 */
class SupportTicketMessage extends Model
{
    /** @use HasFactory<SupportTicketMessageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'author_type',
        'author_id',
        'body',
        'attachments',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'author_type' => TicketAuthorType::class,
            'attachments' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }
}
