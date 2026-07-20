<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\TicketAuthorType;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreTicketRequest;
use App\Http\Requests\Support\UpdateTicketRequest;
use App\Models\SupportTicket;
use App\Notifications\TicketCreatedNotification;
use App\Services\Support\TicketAttachmentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * User-side support tickets (SPEC §13.3, §15.4, CSR zone): list, create with
 * category, threaded view and user-close. Replies live in
 * TicketMessageController. Status transitions follow the TicketStatus map —
 * users may only close; pending/resolved are admin-set.
 */
class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = $request->user()->tickets()
            ->withCount('messages')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SupportTicket $ticket): array => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'category' => $ticket->category->value,
                'status' => $ticket->status->value,
                'messages_count' => $ticket->messages_count,
                'created_at' => $ticket->created_at->toIso8601String(),
                'url' => route('dashboard.tickets.show', $ticket),
            ]);

        return Inertia::render('dashboard/tickets/index', [
            'tickets' => $tickets,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('dashboard/tickets/new', [
            'categories' => TicketCategory::options(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(StoreTicketRequest $request, TicketAttachmentStore $attachments): RedirectResponse
    {
        $ticket = $request->user()->tickets()->create([
            'subject' => $request->validated('subject'),
            'category' => $request->validated('category'),
            'status' => TicketStatus::Open,
        ]);

        $ticket->messages()->create([
            'author_type' => TicketAuthorType::User,
            'author_id' => $request->user()->id,
            'body' => $request->validated('body'),
            'attachments' => $attachments->store($ticket, $request->file('attachments', [])),
        ]);

        // New-ticket alert to the support inbox (SPEC §16.1).
        Notification::route('mail', config('mail.admin.address'))
            ->notify(new TicketCreatedNotification($ticket));

        return to_route('dashboard.tickets.show', $ticket)
            ->with('notice', 'Ticket created — we will reply by email and in the thread.');
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        $messages = $ticket->messages()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($message): array => [
                'id' => $message->id,
                'author_type' => $message->author_type->value,
                'body' => $message->body,
                'attachments' => collect($message->attachments ?? [])
                    ->map(fn (array $attachment): array => ['name' => $attachment['name'], 'size' => $attachment['size']])
                    ->all(),
                'created_at' => $message->created_at->toIso8601String(),
            ]);

        return Inertia::render('dashboard/tickets/show', [
            'ticket' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'category' => $ticket->category->value,
                'status' => $ticket->status->value,
                'created_at' => $ticket->created_at->toIso8601String(),
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * User-side status update: close only. Anything else — or closing a
     * ticket whose status cannot transition to closed (TicketStatus map) —
     * is rejected.
     *
     * @throws ValidationException
     */
    public function update(UpdateTicketRequest $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        if (! $ticket->status->canTransitionTo(TicketStatus::Closed)) {
            throw ValidationException::withMessages([
                'status' => 'This ticket can no longer be closed.',
            ]);
        }

        $ticket->update(['status' => TicketStatus::Closed]);

        return back()->with('notice', 'Ticket closed.');
    }
}
