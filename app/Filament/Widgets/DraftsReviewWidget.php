<?php

namespace App\Filament\Widgets;

use App\Enums\ComponentStatus;
use App\Filament\Resources\Components\Actions\PreviewComponentAction;
use App\Filament\Resources\Components\Actions\PublishComponentAction;
use App\Filament\Resources\Components\Actions\RejectComponentAction;
use App\Models\Component;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * P0 action queue (SPEC §8.6 row 4): components awaiting review with inline
 * Preview / Publish / Reject actions — the same workflow actions as the
 * component resource, so QA rules apply identically here.
 */
class DraftsReviewWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Drafts awaiting review')
            ->query(
                Component::query()
                    ->where('status', ComponentStatus::InReview)
                    ->latest('updated_at'),
            )
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('level')
                    ->badge(),
                TextColumn::make('usageCategory.name')
                    ->label('Usage'),
                TextColumn::make('updated_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                PreviewComponentAction::make(),
                PublishComponentAction::make(),
                RejectComponentAction::make(),
            ])
            ->paginated(false);
    }
}
