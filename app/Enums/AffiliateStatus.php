<?php

namespace App\Enums;

enum AffiliateStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

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
