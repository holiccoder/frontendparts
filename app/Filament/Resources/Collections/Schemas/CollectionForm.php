<?php

namespace App\Filament\Resources\Collections\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->rows(3)
                    ->helperText('Shown on the bundle page; doubles as the meta description fallback.')
                    ->columnSpanFull(),
                FileUpload::make('cover')
                    ->image(),
                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ])
                    ->required()
                    ->default('draft'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                Repeater::make('collectionComponents')
                    ->label('Components')
                    ->relationship()
                    ->schema([
                        Select::make('component_id')
                            ->label('Component')
                            ->relationship('component', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                    ])
                    ->orderColumn('sort_order')
                    ->addActionLabel('Add component')
                    ->defaultItems(0)
                    ->columnSpanFull(),
                TextInput::make('meta_title')
                    ->maxLength(255)
                    ->helperText('Defaults to the collection name when empty.'),
                Textarea::make('meta_description')
                    ->rows(2)
                    ->helperText('Defaults to the description when empty.')
                    ->columnSpanFull(),
            ]);
    }
}
