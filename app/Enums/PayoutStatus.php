<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';

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
