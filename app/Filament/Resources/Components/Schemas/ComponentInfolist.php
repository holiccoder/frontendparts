<?php

namespace App\Filament\Resources\Components\Schemas;

use App\Models\Component;
use App\Services\Catalog\CompositionTree;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ComponentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Meta')
                    ->columns(3)
                    ->components([
                        TextEntry::make('name'),
                        TextEntry::make('slug')
                            ->fontFamily('mono'),
                        TextEntry::make('version'),
                        TextEntry::make('level')
                            ->badge(),
                        TextEntry::make('access_level')
                            ->label('Access')
                            ->badge(),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('usageCategory.name')
                            ->label('Usage pattern'),
                        TextEntry::make('industries.name')
                            ->label('Industries')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('tags.name')
                            ->label('Tags')
                            ->badge()
                            ->color('gray')
                            ->placeholder('—'),
                        TextEntry::make('preview_built_at')
                            ->label('Preview built')
                            ->dateTime()
                            ->placeholder('Never'),
                        TextEntry::make('created_at')
                            ->label('Synced')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
                Section::make('Citation')
                    ->columns(2)
                    ->visible(fn (Component $record): bool => filled($record->source_name) || filled($record->source_url))
                    ->components([
                        TextEntry::make('source_name')
                            ->label('Source')
                            ->placeholder('—'),
                        TextEntry::make('source_url')
                            ->label('Source URL')
                            ->url(fn (Component $record): ?string => $record->source_url, shouldOpenInNewTab: true)
                            ->placeholder('—'),
                    ]),
                Section::make('Dependencies')
                    ->visible(fn (Component $record): bool => filled($record->deps))
                    ->components([
                        KeyValueEntry::make('deps')
                            ->columnSpanFull(),
                    ]),
                Section::make('Composition tree')
                    ->description('Read-only visualization of the parsed component graph (SPEC §2.2).')
                    ->components([
                        ViewEntry::make('composition_tree')
                            ->label('')
                            ->state(fn (Component $record): array => app(CompositionTree::class)->for($record))
                            ->view('filament.infolists.entries.composition-tree')
                            ->columnSpanFull(),
                    ]),
                Section::make('Review')
                    ->visible(fn (Component $record): bool => filled($record->qa_checklist) || filled($record->review_note))
                    ->components([
                        KeyValueEntry::make('qa_checklist')
                            ->label('QA checklist')
                            ->columnSpanFull(),
                        TextEntry::make('review_note')
                            ->label('Review note')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
