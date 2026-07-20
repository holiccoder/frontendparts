<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use Illuminate\Http\UploadedFile;

/**
 * Stores message attachments on the private disk (SPEC §13.3) and returns the
 * JSON-ready `[{name, path, size}]` entries persisted on the message.
 */
class TicketAttachmentStore
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return list<array{name: string, path: string, size: int}>
     */
    public function store(SupportTicket $ticket, array $files): array
    {
        $attachments = [];

        foreach ($files as $file) {
            $attachments[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $file->store("support-tickets/{$ticket->id}"),
                'size' => $file->getSize(),
            ];
        }

        return $attachments;
    }
}
