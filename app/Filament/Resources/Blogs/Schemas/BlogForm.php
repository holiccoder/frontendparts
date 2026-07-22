<?php

namespace App\Filament\Resources\Blogs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BlogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Author')
                    ->relationship('author', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true),
                Textarea::make('excerpt')
                    ->columnSpanFull(),
                Textarea::make('body')
                    ->required()
                    ->rows(14)
                    ->helperText('Markdown. H2/H3 headings feed the per-article table of contents.')
                    ->columnSpanFull(),
                FileUpload::make('featured_image')
                    ->image(),
                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ])
                    ->required()
                    ->default('draft'),
                DateTimePicker::make('published_at')
                    ->helperText('A future date schedules the post: it stays hidden until then.'),
                TextInput::make('reading_time')
                    ->label('Reading time (min)')
                    ->disabled()
                    ->dehydrated(false),
                Select::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique('blog_categories', 'slug'),
                    ]),
                Select::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique('blog_tags', 'slug'),
                    ]),
                TextInput::make('meta_title')
                    ->maxLength(255)
                    ->helperText('Defaults to the post title when empty.'),
                Textarea::make('meta_description')
                    ->rows(2)
                    ->helperText('Defaults to the excerpt when empty.')
                    ->columnSpanFull(),
            ]);
    }
}
