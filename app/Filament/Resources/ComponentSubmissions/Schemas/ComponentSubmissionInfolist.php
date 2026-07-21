<?php

namespace App\Filament\Resources\ComponentSubmissions\Schemas;

use App\Filament\Resources\Components\ComponentResource;
use App\Models\Component;
use App\Models\ComponentSubmission;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Submission review view (task 5.3): submitter + metadata, the description
 * (usage scenario), the pasted sources in mono with copy buttons, the
 * sample data and — once approved — a link to the created component.
 */
class ComponentSubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Submission')
                    ->columns(2)
                    ->components([
                        TextEntry::make('name'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('level')
                            ->badge(),
                        TextEntry::make('framework')
                            ->badge(),
                        TextEntry::make('usageCategory.name')
                            ->label('Usage pattern'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('user.name'),
                        TextEntry::make('user.email'),
                        TextEntry::make('source_url')
                            ->label('Citation URL')
                            ->url(fn (ComponentSubmission $record): ?string => $record->source_url, shouldOpenInNewTab: true)
                            ->placeholder('—'),
                        TextEntry::make('review_note')
                            ->label('Review note')
                            ->visible(fn (ComponentSubmission $record): bool => filled($record->review_note)),
                        TextEntry::make('description')
                            ->label('Description & usage scenario')
                            ->columnSpanFull(),
                    ]),
                Section::make('React code')
                    ->visible(fn (ComponentSubmission $record): bool => filled($record->react_code))
                    ->components([
                        TextEntry::make('react_code')
                            ->label('')
                            ->fontFamily('mono')
                            ->copyable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Vue code')
                    ->visible(fn (ComponentSubmission $record): bool => filled($record->vue_code))
                    ->components([
                        TextEntry::make('vue_code')
                            ->label('')
                            ->fontFamily('mono')
                            ->copyable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Sample data')
                    ->visible(fn (ComponentSubmission $record): bool => filled($record->sample_data))
                    ->components([
                        KeyValueEntry::make('sample_data')
                            ->label('')
                            ->columnSpanFull(),
                    ]),
                Section::make('Approved component')
                    ->visible(fn (ComponentSubmission $record): bool => $record->component_id !== null)
                    ->components([
                        TextEntry::make('component.name')
                            ->label('Component')
                            ->url(fn (ComponentSubmission $record): ?string => $record->component instanceof Component
                                ? ComponentResource::getUrl('view', ['record' => $record->component])
                                : null),
                        TextEntry::make('component.slug')
                            ->fontFamily('mono'),
                        TextEntry::make('component.status')
                            ->badge(),
                    ])
                    ->columns(3),
            ]);
    }
}
