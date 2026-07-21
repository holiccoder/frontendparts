<?php

namespace App\Filament\Resources\AffiliatePayouts\Pages;

use App\Filament\Resources\AffiliatePayouts\AffiliatePayoutResource;
use App\Services\Affiliates\PayoutService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

/**
 * Payout batches (SPEC §17.5): the header action runs the monthly batch —
 * every payable commission whose per-affiliate (+currency) total clears
 * the payout threshold is swept into a processing payout; below-threshold
 * balances roll over. Each payout is then paid manually and marked paid
 * with the provider reference (record action on the table).
 */
class ListAffiliatePayouts extends ListRecords
{
    protected static string $resource = AffiliatePayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runBatch')
                ->label('Run payout batch')
                ->icon(Heroicon::OutlinedPlay)
                ->requiresConfirmation()
                ->modalHeading('Run payout batch')
                ->modalDescription('Sweep every payable commission over the payout threshold into processing payouts, grouped per affiliate and currency. Below-threshold balances roll over.')
                ->action(function (PayoutService $payouts): void {
                    $created = $payouts->batch();

                    Notification::make()
                        ->title('Payout batch complete')
                        ->body("{$created->count()} payout(s) created.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
