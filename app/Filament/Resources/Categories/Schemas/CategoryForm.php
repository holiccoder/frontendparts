<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Enums\CategoryType;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(CategoryType::class)
                    ->required()
                    ->live()
                    ->disabledOn('edit'),
                TextInput::make('zone')
                    ->helperText('Grouping zone for usage patterns (e.g. Navigation, Conversion).')
                    ->visible(fn (Get $get): bool => self::typeValue($get) === CategoryType::Usage->value)
                    ->required(fn (Get $get): bool => self::typeValue($get) === CategoryType::Usage->value)
                    ->maxLength(255),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->rules([
                        fn (Get $get, ?Category $record): Unique => Rule::unique('categories', 'slug')
                            ->where('type', self::typeValue($get) ?? $record?->type?->value)
                            ->ignore($record?->id),
                    ]),
                TextInput::make('sort_order')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
            ]);
    }

    /**
     * The type select state is cast to a CategoryType enum by the options
     * binding, so normalize enum-or-string reads to the raw value.
     */
    private static function typeValue(Get $get): ?string
    {
        $type = $get('type');

        return $type instanceof CategoryType ? $type->value : $type;
    }
}
