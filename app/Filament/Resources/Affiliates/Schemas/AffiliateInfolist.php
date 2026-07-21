<?php

namespace App\Filament\Resources\Affiliates\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Affiliate detail (SPEC §17.5): identity, referral code and program state,
 * plus the saved payout coordinates the monthly batch snapshots from.
 */
class AffiliateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Affiliate')
                    ->schema([
                        TextEntry::make('user.name'),
                        TextEntry::make('user.email'),
                        TextEntry::make('code')
                            ->copyable(),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('terms_accepted_at')
                            ->dateTime(),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Payout method')
                    ->description('Snapshotted onto each payout at batch time.')
                    ->schema([
                        TextEntry::make('payout_method.method')
                            ->label('Rail')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'paypal' => 'PayPal',
                                'wise' => 'Wise',
                                default => '—',
                            }),
                        TextEntry::make('payout_method.email')
                            ->label('Account email')
                            ->placeholder('—'),
                        TextEntry::make('payout_method.account_name')
                            ->label('Account name')
                            ->placeholder('—'),
                    ])
                    ->columns(3),
            ]);
    }
}
