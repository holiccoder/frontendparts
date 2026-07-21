<?php

namespace App\Enums;

/**
 * Community submission lifecycle (task 5.3): pending until an admin reviews
 * it in Filament, then approved (a component enters the library pipeline) or
 * rejected (with a review note for the submitter).
 */
enum SubmissionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

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
