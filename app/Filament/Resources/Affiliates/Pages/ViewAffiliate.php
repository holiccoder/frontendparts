<?php

namespace App\Filament\Resources\Affiliates\Pages;

use App\Enums\AffiliateStatus;
use App\Filament\Resources\Affiliates\AffiliateResource;
use App\Models\Affiliate;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Affiliate detail with the fraud controls (SPEC §17.5, §17.2): suspend
 * stops the code recording clicks and earning new commissions (history is
 * kept); unsuspend re-activates it.
 */
class ViewAffiliate extends ViewRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('suspend')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Suspend affiliate')
                ->modalDescription(fn (Affiliate $record): string => sprintf(
                    'Suspend %s? Their referral link stops recording clicks and earning new commissions immediately. History is kept.',
                    $record->user?->email ?? "affiliate #{$record->id}",
                ))
                ->visible(fn (Affiliate $record): bool => $record->status === AffiliateStatus::Active)
                ->action(function (Affiliate $record): void {
                    $record->update(['status' => AffiliateStatus::Suspended]);

                    Notification::make()
                        ->title('Affiliate suspended')
                        ->success()
                        ->send();
                }),
            Action::make('unsuspend')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Affiliate $record): bool => $record->status === AffiliateStatus::Suspended)
                ->action(function (Affiliate $record): void {
                    $record->update(['status' => AffiliateStatus::Active]);

                    Notification::make()
                        ->title('Affiliate re-activated')
                        ->success()
                        ->send();
                }),
        ];
    }
}
