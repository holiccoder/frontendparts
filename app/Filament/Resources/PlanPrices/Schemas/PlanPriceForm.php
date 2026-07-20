<?php

namespace App\Filament\Resources\PlanPrices\Schemas;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\PlanProvider;
use App\Models\PlanPrice;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class PlanPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('plan')
                    ->options(OrderPlan::class)
                    ->required()
                    ->live(),
                Select::make('period')
                    ->options(BillingPeriod::class)
                    ->required()
                    ->live(),
                Select::make('provider')
                    ->options(PlanProvider::class)
                    ->required()
                    ->live()
                    ->rules([
                        function (Get $get, ?PlanPrice $record): Unique {
                            // Select states are cast to enums by the options
                            // binding — normalize to raw values for the rule.
                            $plan = $get('plan');
                            $period = $get('period');

                            return Rule::unique('plan_prices', 'provider')
                                ->where('plan', $plan instanceof OrderPlan ? $plan->value : $plan)
                                ->where('period', $period instanceof BillingPeriod ? $period->value : $period)
                                ->ignore($record?->id);
                        },
                    ]),
                TextInput::make('amount')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('currency')
                    ->required()
                    ->length(3)
                    ->alpha()
                    ->default('USD'),
                TextInput::make('paddle_price_id')
                    ->label('Paddle price ID')
                    ->maxLength(255),
            ]);
    }
}
