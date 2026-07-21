<?php

namespace App\Filament\Pages;

use App\Support\Settings as PlatformSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Platform settings (SPEC §8.7): every tunable product value editable in the
 * panel — never hardcoded. Form fields map onto the registered keys of the
 * Settings service; saving writes through the service (which invalidates the
 * cached values), so changes take effect without a deploy.
 */
class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    /**
     * Form field name → registered settings key.
     *
     * @var array<string, string>
     */
    private const FIELD_MAP = [
        'plans_project_limit_free' => 'plans.project_limit.free',
        'plans_project_limit_starter' => 'plans.project_limit.starter',
        'plans_project_limit_pro' => 'plans.project_limit.pro',
        'plans_project_limit_team' => 'plans.project_limit.team',
        'billing_refund_window_days' => 'billing.refund_window_days',
        'features_preview_dark_toggle' => 'features.preview_dark_toggle',
        'features_tree_interactions' => 'features.tree_interactions',
        'features_live_edit' => 'features.live_edit',
        'goals_launch_component_target' => 'goals.launch_component_target',
        'goals_components_per_month' => 'goals.components_per_month',
        'goals_organic_visits_monthly' => 'goals.organic_visits_monthly',
        'goals_signup_conversion_pct' => 'goals.signup_conversion_pct',
        'goals_paid_conversion_pct_min' => 'goals.paid_conversion_pct_min',
        'goals_paid_conversion_pct_max' => 'goals.paid_conversion_pct_max',
        'goals_churn_max_pct' => 'goals.churn_max_pct',
        'goals_mrr_target_usd' => 'goals.mrr_target_usd',
        'fx_cny_to_usd' => 'fx.cny_to_usd',
        'affiliate_commission_rate' => 'affiliate.commission_rate',
        'affiliate_cookie_days' => 'affiliate.cookie_days',
        'affiliate_recurring_months' => 'affiliate.recurring_months',
        'affiliate_holding_days' => 'affiliate.holding_days',
        'affiliate_payout_threshold' => 'affiliate.payout_threshold',
    ];

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(PlatformSettings::class);

        $state = [];

        foreach (self::FIELD_MAP as $field => $key) {
            $state[$field] = $settings->get($key);
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Plans & limits')
                        ->description('Per-plan project limits and the refund window. Empty Pro/Team limit means unlimited.')
                        ->columns(2)
                        ->components([
                            TextInput::make('plans_project_limit_free')
                                ->label('Free project limit')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            TextInput::make('plans_project_limit_starter')
                                ->label('Starter project limit')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            TextInput::make('plans_project_limit_pro')
                                ->label('Pro project limit')
                                ->numeric()
                                ->minValue(0)
                                ->placeholder('Unlimited')
                                ->helperText('Leave empty for unlimited projects.'),
                            TextInput::make('plans_project_limit_team')
                                ->label('Team project limit (per seat)')
                                ->numeric()
                                ->minValue(0)
                                ->placeholder('Unlimited')
                                ->helperText('Leave empty for unlimited projects.'),
                            TextInput::make('billing_refund_window_days')
                                ->label('Refund window (days)')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                        ]),
                    Section::make('Feature flags')
                        ->description('Runtime toggles read by the site — no deploy needed.')
                        ->components([
                            Toggle::make('features_preview_dark_toggle')
                                ->label('Preview dark/light toggle'),
                            Toggle::make('features_tree_interactions')
                                ->label('Tree interactions (pin, navigate, scroll-to, keyboard)'),
                            Toggle::make('features_live_edit')
                                ->label('Live-edit mode')
                                ->helperText('In-browser Edit tab (React) in the preview modal. Phase 3.1.'),
                        ]),
                    Section::make('Goals')
                        ->description('Targets surfaced on the admin dashboard as target-vs-actual tracking.')
                        ->columns(2)
                        ->components([
                            TextInput::make('goals_launch_component_target')
                                ->label('Launch component target')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            TextInput::make('goals_components_per_month')
                                ->label('Components per month')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            TextInput::make('goals_organic_visits_monthly')
                                ->label('Organic visits / month')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            TextInput::make('goals_signup_conversion_pct')
                                ->label('Signup conversion %')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->required(),
                            TextInput::make('goals_paid_conversion_pct_min')
                                ->label('Paid conversion % (min)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->required(),
                            TextInput::make('goals_paid_conversion_pct_max')
                                ->label('Paid conversion % (max)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->required(),
                            TextInput::make('goals_churn_max_pct')
                                ->label('Churn max %')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->required(),
                            TextInput::make('goals_mrr_target_usd')
                                ->label('MRR target (USD)')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            TextInput::make('fx_cny_to_usd')
                                ->label('FX rate: CNY → USD')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                        ]),
                    Section::make('Affiliate')
                        ->description('Affiliate program knobs (SPEC §17.2) — commission rate, attribution cookie, renewal window, holding period and payout threshold.')
                        ->columns(2)
                        ->components([
                            TextInput::make('affiliate_commission_rate')
                                ->label('Commission rate (% of net)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->required(),
                            TextInput::make('affiliate_cookie_days')
                                ->label('Attribution cookie (days)')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            TextInput::make('affiliate_recurring_months')
                                ->label('Renewal commission window (months)')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            TextInput::make('affiliate_holding_days')
                                ->label('Holding period after refund window (days)')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            TextInput::make('affiliate_payout_threshold')
                                ->label('Payout threshold (USD)')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save settings')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = app(PlatformSettings::class);

        foreach (self::FIELD_MAP as $field => $key) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $settings->set($key, $data[$field]);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
