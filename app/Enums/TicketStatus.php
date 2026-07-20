<?php

namespace App\Enums;

/**
 * Ticket lifecycle (SPEC §13.3). Transition map (SPEC is silent on edges, so
 * conventional helpdesk behavior applies):
 *
 * - open     → pending (admin reply) · resolved (admin) · closed
 * - pending  → open (user reply re-opens) · resolved (admin) · closed
 * - resolved → open (user reply re-opens) · pending (admin reply) · closed
 * - closed   → terminal (no transitions; replies are rejected)
 *
 * Users may only ever request `closed`; pending/resolved are admin-set.
 */
enum TicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Open => in_array($target, [self::Pending, self::Resolved, self::Closed], true),
            self::Pending => in_array($target, [self::Open, self::Resolved, self::Closed], true),
            self::Resolved => in_array($target, [self::Open, self::Pending, self::Closed], true),
            self::Closed => false,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => ucfirst($status->value)])
            ->all();
    }
}
