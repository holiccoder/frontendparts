<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\TicketAuthorType;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreTicketMessageRequest;
use App\Models\SupportTicket;
use App\Services\Support\TicketAttachmentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * User-side ticket replies (SPEC §13.3): appends to the thread and re-opens
 * pending/resolved tickets back to open (TicketStatus transition map).
 * Closed tickets reject replies — closed is terminal.
 */
class TicketMessageController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function store(StoreTicketMessageRequest $request, SupportTicket $ticket, TicketAttachmentStore $attachments): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        if ($ticket->status === TicketStatus::Closed) {
            throw ValidationException::withMessages([
                'body' => 'This ticket is closed — open a new ticket instead.',
            ]);
        }

        $ticket->messages()->create([
            'author_type' => TicketAuthorType::User,
            'author_id' => $request->user()->id,
            'body' => $request->validated('body'),
            'attachments' => $attachments->store($ticket, $request->file('attachments', [])),
        ]);

        // User reply on pending/resolved re-opens the ticket (TicketStatus map).
        if (in_array($ticket->status, [TicketStatus::Pending, TicketStatus::Resolved], true)) {
            $ticket->update(['status' => TicketStatus::Open]);
        }

        return back();
    }
}
