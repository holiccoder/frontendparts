<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Billing = 'billing';
    case Technical = 'technical';
    case License = 'license';
    case Takedown = 'takedown';
    case Other = 'other';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $category): array => [$category->value => ucfirst($category->value)])
            ->all();
    }
}
